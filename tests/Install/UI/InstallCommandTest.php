<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Install\UI;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Install\Domain\DatabaseCheck;
use SightPress\Core\Install\Domain\InstallState;
use SightPress\Core\Install\Domain\InstallStep;
use SightPress\Core\Install\UI\Console\InstallCommand;
use SightPress\Core\Media\Application\ConfigureMediaStorageHandler;
use SightPress\Core\Plugin\Application\InstalledPlugin;
use SightPress\Core\Plugin\Application\InstallOfficialPlugins;
use SightPress\Core\Plugin\Application\ListInstalledPlugins;
use SightPress\Core\Plugin\Application\OfficialPluginCatalogue;
use SightPress\Core\Shared\Process\CommandResult;
use SightPress\Core\Tests\Support\CoreTranslator;
use SightPress\Core\Tests\Support\FakeCommandRunner;
use SightPress\Core\Tests\Support\FakeConfigWriter;
use SightPress\Core\Tests\Support\FakeDatabaseProbe;
use SightPress\Core\Tests\Support\FakeInstalledPluginSource;
use SightPress\Core\Tests\Support\FakeRequirementProbe;
use SightPress\Core\Tests\Support\InMemoryInstallStateStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * sightpress:install (PLAN §3.4, M0 step 3f): interactive, unattended, under
 * Composer, already installed, resumed after a failure, and never printing a secret.
 */
final class InstallCommandTest extends TestCase
{
    private const string DB_PASSWORD = 'db-Secret-4815';
    private const string MAILER = 'smtp://mailer:mail-Secret-1623@mail.example.org:587';
    private const string APP_SECRET = 'app-Secret-42';

