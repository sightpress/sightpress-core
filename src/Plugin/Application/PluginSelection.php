<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Application;

/** Official plugins chosen for install, with the ones added because a chosen plugin needs them. */
final readonly class PluginSelection
{
    /**
     * @param list<OfficialPlugin>  $plugins
     * @param array<string, string> $addedFor id of an added plugin => id of the plugin that needs it
     */
    public function __construct(
        public array $plugins,
        public array $addedFor = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->plugins;
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_map(static fn (OfficialPlugin $p): string => $p->id, $this->plugins);
    }
}
