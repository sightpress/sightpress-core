<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App\EventListener;

use SightPress\Core\Localization\Application\UiLocale;
use SightPress\Plugin\Core\Value\Locale;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Test-only: chooses the interface locale from ?ui_locale=, standing in for
 * user preferences (M1). It is how the he-IL and ar-SA RTL test locales,
 * which have no catalogue and are never negotiated, reach the admin layout.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final readonly class TestLocaleListener
{
    public function __invoke(RequestEvent $event): void
    {
        $tag = $event->getRequest()->query->get('ui_locale');
        if (\is_string($tag) && '' !== $tag) {
            $event->getRequest()->attributes->set(UiLocale::ATTRIBUTE, new UiLocale(Locale::fromString($tag)));
        }
    }
}
