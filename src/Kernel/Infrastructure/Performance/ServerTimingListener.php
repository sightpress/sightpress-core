<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Performance;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds a Server-Timing header with the time spent in the application. The
 * container registers it only outside production (see config/services.php).
 */
final class ServerTimingListener
{
    private const string START = '_sightpress_started_at';

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::START, hrtime(true));
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -4096)]
    public function onResponse(ResponseEvent $event): void
    {
        $started = $event->getRequest()->attributes->get(self::START);
        if (!$event->isMainRequest() || !\is_int($started)) {
            return;
        }

        $milliseconds = (hrtime(true) - $started) / 1_000_000;
        $event->getResponse()->headers->set('Server-Timing', \sprintf('app;dur=%.1f;desc="Application"', $milliseconds));
    }
}
