<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Application;

use SightPress\Core\Shared\Process\CommandRunner;

/**
 * Installs official plugins with one `composer require` in a child process,
 * then (unless told not to) runs every migration in another one, so the new
 * bundles are loaded. The installer and sightpress:plugin:install share it.
 */
final readonly class InstallOfficialPlugins
{
    public function __construct(
        private CommandRunner $runner,
        private ?string $composerBinary = null,
    ) {
    }

    /** @param callable(string): void $output receives Composer's output as plain text */
    public function __invoke(PluginSelection $selection, callable $output, bool $migrate = true): PluginInstallReport
    {
        if ($selection->isEmpty()) {
            return new PluginInstallReport([], true, null);
        }

        $requirements = array_map(static fn (OfficialPlugin $p): string => $p->requirement(), $selection->plugins);
        $result = $this->runner->run([...$this->composer(), 'require', '--no-interaction', '--no-ansi', ...$requirements], $output);
        if (!$result->isSuccessful()) {
            $reason = '' === $result->lastLine() ? \sprintf('Composer stopped with exit code %d.', $result->exitCode) : $result->lastLine();

            return new PluginInstallReport($selection->plugins, false, $reason);
        }

        if ($migrate) {
            $migrations = $this->runner->run([\PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration', '--no-ansi'], $output);
            if (!$migrations->isSuccessful()) {
                return new PluginInstallReport($selection->plugins, true, null, 'The plugins were installed, but their migrations failed: '.$migrations->lastLine());
            }
        }

        return new PluginInstallReport($selection->plugins, true, null);
    }

    /** @return list<string> */
    private function composer(): array
    {
        // Composer sets COMPOSER_BINARY for the scripts it runs, such as the installer hook.
        $binary = $this->composerBinary ?? (getenv('COMPOSER_BINARY') ?: null);

        return null === $binary ? ['composer'] : [\PHP_BINARY, $binary];
    }
}
