<?php

declare(strict_types=1);

namespace SightPress\Core\Site\Infrastructure;

use SightPress\Core\Site\Domain\SiteDomain;
use SightPress\Plugin\Core\Port\SiteContext;
use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;
use Symfony\Component\HttpFoundation\RequestStack;

/** The request-scoped current site, read from the main request so the service stays stateless. */
final readonly class RequestSiteContext implements SiteContext
{
    public const string ATTRIBUTE = '_sightpress_site';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function siteId(): SiteId
    {
        return $this->current()->siteId;
    }

    public function defaultLocale(): Locale
    {
        return $this->current()->defaultLocale;
    }

    public function locales(): array
    {
        return $this->current()->locales;
    }

    public function hasSite(): bool
    {
        return $this->requestStack->getMainRequest()?->attributes->get(self::ATTRIBUTE) instanceof SiteDomain;
    }

    private function current(): SiteDomain
    {
        $site = $this->requestStack->getMainRequest()?->attributes->get(self::ATTRIBUTE);
        if (!$site instanceof SiteDomain) {
            throw new \LogicException('There is no current site: this request was not matched to a site host.');
        }

        return $site;
    }
}
