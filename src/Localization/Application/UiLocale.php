<?php

declare(strict_types=1);

namespace SightPress\Core\Localization\Application;

use SightPress\Plugin\Core\Value\Locale;

/**
 * The locale the admin interface is shown in. A launch locale has its own
 * catalogue. Any other (such as the he-IL and ar-SA RTL test locales) keeps
 * its lang and dir on the page, while the strings fall back to en-GB and the
 * element holding them says so with lang="en-GB" (PLAN §3.1).
 */
final readonly class UiLocale
{
    public const string ATTRIBUTE = '_sightpress_ui_locale';
    public const string SOURCE = 'en-GB';

    public function __construct(public Locale $locale)
    {
    }

    public static function source(): self
    {
        return new self(Locale::fromString(self::SOURCE));
    }

    public function lang(): string
    {
        return $this->locale->toString();
    }

    public function dir(): string
    {
        return $this->locale->direction();
    }

    /** The translator locale: the catalogue suffix (en, pt_BR, …). */
    public function translatorLocale(): string
    {
        return $this->locale->catalogue();
    }

    /** The lang of the interface strings when they fall back to the source, or null when they are in this locale. */
    public function fallbackLang(): ?string
    {
        return $this->locale->isLaunchLocale() ? null : self::SOURCE;
    }

    /**
     * Negotiates from Accept-Language among the launch locales: an exact
     * match first, then the same language in any region, then en-GB.
     *
     * @param list<string> $accepted language tags in order of preference
     */
    public static function negotiate(array $accepted): self
    {
        foreach ($accepted as $tag) {
            $tag = str_replace('_', '-', trim($tag));
            foreach (Locale::LAUNCH as $code) {
                if (0 === strcasecmp($code, $tag)) {
                    return new self(Locale::fromString($code));
                }
            }
            $language = strtolower(explode('-', $tag)[0]);
            foreach (Locale::LAUNCH as $code) {
                if (str_starts_with($code, $language.'-')) {
                    return new self(Locale::fromString($code));
                }
            }
        }

        return self::source();
    }
}
