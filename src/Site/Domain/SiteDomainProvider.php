<?php

declare(strict_types=1);

namespace SightPress\Core\Site\Domain;

/**
 * Where the request's host is mapped to a site. The real implementation reads
 * SiteDomain rows (M1); until then a configured stub answers.
 */
interface SiteDomainProvider
{
    /** Whether the host may be served at all. Unknown hosts get a 404 before any site code runs. */
    public function isKnownHost(string $host): bool;

    /** The site the host belongs to, or null for a known host that no site owns (a development host). */
    public function findByHost(string $host): ?SiteDomain;
}
