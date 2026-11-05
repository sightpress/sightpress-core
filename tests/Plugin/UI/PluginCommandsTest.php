<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Plugin\UI;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Plugin\Application\InstalledPlugin;
use SightPress\Core\Plugin\Application\InstallOfficialPlugins;
use SightPress\Core\Plugin\Application\ListInstalledPlugins;
use SightPress\Core\Plugin\Application\OfficialPlugin;
use SightPress\Core\Plugin\Application\OfficialPluginCatalogue;
use SightPress\Core\Plugin\UI\Console\PluginInstallCommand;
use SightPress\Core\Plugin\UI\Console\PluginListCommand;
use SightPress\Core\Shared\Process\CommandResult;
use SightPress\Core\Tests\Support\CoreTranslator;
use SightPress\Core\Tests\Support\FakeCommandRunner;
use SightPress\Core\Tests\Support\FakeInstalledPluginSource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PluginCommandsTest extends TestCase
{
    /** @param list<InstalledPlugin> $installed */
    private function list(array $installed, ?OfficialPluginCatalogue $catalogue = null): CommandTester
    {
        $tester = new CommandTester(new PluginListCommand(new ListInstalledPlugins(new FakeInstalledPluginSource($installed)), $catalogue ?? new OfficialPluginCatalogue(), CoreTranslator::create()));
        $tester->execute([]);

        return $tester;
    }

    public function testWithNoPluginTheListSaysSoAndOffersEveryOfficialOne(): void
    {
        $tester = $this->list([]);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Installed plugins: none. SightPress works fully with no plugin installed.', $display);
        self::assertStringContainsString('Official plugins not installed (8):', $display);
        self::assertStringContainsString('2. Community helper (community-helper), not installed.', $display);
        self::assertStringContainsString('   Install it with: php bin/console sightpress:plugin:install notifications-helper', $display);
    }

    public function testInstalledPluginsAreListedWithVersionAndPackage(): void
    {
        $display = $this->list([
            new InstalledPlugin('articles', 'sightpress/plugin-articles', '1.2.0', 'ignored for official plugins'),
            new InstalledPlugin('acme.events', 'acme/plugin-events', '0.3.0', 'Events and tickets.'),
            new InstalledPlugin('acme.quiet', 'acme/plugin-quiet', '0.1.0', ''),
        ])->getDisplay();

        self::assertStringContainsString('Installed plugins (3):', $display);
        self::assertStringContainsString('1. acme.events (acme.events), version 0.3.0, package acme/plugin-events.', $display);
        self::assertStringContainsString('   Events and tickets.', $display);
        self::assertStringContainsString('   Not an official plugin: it was added with Composer.', $display);
        self::assertStringContainsString('3. Articles (articles), version 1.2.0, package sightpress/plugin-articles.', $display);
        self::assertStringContainsString('Official plugins not installed (7):', $display);
    }

    public function testEveryOfficialPluginInstalled(): void
    {
        $catalogue = new OfficialPluginCatalogue([new OfficialPlugin('articles', 'sightpress/plugin-articles', '^1.0')]);
        $display = $this->list([new InstalledPlugin('articles', 'sightpress/plugin-articles', '1.0.0', '')], $catalogue)->getDisplay();

        self::assertStringContainsString('Every official plugin is installed.', $display);
    }

    /** @param array<string, mixed> $input */
    private function install(FakeCommandRunner $runner, array $input, ?OfficialPluginCatalogue $catalogue = null): CommandTester
    {
        $catalogue ??= new OfficialPluginCatalogue();
        $tester = new CommandTester(new PluginInstallCommand($catalogue, new InstallOfficialPlugins($runner, 'composer.phar'), CoreTranslator::create()));
        $tester->execute($input);

        return $tester;
    }

    public function testInstallingRunsComposerAndMigrationsAndNamesTheSetupCommand(): void
    {
        $runner = new FakeCommandRunner(['require' => new CommandResult(0, "Composer says hello\n")]);
        $tester = $this->install($runner, ['ids' => ['notifications-helper', ' articles ', 'articles', '']]);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Installing with Composer: Articles, Notifications helper.', $display);
        self::assertStringContainsString('Composer says hello', $display);
        self::assertStringContainsString('Articles: Installed.', $display);
        self::assertStringContainsString('Next step for Notifications helper: set up its install-wide settings with php bin/console sightpress:notifications-helper:setup', $display);
        self::assertStringContainsString('Installed plugins are off on every site until a site admin enables them.', $display);
        self::assertSame(['require', 'doctrine:migrations:migrate'], [$runner->ran[0][2], $runner->ran[1][2]]);
    }

    public function testNoMigrateSkipsTheMigrations(): void
    {
        $runner = new FakeCommandRunner();
        $this->install($runner, ['ids' => ['articles'], '--no-migrate' => true])->assertCommandIsSuccessful();

        self::assertCount(1, $runner->ran);
    }

    public function testAnUnknownIdIsRefusedBeforeComposerRuns(): void
    {
        $runner = new FakeCommandRunner();
        $tester = $this->install($runner, ['ids' => ['forms']]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Error: "forms" is not an official plugin. The official plugins are:', $tester->getDisplay());
        self::assertSame([], $runner->ran);
    }

    public function testAFailedInstallSaysWhyAndHowToRetry(): void
    {
        $tester = $this->install(new FakeCommandRunner(['require' => new CommandResult(1, 'Your requirements could not be resolved.')]), ['ids' => ['articles']]);
        $display = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Articles: Failed.', $display);
        self::assertStringContainsString('Reason: Your requirements could not be resolved.', $display);
        self::assertStringContainsString('To try again, run: php bin/console sightpress:plugin:install articles', $display);
    }

    public function testFailedMigrationsSayHowToRunThemAgain(): void
    {
        $tester = $this->install(new FakeCommandRunner(['doctrine:migrations:migrate' => new CommandResult(1, 'connection refused')]), ['ids' => ['articles']]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('To run them again: php bin/console doctrine:migrations:migrate', $tester->getDisplay());
    }

    public function testAddedDependenciesAreAnnounced(): void
    {
        $catalogue = new OfficialPluginCatalogue([
            new OfficialPlugin('articles', 'sightpress/plugin-articles', '^1.0'),
            new OfficialPlugin('wordpress-import', 'sightpress/plugin-wordpress-import', '^1.0', ['articles']),
        ]);
        $display = $this->install(new FakeCommandRunner(), ['ids' => ['wordpress-import']], $catalogue)->getDisplay();

        // Names fall back to the id when the catalogue has no translation for them.
        self::assertStringContainsString('Also installing Articles, because plugin.official.wordpress-import.name needs it.', $display);
    }
}
