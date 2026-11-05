<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Application;

/** The result of installing official plugins, stated so it can be read out in words. */
final readonly class PluginInstallReport
{
    /** @param list<OfficialPlugin> $plugins */
    public function __construct(
        public array $plugins,
        public bool $installed,
        public ?string $reason,
        public ?string $migrationProblem = null,
    ) {
    }

    /** The command that tries again later. */
    public function retryCommand(): string
    {
        return 'php bin/console sightpress:plugin:install '.implode(' ', array_map(static fn (OfficialPlugin $p): string => $p->id, $this->plugins));
    }
}
