<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Support;

use SightPress\Core\Plugin\Application\InstalledPlugin;
use SightPress\Core\Plugin\Application\InstalledPluginSource;

final class FakeInstalledPluginSource implements InstalledPluginSource
{
    /** @param list<InstalledPlugin> $plugins */
    public function __construct(public array $plugins = [])
    {
    }

    public function all(): array
    {
        return $this->plugins;
    }
}
