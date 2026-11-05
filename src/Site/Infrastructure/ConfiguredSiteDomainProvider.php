<?php

declare(strict_types=1);

namespace SightPress\Core\Site\Infrastructure;

use SightPress\Core\Site\Domain\Hostname;
use SightPress\Core\Site\Domain\SiteDomain;
use SightPress\Core\Site\Domain\SiteDomainProvider;
use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;

/** Stub provider: sites and hosts come from the `sightpress_core.sites` configuration. */
final class ConfiguredSiteDomainProvider implements SiteDomainProvider
{
    /** @var array<string, SiteDomain> */
    private array $byHost = [];

    /** @var array<string, true> */
    private array $devHosts = [];

    /**
     * @param list<array{id: string, hosts: list<string>, default_locale: string, locales: list<string>}> $sites
     * @param list<string>                                                                                $devHosts accepted only in debug mode
     */
    public function __construct(array $sites, array $devHosts, bool $debug)
    {
        foreach ($sites as $site) {
            $default = Locale::fromString($site['default_locale']);
            $locales = array_map(Locale::fromString(...), $site['locales']);
            $siteId = SiteId::fromString($site['id']);
            foreach ($site['hosts'] as $host) {
                $normalized = Hostname::normalize($host);
                if (null === $normalized) {
                    throw new \InvalidArgumentException(\sprintf('"%s" is not a valid host for site %s.', $host, $site['id']));
                }
                $this->byHost[$normalized] = new SiteDomain($siteId, $normalized, $default, [] === $locales ? [$default] : $locales);
            }
        }

        if ($debug) {
            foreach ($devHosts as $host) {
                $normalized = Hostname::normalize($host);
                if (null !== $normalized) {
                    $this->devHosts[$normalized] = true;
                }
            }
        }
    }

    public function isKnownHost(string $host): bool
    {
        return isset($this->byHost[$host]) || isset($this->devHosts[$host]);
    }

    public function findByHost(string $host): ?SiteDomain
    {
        return $this->byHost[$host] ?? null;
    }
}
