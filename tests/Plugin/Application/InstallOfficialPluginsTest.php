<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Plugin\Application;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Plugin\Application\InstallOfficialPlugins;
use SightPress\Core\Plugin\Application\OfficialPluginCatalogue;
use SightPress\Core\Plugin\Application\PluginInstallReport;
use SightPress\Core\Shared\Process\CommandResult;
use SightPress\Core\Tests\Support\FakeCommandRunner;

final class InstallOfficialPluginsTest extends TestCase
{
    private string $output = '';

    /** @param list<string> $ids */
    private function install(FakeCommandRunner $runner, array $ids, bool $migrate = true, ?string $composer = '/usr/local/bin/composer.phar'): PluginInstallReport
    {
        $selection = new OfficialPluginCatalogue()->select($ids);

        return new InstallOfficialPlugins($runner, $composer)($selection, function (string $buffer): void {
            $this->output .= $buffer;
        }, $migrate);
    }

    public function testNothingRunsForAnEmptySelection(): void
    {
        $runner = new FakeCommandRunner();
        $report = $this->install($runner, []);

        self::assertTrue($report->installed);
        self::assertSame([], $runner->ran);
    }

    public function testOneComposerRequireThenTheMigrations(): void
    {
        $runner = new FakeCommandRunner(['require' => new CommandResult(0, "Package operations: 2 installs\n")]);
        $report = $this->install($runner, ['form-builder', 'articles']);

        self::assertTrue($report->installed);
        self::assertNull($report->migrationProblem);
        self::assertSame([\PHP_BINARY, '/usr/local/bin/composer.phar', 'require', '--no-interaction', '--no-ansi', 'sightpress/plugin-articles:^0.1 || ^1.0', 'sightpress/plugin-form-builder:^0.1 || ^1.0'], $runner->ran[0]);
        self::assertSame('doctrine:migrations:migrate', $runner->ran[1][2]);
        self::assertStringContainsString('Package operations', $this->output);
    }

    public function testMigrationsCanBeLeftForLater(): void
    {
        $runner = new FakeCommandRunner();
        $this->install($runner, ['articles'], false);

        self::assertCount(1, $runner->ran);
    }

    public function testAComposerFailureIsReportedWithItsLastLine(): void
    {
        $report = $this->install(new FakeCommandRunner(['require' => new CommandResult(2, "Loading\n  Could not find package sightpress/plugin-articles.\n\n")]), ['articles']);

        self::assertFalse($report->installed);
        self::assertSame('Could not find package sightpress/plugin-articles.', $report->reason);
    }

    public function testASilentComposerFailureIsReportedWithItsExitCode(): void
    {
        $report = $this->install(new FakeCommandRunner(['require' => new CommandResult(137)]), ['articles']);

        self::assertSame('Composer stopped with exit code 137.', $report->reason);
    }

    public function testFailedMigrationsAreReportedButThePackagesStayInstalled(): void
    {
        $report = $this->install(new FakeCommandRunner(['doctrine:migrations:migrate' => new CommandResult(1, 'SQLSTATE[08006] connection refused')]), ['articles']);

        self::assertTrue($report->installed);
        self::assertSame('The plugins were installed, but their migrations failed: SQLSTATE[08006] connection refused', $report->migrationProblem);
    }

    public function testComposerFromThePathOrFromTheHook(): void
    {
        $runner = new FakeCommandRunner();
        $previous = getenv('COMPOSER_BINARY');
        putenv('COMPOSER_BINARY');
        try {
            $this->install($runner, ['articles'], false, null);
            self::assertSame('composer', $runner->ran[0][0]);

            putenv('COMPOSER_BINARY=/opt/composer');
            $this->install($runner, ['articles'], false, null);
            self::assertSame([\PHP_BINARY, '/opt/composer'], \array_slice($runner->ran[1], 0, 2));
        } finally {
            putenv(false === $previous ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY='.$previous);
        }
    }
}
