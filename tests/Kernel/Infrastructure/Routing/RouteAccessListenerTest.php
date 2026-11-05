<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Kernel\Infrastructure\Routing;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Kernel\Infrastructure\Routing\RouteAccessListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class RouteAccessListenerTest extends TestCase
{
    /** @param array<string, mixed> $attributes */
    private function event(array $attributes, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/somewhere');
        $request->attributes->add($attributes);

        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type);
    }

    /** @param list<string> $granted */
    private function checker(array $granted): AuthorizationCheckerInterface
    {
        return new readonly class($granted) implements AuthorizationCheckerInterface {
            /** @param list<string> $granted */
            public function __construct(private array $granted)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return \in_array($attribute, $this->granted, true);
            }
        };
    }

    public function testPublicRoutesAreOpenToEveryone(): void
    {
        $event = $this->event(['_route' => 'front_home', '_access' => 'public']);
        new RouteAccessListener()($event);

        self::assertFalse($event->hasResponse());
    }

    public function testARouteWithoutAnAccessRuleIsRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Route "admin_secret" has no access rule, so nobody may use it.');

        new RouteAccessListener($this->checker(['admin.access']))($this->event(['_route' => 'admin_secret']));
    }

    public function testAnEmptyAccessRuleIsRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        new RouteAccessListener()($this->event(['_route' => 'admin_secret', '_access' => ' ']));
    }

    public function testWithoutACheckerOnlyPublicRoutesAnswer(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Route "admin_home" needs "admin.access", and no authorization checker is configured.');

        new RouteAccessListener()($this->event(['_route' => 'admin_home', '_access' => 'admin.access']));
    }

    public function testTheVoterAttributeMustBeGranted(): void
    {
        new RouteAccessListener($this->checker(['admin.access']))($this->event(['_route' => 'admin_home', '_access' => 'admin.access']));

        try {
            new RouteAccessListener($this->checker([]))($this->event(['_route' => 'admin_home', '_access' => 'admin.access']));
            self::fail('A lower role must be refused.');
        } catch (AccessDeniedException $e) {
            self::assertSame(['admin.access'], $e->getAttributes());
            self::assertSame('Access to route "admin_home" needs "admin.access".', $e->getMessage());
        }
    }

    public function testRequestsWithoutARouteFrameworkRoutesAndSubRequestsAreLeftAlone(): void
    {
        $listener = new RouteAccessListener();
        $listener($this->event([]));
        $listener($this->event(['_route' => '_profiler']));
        $listener($this->event(['_route' => 'admin_home'], HttpKernelInterface::SUB_REQUEST));

        $this->addToAssertionCount(1);
    }
}
