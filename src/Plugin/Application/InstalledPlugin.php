<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Application;

/** A plugin installed with Composer. */
final readonly class InstalledPlugin
{
    public function __construct(
        public string $id,
        public string $package,
        public string $version,
        public string $description,
    ) {
    }
}
