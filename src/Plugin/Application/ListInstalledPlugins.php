<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Application;

/** Lists the plugins available on this install. The core never requires one, so the list can be empty. */
final readonly class ListInstalledPlugins
{
    public function __construct(private InstalledPluginSource $source)
    {
    }

    /** @return list<InstalledPlugin> */
    public function __invoke(): array
    {
        $plugins = $this->source->all();
        usort($plugins, static fn (InstalledPlugin $a, InstalledPlugin $b): int => $a->id <=> $b->id);

        return $plugins;
    }
}
