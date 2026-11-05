<?php

declare(strict_types=1);

namespace SightPress\Core\Localization\UI\Twig;

use SightPress\Core\Localization\Application\UiLocale;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** `sightpress_ui_locale()` gives templates the interface's lang, dir and fallback lang. */
final class UiLocaleExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('sightpress_ui_locale', $this->current(...))];
    }

    /** @return array{lang: string, dir: string, fallback_lang: string|null} */
    public function current(): array
    {
        $locale = $this->requestStack->getMainRequest()?->attributes->get(UiLocale::ATTRIBUTE);
        $locale = $locale instanceof UiLocale ? $locale : UiLocale::source();

        return ['lang' => $locale->lang(), 'dir' => $locale->dir(), 'fallback_lang' => $locale->fallbackLang()];
    }
}
