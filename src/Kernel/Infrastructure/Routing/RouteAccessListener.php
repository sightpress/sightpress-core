<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Routing;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces the "_access" route default on every request, so the rule
 * RouteAccessAuditor checks in the test suite is also what happens at run
 * time. "public" lets everyone in; any other value is the voter attribute that
 * must grant access. It fails closed: a route without a rule, or an
 * install without an authorization checker, refuses the request.
 *
 * It runs after routing (32) and the firewall (8), so the user is known.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
final readonly class RouteAccessListener
{
    public const string PUBLIC = 'public';

    public function __construct(private ?AuthorizationCheckerInterface $authorizationChecker = null)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        // No route (the router answers 404) or a framework route such as the profiler.
        if (!\is_string($route) || str_starts_with($route, '_')) {
            return;
        }

        $access = $request->attributes->get(RouteAccessAuditor::DEFAULT_KEY);
        if (!\is_string($access) || '' === trim($access)) {
            throw new AccessDeniedHttpException(\sprintf('Route "%s" has no access rule, so nobody may use it.', $route));
        }
        if (self::PUBLIC === $access) {
            return;
        }
        if (null === $this->authorizationChecker) {
            throw new AccessDeniedHttpException(\sprintf('Route "%s" needs "%s", and no authorization checker is configured.', $route, $access));
        }
        if (!$this->authorizationChecker->isGranted($access, $request)) {
            $exception = new AccessDeniedException(\sprintf('Access to route "%s" needs "%s".', $route, $access));
            $exception->setAttributes([$access]);
            $exception->setSubject($request);

            throw $exception;
        }
    }
}
