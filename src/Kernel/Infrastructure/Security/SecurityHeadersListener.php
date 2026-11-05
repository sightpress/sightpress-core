<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the security headers on every response. It runs after every other
 * response listener and overwrites, so neither plugins nor features can
 * loosen them.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -2048)]
final readonly class SecurityHeadersListener
{
    private const string PERMISSIONS_POLICY = 'accelerometer=(), autoplay=(self), browsing-topics=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()';

    public function __construct(
        private ContentSecurityPolicy $policy,
        private string $adminPathPrefix = '/admin',
        private int $hstsMaxAge = 31536000,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $path = $request->getPathInfo();
        $admin = $path === $this->adminPathPrefix || str_starts_with($path, rtrim($this->adminPathPrefix, '/').'/');

        // A shared cache would replay one nonce to every visitor, so anonymous cacheable pages
        // only get a nonce when their template asked for one (public templates have no inline code).
        $cacheable = $response->headers->hasCacheControlDirective('public') || $response->headers->hasCacheControlDirective('s-maxage');
        $nonce = $cacheable && !CspNonce::wasUsed($request) ? null : CspNonce::forRequest($request);

        $headers = $response->headers;
        $headers->set('Content-Security-Policy', $this->policy->build($nonce, $admin, $request->isSecure()));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', self::PERMISSIONS_POLICY);
        if ($request->isSecure() && $this->hstsMaxAge > 0) {
            $headers->set('Strict-Transport-Security', 'max-age='.$this->hstsMaxAge.'; includeSubDomains');
        }
    }
}
