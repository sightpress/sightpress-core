<?php

declare(strict_types=1);

namespace SightPress\Core\Site\Domain;

use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;

/** A hostname that belongs to a site, with the site's locales. */
final readonly class SiteDomain
{
    /** @param list<Locale> $locales */
    public function __construct(
        public SiteId $siteId,
        public string $host,
        public Locale $defaultLocale,
        public array $locales,
    ) {
    }
}
