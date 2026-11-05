<?php

declare(strict_types=1);

namespace SightPress\Core\Localization\Infrastructure\Http;

use SightPress\Core\Localization\Application\UiLocale;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Chooses the admin interface locale. Until people have preferences (M1), it
 * negotiates from Accept-Language among the launch locales, then en-GB. A
 * locale already chosen for the request (by user preferences, later) wins.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 15)]
final readonly class UiLocaleListener
{
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $chosen = $request->attributes->get(UiLocale::ATTRIBUTE);
        if (!$chosen instanceof UiLocale) {
            $chosen = UiLocale::negotiate(array_values($request->getLanguages()));
            $request->attributes->set(UiLocale::ATTRIBUTE, $chosen);
        }
        $request->setLocale($chosen->translatorLocale());
    }
}
