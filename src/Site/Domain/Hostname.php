<?php

declare(strict_types=1);

namespace SightPress\Core\Site\Domain;

/** Normalises a Host header value so hosts compare reliably. */
final class Hostname
{
    /** @return non-empty-string|null null when the value can't be a valid host */
    public static function normalize(?string $host): ?string
    {
        if (null === $host) {
            return null;
        }

        $host = strtolower(trim($host));
        // Strip the port: "example.org:8443" and "[::1]:8000".
        if (1 === preg_match('/^(\[[0-9a-f:.]+\]|[^:\[\]]+)(?::\d{1,5})?$/', $host, $matches)) {
            $host = $matches[1];
        } else {
            return null;
        }
        $host = rtrim($host, '.');

        if (1 !== preg_match('/^(?:\[[0-9a-f:.]+\]|[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)$/', $host)) {
            return null;
        }

        return $host;
    }
}