    private string $project;
    private InMemoryInstallStateStore $store;
    private FakeRequirementProbe $requirements;
    private FakeDatabaseProbe $database;
    private FakeConfigWriter $config;
    private FakeCommandRunner $runner;
    private FakeInstalledPluginSource $plugins;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/sightpress-installer-'.bin2hex(random_bytes(6));
        mkdir($this->project.'/public', 0o777, true);
        $this->store = new InMemoryInstallStateStore();
        $this->requirements = new FakeRequirementProbe();
        $this->database = new FakeDatabaseProbe();
        $this->config = new FakeConfigWriter();
        $this->runner = new FakeCommandRunner();
        $this->plugins = new FakeInstalledPluginSource();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->project);
    }

    /**
     * Every answer an unattended install needs.
     *
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    private function unattendedEnvironment(array $extra = []): array
    {
        return [
            'SIGHTPRESS_DB_PASSWORD' => self::DB_PASSWORD,
            'SIGHTPRESS_MAILER_DSN' => self::MAILER,
            'SIGHTPRESS_MAIL_FROM' => 'no-reply@example.org',
            'SIGHTPRESS_APP_SECRET' => self::APP_SECRET,
            ...$extra,
        ];
    }

    /**
     * @param array<string, string> $environment
     * @param array<string, mixed>  $input
     * @param list<string>          $answers     typed answers; given answers make the run interactive
     */
    private function install(array $environment = [], array $input = [], array $answers = [], ?bool $interactive = null): CommandTester
    {
        $command = new InstallCommand(
            $this->store,
            $this->requirements,
            $this->database,
            $this->config,
            new ConfigureMediaStorageHandler($this->project),
            new OfficialPluginCatalogue(),
            new InstallOfficialPlugins($this->runner, 'composer.phar'),
            new ListInstalledPlugins($this->plugins),
            $this->runner,
            CoreTranslator::create(),
            $this->project,
            $environment,
        );
        $tester = new CommandTester($command);
        $tester->setInputs($answers);
        $tester->execute($input, ['interactive' => $interactive ?? [] !== $answers, 'decorated' => true]);

        return $tester;
    }

    private static function assertNoSecretIn(string $display): void
    {
        foreach ([self::DB_PASSWORD, self::MAILER, 'mail-Secret-1623', self::APP_SECRET] as $secret) {
            self::assertStringNotContainsString($secret, $display);
        }
    }

    public function testComposerCanSkipTheInstaller(): void
    {
        $tester = $this->install(['SIGHTPRESS_SKIP_INSTALL' => '1'], ['--from-composer' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertSame("SightPress installer skipped, because SIGHTPRESS_SKIP_INSTALL is 1. When you are ready, run: php bin/console sightpress:install\n", $tester->getDisplay());
    }

    public function testUnderComposerWithoutATerminalItExitsZeroWithTheNextSteps(): void
    {
        $tester = $this->install(['COMPOSER_NO_INTERACTION' => '1'], ['--from-composer' => true], interactive: true);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('SightPress isn\'t set up yet. There is no terminal to ask questions in, so the installer didn\'t run.', $display);
        self::assertStringContainsString('To finish the install, run: php bin/console sightpress:install', $display);
        self::assertStringContainsString('Official plugins you can install later (8):', $display);
        self::assertStringContainsString('2. Community helper: ', $display);
        self::assertStringContainsString('Install it with: php bin/console sightpress:plugin:install community-helper', $display);
        self::assertSame([], $this->runner->ran, 'Nothing runs, nothing is written.');
        self::assertEquals(new InstallState(), $this->store->state);
    }

    public function testInCiItIsNeverInteractive(): void
    {
        $this->install(['CI' => 'true'], ['--from-composer' => true], ['should not be read'])->assertCommandIsSuccessful();

        self::assertEquals(new InstallState(), $this->store->state);
    }

    public function testAnUnattendedInstallRunsEveryStepFromVariablesAndOptions(): void
    {
        $tester = $this->install($this->unattendedEnvironment(['SIGHTPRESS_DB_HOST' => 'db.internal']), ['--app-env' => 'dev', '--db-name' => 'site_db']);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        foreach (InstallStep::cases() as $index => $step) {
            self::assertStringContainsString(\sprintf('Step %d of 7: %s', $index + 1, $step->title()), $display);
        }
        self::assertStringContainsString('Running unattended, with the answers from options and SIGHTPRESS_* variables.', $display);
        self::assertStringContainsString('Passed: PHP 8.4.3 (8.4 or later is needed).', $display);
        self::assertStringContainsString('Optional tool ffmpeg: found.', $display);
        self::assertStringContainsString('Optional tool pandoc: not found. Without it, SightPress works, but without importing DOCX files with the file-importer plugin.', $display);
        self::assertStringContainsString('Application secret: taken from SIGHTPRESS_APP_SECRET.', $display);
        self::assertStringContainsString('Passed: Connected to the database site_db on db.internal port 5432 as sightpress.', $display);
        self::assertStringContainsString('Passed: Settings saved to .env.local. Secrets are never printed or logged.', $display);
        self::assertStringContainsString('No plugins will be installed.', $display);
        self::assertStringContainsString('Passed: Media files are kept in '.$this->project.'/var/storage/media (created now), outside the public folder.', $display);
        self::assertStringContainsString('Passed: The database is up to date.', $display);
        self::assertStringContainsString('No plugins are installed, so there is nothing to make available.', $display);
        self::assertStringContainsString('Part 1 is done: the system is set up.', $display);
        self::assertStringNotContainsString("\e[", $display, 'Output is plain text: no colour codes.');
        self::assertNoSecretIn($display);

        self::assertSame(['APP_SECRET' => self::APP_SECRET, 'DATABASE_URL' => 'postgresql://sightpress:'.self::DB_PASSWORD.'@db.internal:5432/site_db?serverVersion=17&charset=utf8', 'MAILER_DSN' => self::MAILER], $this->config->secrets);
        self::assertSame(['APP_ENV' => 'dev', 'SIGHTPRESS_MAIL_FROM' => 'no-reply@example.org', 'MESSENGER_TRANSPORT_DSN' => 'doctrine://default?auto_setup=0'], $this->config->values);
        self::assertFalse($this->config->usedVault);
        self::assertNotNull($this->store->state->installedAt);
        self::assertTrue($this->store->state->hasCompletedAll(InstallStep::requiredKeys()));
        self::assertSame('none', $this->store->state->answers['plugins']);
        foreach ($this->store->state->answers as $answer) {
            self::assertNoSecretIn($answer);
        }
    }

    public function testProductionSealsSecretsAndSaysWhatToDoNext(): void
    {
        $tester = $this->install($this->unattendedEnvironment(['SIGHTPRESS_MESSENGER_TRANSPORT' => 'sync']));
        $display = $tester->getDisplay();

        self::assertTrue($this->config->usedVault);
        self::assertSame('sync://', $this->config->values['MESSENGER_TRANSPORT_DSN']);
        self::assertStringContainsString('Settings saved to config/secrets/prod and .env.local.', $display);
        self::assertStringContainsString('1. Run composer dump-env prod, so the settings load faster.', $display);
        self::assertStringContainsString('2. Keep config/secrets/prod/prod.decrypt.private.php secret', $display);
    }

    public function testSecretsCanComeFromFiles(): void
    {
        file_put_contents($this->project.'/db-password', self::DB_PASSWORD."\n");
        $environment = $this->unattendedEnvironment();
        unset($environment['SIGHTPRESS_DB_PASSWORD']);

        $this->install([...$environment, 'SIGHTPRESS_DB_PASSWORD_FILE' => $this->project.'/db-password'])->assertCommandIsSuccessful();

        self::assertStringContainsString(':'.self::DB_PASSWORD.'@', $this->config->secrets['DATABASE_URL']);
    }

    public function testAnUnreadableSecretFileIsNamed(): void
    {
        $environment = $this->unattendedEnvironment(['SIGHTPRESS_DB_PASSWORD_FILE' => '/run/secrets/missing']);
        unset($environment['SIGHTPRESS_DB_PASSWORD']);
        $tester = $this->install($environment);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Failed: Database password: SIGHTPRESS_DB_PASSWORD_FILE points to /run/secrets/missing, which can\'t be read.', $tester->getDisplay());

        $this->install($environment, ['--from-composer' => true])->assertCommandIsSuccessful();
    }

    public function testAnUnreadableSecretFileIsNamedWhenItIsTheStepThatReadsIt(): void
    {
        $this->config = new FakeConfigWriter([], ['DATABASE_URL' => 'postgresql://sp:kept@db:5432/sp']);
        $environment = $this->unattendedEnvironment(['SIGHTPRESS_DB_PASSWORD_FILE' => $this->project]);
        unset($environment['SIGHTPRESS_DB_PASSWORD']);

        self::assertStringContainsString('Failed: Database password: SIGHTPRESS_DB_PASSWORD_FILE points to '.$this->project.', which can\'t be read.', $this->install($environment)->getDisplay());
    }

    public function testAnUnattendedInstallListsEveryMissingAnswer(): void
    {
        $tester = $this->install(['SIGHTPRESS_DB_HOST' => 'db']);
        $display = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('The install can\'t run unattended, because these values are missing:', $display);
        self::assertStringContainsString('- Database password: set SIGHTPRESS_DB_PASSWORD, or SIGHTPRESS_DB_PASSWORD_FILE to the path of a file that holds it.', $display);
        self::assertStringContainsString('- Mailer DSN: set SIGHTPRESS_MAILER_DSN', $display);
        self::assertStringContainsString('- Default sender address: give --mail-from or set SIGHTPRESS_MAIL_FROM.', $display);
    }

    public function testUnderComposerAMissingAnswerNeverFailsTheHook(): void
    {
        $this->install(['SIGHTPRESS_DB_HOST' => 'db'], ['--from-composer' => true])->assertCommandIsSuccessful();
    }

    public function testAnInvalidSuppliedValueIsNamed(): void
    {
        $tester = $this->install($this->unattendedEnvironment(['SIGHTPRESS_DB_PORT' => 'eighty']));

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Failed: Database port: "eighty" is not a port number.', $tester->getDisplay());
        self::assertSame('environment', $this->store->state->failedStep);
    }

    public function testAnInvalidChoiceIsNamed(): void
    {
        $tester = $this->install($this->unattendedEnvironment(['SIGHTPRESS_APP_ENV' => 'staging']));

        self::assertStringContainsString('Failed: Application environment: "staging" is not one of the choices. Enter one of: prod, dev.', $tester->getDisplay());
    }

    public function testFailedRequirementsStopTheInstallAndAResumedRunCarriesOn(): void
    {
        $this->requirements = new FakeRequirementProbe('8.3.9', ['intl', 'pdo_pgsql', 'gd', 'imagick'], ['/var', 'var/storage/media']);
        $tester = $this->install($this->unattendedEnvironment());
        $display = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Failed: PHP 8.3.9 is too old. Install PHP 8.4 or later.', $display);
        self::assertStringContainsString('Failed: PHP extension pdo_pgsql is not loaded. Install or enable it, for example with the php8.4-pgsql package.', $display);
        self::assertStringContainsString('Failed: Neither the gd nor the imagick PHP extension is loaded.', $display);
        self::assertStringContainsString('Failed: var/ is not writable.', $display);
        self::assertStringContainsString('Failed: The media folder var/storage/media is not writable.', $display);
        self::assertStringContainsString('Failed: 6 requirements are not met, listed above.', $display);
        self::assertStringContainsString('The install stopped at step 1. Fix the problem, then run php bin/console sightpress:install again: it carries on from this step.', $display);
        self::assertSame('requirements', $this->store->state->failedStep);

        $this->requirements = new FakeRequirementProbe();
        $this->runner->willReturn('doctrine:migrations:migrate', new CommandResult(1, "Migrating\nSQLSTATE[08006] could not connect\n"));
        $display = $this->install($this->unattendedEnvironment())->getDisplay();
        self::assertStringContainsString('Failed: Database migrations: SQLSTATE[08006] could not connect', $display);
        self::assertStringContainsString('The install stopped at step 5.', $display);

        $this->runner = new FakeCommandRunner();
        $tester = $this->install($this->unattendedEnvironment());
        $display = $tester->getDisplay();
        $tester->assertCommandIsSuccessful();
        self::assertSame(4, substr_count($display, 'Already done in an earlier run, so this step is skipped.'), 'Requirements, environment, plugins and media storage are not repeated.');
        self::assertStringContainsString('Passed: The database is up to date.', $display);
    }

    public function testASilentMigrationFailureGivesTheExitCode(): void
    {
        $this->runner->willReturn('doctrine:migrations:migrate', new CommandResult(9));

        self::assertStringContainsString('Failed: Database migrations: they stopped with exit code 9.', $this->install($this->unattendedEnvironment())->getDisplay());
    }

    public function testOneMissingRequirementIsSaidInTheSingular(): void
    {
        $this->requirements = new FakeRequirementProbe(missingExtensions: ['sodium']);

        self::assertStringContainsString('Failed: 1 requirement is not met, listed above.', $this->install($this->unattendedEnvironment())->getDisplay());
    }

    public function testUnderComposerAnInstalledSiteIsLeftAlone(): void
    {
        $this->store->state = new InstallState(InstallStep::requiredKeys(), [], null, null, '2026-11-01T09:00:00+00:00');
        $tester = $this->install([], ['--from-composer' => true]);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('SightPress is already installed, so the installer has nothing to do.', $display);
        self::assertStringContainsString('Database migrations: up to date.', $display);
        self::assertStringContainsString('To run the setup again, run: php bin/console sightpress:install', $display);
        self::assertSame('doctrine:migrations:up-to-date', $this->runner->ran[0][2]);

        $this->runner->willReturn('doctrine:migrations:up-to-date', new CommandResult(1));
        self::assertStringContainsString('Database migrations: some may be waiting, or the database can\'t be reached. Run: php bin/console doctrine:migrations:migrate', $this->install([], ['--from-composer' => true])->getDisplay());
    }

    public function testRunningAgainByHandRepeatsTheSetupWithTheEarlierAnswersAsDefaults(): void
    {
        $this->store->state = new InstallState(InstallStep::requiredKeys(), ['db_host' => 'old-db', 'app_env' => 'dev', 'media_path' => 'var/media'], null, null, '2026-11-01T09:00:00+00:00');
        $this->config = new FakeConfigWriter(['SIGHTPRESS_MAIL_FROM' => 'kept@example.org'], ['APP_SECRET' => 'kept-secret', 'DATABASE_URL' => 'postgresql://old:'.rawurlencode(self::DB_PASSWORD).'@old-db:5544/old_db', 'MAILER_DSN' => self::MAILER]);
        $tester = $this->install();
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('SightPress is already installed. The setup runs again, and your earlier answers are the defaults.', $display);
        self::assertStringContainsString('Application secret: kept from the earlier setup.', $display);
        self::assertStringContainsString('Connected to the database old_db on old-db port 5544 as old.', $display);
        self::assertSame('kept-secret', $this->config->secrets['APP_SECRET']);
        self::assertSame('dev', $this->config->values['APP_ENV']);
        self::assertStringContainsString($this->project.'/var/media', $display);
        self::assertNoSecretIn($display);
    }

    public function testAMissingDatabaseIsCreatedAndACreateFailureIsNamed(): void
    {
        $this->database = new FakeDatabaseProbe([new DatabaseCheck(true, false)]);
        self::assertStringContainsString('Passed: Database "sightpress" created.', $this->install($this->unattendedEnvironment())->getDisplay());

        $this->store = new InMemoryInstallStateStore();
        $this->database = new FakeDatabaseProbe([new DatabaseCheck(true, false)], 'permission denied to create database');
        self::assertStringContainsString('Failed: Database: "sightpress" could not be created. The server said: permission denied to create database', $this->install($this->unattendedEnvironment())->getDisplay());
    }

    public function testAnUnreachableDatabaseIsNamedWithoutThePassword(): void
    {
        $this->database = new FakeDatabaseProbe([new DatabaseCheck(false, false, 'Connection refused')]);
        $display = $this->install($this->unattendedEnvironment())->getDisplay();

        self::assertStringContainsString('Failed: Database: SightPress could not connect to sightpress on 127.0.0.1 port 5432 as sightpress. The server said: Connection refused', $display);
        self::assertNoSecretIn($display);

        $this->database = new FakeDatabaseProbe([new DatabaseCheck(false, false)]);
        self::assertStringContainsString('The server said: no reason given.', $this->install($this->unattendedEnvironment())->getDisplay());
    }

    public function testAMediaPathInsidePublicIsRefused(): void
    {
        $tester = $this->install($this->unattendedEnvironment(['SIGHTPRESS_MEDIA_PATH' => 'public/uploads']));

        self::assertStringContainsString('Failed: Media folder: The media path is inside public/', $tester->getDisplay());
        self::assertSame('media_storage', $this->store->state->failedStep);
    }

    public function testUnattendedPluginsAreInstalledAndTheInstallerRestartsToLoadThem(): void
    {
        $this->runner->attachedExitCode = 0;
        $tester = $this->install($this->unattendedEnvironment(['SIGHTPRESS_PLUGINS' => 'articles,6']), ['--from-composer' => true]);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('SightPress has official plugins that add features to the core. None is installed unless you choose it here', $display);
        self::assertStringContainsString('1. Articles: ', $display);
        self::assertStringContainsString('Not installed.', $display);
        self::assertStringContainsString('Installing with Composer: Articles, Image finder. Composer\'s output follows.', $display);
        self::assertStringContainsString('Articles: Installed.', $display);
        self::assertStringContainsString('Restarting the installer to load the new plugins.', $display);
        self::assertSame([\PHP_BINARY, 'composer.phar', 'require', '--no-interaction', '--no-ansi', 'sightpress/plugin-articles:^0.1 || ^1.0', 'sightpress/plugin-image-finder:^0.1 || ^1.0'], $this->runner->ran[0]);
        self::assertSame([\PHP_BINARY, 'bin/console', 'sightpress:install', '--resume', '--from-composer', '--no-interaction'], $this->runner->attached[0]);
        self::assertTrue($this->store->state->isCompleted('plugins'));
        self::assertSame('articles,image-finder', $this->store->state->answers['plugins']);
        self::assertStringNotContainsString('Step 4 of 7', $display, 'The restarted installer runs the rest.');
    }

    public function testTheRestartedInstallerResumesAndListsSetupCommands(): void
    {
        $this->store->state = new InstallState(['requirements', 'environment', 'plugins'], ['plugins' => 'articles,image-finder']);
        $this->plugins->plugins = [
            new InstalledPlugin('articles', 'sightpress/plugin-articles', '1.0.0', ''),
            new InstalledPlugin('image-finder', 'sightpress/plugin-image-finder', '1.0.0', ''),
        ];
        $tester = $this->install($this->unattendedEnvironment(['SIGHTPRESS_AVAILABLE_PLUGINS' => 'articles']), ['--resume' => true]);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringNotContainsString('SightPress installer.', $display, 'A resumed run doesn\'t introduce itself again.');
        self::assertStringContainsString('Installed plugins (2). Each is available to every site unless you choose a subset', $display);
        self::assertStringContainsString('2. Image finder (image-finder)', $display);
        self::assertStringContainsString('Passed: Available to sites: articles.', $display);
        self::assertStringContainsString('Set up Image finder\'s install-wide settings: php bin/console sightpress:image-finder:setup', $display);
        self::assertStringContainsString('Official plugins you can install later (6):', $display);
    }

    public function testAnUnknownAvailablePluginIsNamed(): void
    {
        $this->plugins->plugins = [new InstalledPlugin('articles', 'sightpress/plugin-articles', '1.0.0', '')];

        self::assertStringContainsString('Failed: Plugins available to sites: "forms" is not installed.', $this->install($this->unattendedEnvironment(['SIGHTPRESS_AVAILABLE_PLUGINS' => 'articles,forms']))->getDisplay());
    }

    public function testAFailedPluginInstallIsReportedAndTheInstallCarriesOn(): void
    {
        $this->runner->willReturn('require', new CommandResult(2, 'Could not find a matching version of package sightpress/plugin-articles.'));
        $tester = $this->install($this->unattendedEnvironment(['SIGHTPRESS_PLUGINS' => 'articles']));
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Articles: Failed.', $display);
        self::assertStringContainsString('Reason: Could not find a matching version of package sightpress/plugin-articles.', $display);
        self::assertStringContainsString('The install carries on without them. To try again later, run: php bin/console sightpress:plugin:install articles', $display);
        self::assertStringContainsString('Part 1 is done', $display);
        self::assertSame([], $this->runner->attached);
    }

    public function testAnUnknownPluginIsNamedWithTheValidChoices(): void
    {
        $display = $this->install($this->unattendedEnvironment(['SIGHTPRESS_PLUGINS' => 'forms,99']))->getDisplay();

        self::assertStringContainsString('Failed: Official plugins: "forms", "99" are not official plugins. The official plugins are: articles, community-helper, form-builder', $display);
        self::assertStringContainsString('Type numbers from 1 to 8, "all" or "none".', $display);
    }

    public function testAlreadyInstalledPluginsAreNotInstalledAgain(): void
    {
        $this->plugins->plugins = [new InstalledPlugin('articles', 'sightpress/plugin-articles', '1.0.0', '')];
        $display = $this->install($this->unattendedEnvironment(['SIGHTPRESS_PLUGINS' => 'articles', 'SIGHTPRESS_AVAILABLE_PLUGINS' => 'all']))->getDisplay();

        self::assertStringContainsString('1. Articles: ', $display);
        self::assertStringContainsString(' Installed.', $display);
        self::assertStringContainsString('No plugins will be installed.', $display);
        self::assertStringContainsString('Passed: Every installed plugin is available to sites.', $display);
        self::assertSame('none', $this->store->state->answers['plugins']);
    }

    public function testNoColorTurnsOffDecoration(): void
    {
        $tester = $this->install($this->unattendedEnvironment(['NO_COLOR' => '1']));

        self::assertFalse($tester->getOutput()->isDecorated());
    }

    public function testAnInteractiveInstallAsksOneQuestionPerLineAndAsksAgainAfterANamedError(): void
    {
        $tester = $this->install(answers: [
            '9',                  // application environment: not a choice
            '2',                  // dev
            '',                   // database host: default
            'eighty',             // port: not a number
            '5433',
            'sp',                 // name
            '',                   // user: default
            self::DB_PASSWORD,    // hidden
            'not a dsn',          // mailer: refused
            self::MAILER,
            'team@example.org',
            'sync',               // messenger, by value
            'all',                // every official plugin
            'no',                 // ... but don't go ahead
            '',                   // media folder: default
        ]);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Each question states its default. Press Enter to accept it.', $display);
        self::assertStringContainsString("Application environment:\n1. Production (prod)\n2. Development (dev), on your own computer only\n", $display);
        self::assertStringContainsString('Type a number from 1 to 2 (default: 1, Production (prod)): ', $display);
        self::assertStringContainsString('Error: Application environment: "9" is not one of the numbers. Type a number from 1 to 2.', $display);
        self::assertStringContainsString('Database host (default: 127.0.0.1): ', $display);
        self::assertStringContainsString('Error: Database port: "eighty" is not a port number. Enter a whole number from 1 to 65535 such as 5432.', $display);
        self::assertStringContainsString('Typing won\'t be shown, and nothing is printed as you type.', $display);
        self::assertStringContainsString('Error: Mailer DSN: That value is not a DSN.', $display);
        self::assertStringContainsString('Which official plugins do you want to install? Type their numbers separated by commas, "all", or "none" (default: none): ', $display);
        self::assertStringContainsString('These plugins will be installed: Articles, Community helper, Form builder, Newsletter manager, Notifications helper, Image finder, AI helper, File importer. Go ahead? Type yes or no (default: yes): ', $display);
        self::assertStringContainsString('Media folder (default: var/storage/media): ', $display);
        self::assertNoSecretIn($display);
        self::assertSame('dev', $this->config->values['APP_ENV']);
        self::assertSame('sync://', $this->config->values['MESSENGER_TRANSPORT_DSN']);
        self::assertStringContainsString('@127.0.0.1:5433/sp', $this->config->secrets['DATABASE_URL']);
        self::assertSame('none', $this->store->state->answers['plugins']);
        self::assertSame([], $this->runner->attached);
    }

    public function testAnInteractiveDatabaseProblemIsNamedAndAskedAgain(): void
    {
        $this->database = new FakeDatabaseProbe([new DatabaseCheck(false, false, 'Name or service not known'), new DatabaseCheck(true, false)]);
        $environment = ['SIGHTPRESS_MAILER_DSN' => self::MAILER, 'SIGHTPRESS_MAIL_FROM' => 'a@example.org', 'SIGHTPRESS_APP_ENV' => 'dev', 'SIGHTPRESS_MESSENGER_TRANSPORT' => 'doctrine', 'SIGHTPRESS_PLUGINS' => 'none', 'SIGHTPRESS_MEDIA_PATH' => 'var/media'];
        $tester = $this->install($environment, answers: [
            'typo-db', '', '', '', self::DB_PASSWORD,
            'db', '', '', '', '',      // the password keeps the value typed before
            'maybe', 'yes',            // create the database?
        ]);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Failed: Database: SightPress could not connect to sightpress on typo-db port 5432 as sightpress. The server said: Name or service not known', $display);
        self::assertStringContainsString('Check the database details and answer again. Your answers are the new defaults.', $display);
        self::assertStringContainsString('Database host (default: typo-db): ', $display);
        self::assertStringContainsString('Database password (default: keep the current value): ', $display);
        self::assertStringContainsString('Error: "maybe" is not an answer to this question. Type yes or no.', $display);
        self::assertStringContainsString('The database "sightpress" doesn\'t exist yet. Create it now?', $display);
        self::assertSame(self::DB_PASSWORD, $this->database->created[0]->password);
        self::assertNoSecretIn($display);
    }

    public function testDecliningToCreateTheDatabaseStopsWithTheReason(): void
    {
        $this->database = new FakeDatabaseProbe([new DatabaseCheck(true, false)]);
        $tester = $this->install(['SIGHTPRESS_APP_ENV' => 'dev'], answers: ['', '', '', '', self::DB_PASSWORD, 'n']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Failed: Database: "sightpress" doesn\'t exist. Create it, then run the installer again.', $tester->getDisplay());
    }

    public function testAnInteractiveCreateFailureIsAskedAgain(): void
    {
        $this->database = new FakeDatabaseProbe([new DatabaseCheck(true, false), new DatabaseCheck(true, true)], 'permission denied');
        $environment = ['SIGHTPRESS_MAILER_DSN' => self::MAILER, 'SIGHTPRESS_MAIL_FROM' => 'a@example.org', 'SIGHTPRESS_APP_ENV' => 'dev', 'SIGHTPRESS_MESSENGER_TRANSPORT' => 'doctrine', 'SIGHTPRESS_PLUGINS' => 'none', 'SIGHTPRESS_MEDIA_PATH' => 'var/media'];
        $display = $this->install($environment, answers: ['', '', '', '', self::DB_PASSWORD, 'yes', '', '', '', '', ''])->getDisplay();

        self::assertStringContainsString('Failed: Database: "sightpress" could not be created. The server said: permission denied', $display);
        self::assertStringContainsString('Passed: Connected to the database', $display);
    }

    public function testInteractivePluginNumbersAreReadBackAndInstalled(): void
    {
        $this->runner->attachedExitCode = 3;
        $environment = $this->unattendedEnvironment(['SIGHTPRESS_APP_ENV' => 'dev', 'SIGHTPRESS_MESSENGER_TRANSPORT' => 'doctrine', 'SIGHTPRESS_DB_HOST' => 'db', 'SIGHTPRESS_DB_PORT' => '5432', 'SIGHTPRESS_DB_NAME' => 'sp', 'SIGHTPRESS_DB_USER' => 'sp']);
        $tester = $this->install($environment, answers: ['12', '3, form-builder,', 'y']);
        $display = $tester->getDisplay();

        self::assertSame(3, $tester->getStatusCode(), 'The restarted installer\'s exit code is passed on.');
        self::assertStringContainsString('Error: Official plugins: "12" is not an official plugin.', $display);
        self::assertStringContainsString('These plugins will be installed: Form builder. Go ahead?', $display);
        self::assertSame([\PHP_BINARY, 'bin/console', 'sightpress:install', '--resume'], $this->runner->attached[0]);
    }

    public function testInteractiveAvailabilityCanChooseASubset(): void
    {
        $this->plugins->plugins = [
            new InstalledPlugin('articles', 'sightpress/plugin-articles', '1.0.0', ''),
            new InstalledPlugin('acme.events', 'acme/plugin-events', '0.1.0', 'Events'),
        ];
        $this->store->state = new InstallState(['requirements', 'environment', 'plugins', 'media_storage', 'database']);
        $tester = $this->install(answers: ['no', 'articles, nope', 'articles']);
        $display = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString("1. acme.events (acme.events)\n2. Articles (articles)", $display);
        self::assertStringContainsString('Make every installed plugin available to sites? Type yes or no (default: yes): ', $display);
        self::assertStringContainsString('Error: Plugins available to sites: "nope" is not installed.', $display);
        self::assertStringContainsString('Passed: Available to sites: articles.', $display);
    }

    public function testInteractiveAvailabilityDefaultsToEveryPlugin(): void
    {
        $this->plugins->plugins = [new InstalledPlugin('articles', 'sightpress/plugin-articles', '1.0.0', '')];
        $this->store->state = new InstallState(['requirements', 'environment', 'plugins', 'media_storage', 'database']);

        self::assertStringContainsString('Passed: Every installed plugin is available to sites.', $this->install(answers: [''])->getDisplay());
    }

    public function testAnInteractiveMediaPathProblemIsAskedAgain(): void
    {
        $this->store->state = new InstallState(['requirements', 'environment', 'plugins']);
        $display = $this->install(answers: ['public/media', 'var/files'])->getDisplay();

        self::assertStringContainsString('Error: Media folder: The media path is inside public/', $display);
        self::assertStringContainsString('Passed: Media files are kept in '.$this->project.'/var/files (created now)', $display);
    }

    public function testEverySecretIsAnEnvironmentVariableNeverAnOption(): void
    {
        $definition = $this->install(['SIGHTPRESS_SKIP_INSTALL' => '1'], ['--from-composer' => true])->getInput()->getOptions();

        self::assertArrayHasKey('db-host', $definition);
        foreach (['db-password', 'mailer-dsn', 'app-secret'] as $secret) {
            self::assertArrayNotHasKey($secret, $definition);
        }
    }
}
