<?php

declare(strict_types=1);

namespace SightPress\Core\Install\UI\Console;

use SightPress\Core\Install\Domain\ConfigWriter;
use SightPress\Core\Install\Domain\DatabaseParameters;
use SightPress\Core\Install\Domain\DatabaseProbe;
use SightPress\Core\Install\Domain\InstallField;
use SightPress\Core\Install\Domain\InstallFields;
use SightPress\Core\Install\Domain\InstallState;
use SightPress\Core\Install\Domain\InstallStateStore;
use SightPress\Core\Install\Domain\InstallStep;
use SightPress\Core\Install\Domain\RequirementProbe;
use SightPress\Core\Media\Application\ConfigureMediaStorage;
use SightPress\Core\Media\Application\ConfigureMediaStorageHandler;
use SightPress\Core\Media\Application\InvalidMediaStorage;
use SightPress\Core\Plugin\Application\InstalledPlugin;
use SightPress\Core\Plugin\Application\InstallOfficialPlugins;
use SightPress\Core\Plugin\Application\ListInstalledPlugins;
use SightPress\Core\Plugin\Application\OfficialPlugin;
use SightPress\Core\Plugin\Application\OfficialPluginCatalogue;
use SightPress\Core\Plugin\Application\UnknownOfficialPlugin;
use SightPress\Core\Shared\Process\CommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sets up a SightPress install (PLAN §3.4). Part 1 is the system: requirements,
 * environment, database and mail, official plugins, media storage, migrations
 * and plugin availability. Every answer is also an option or a SIGHTPRESS_*
 * variable; secrets come only from the environment, *_FILE or hidden input.
 * Every step is idempotent, and a failed run resumes where it stopped.
 *
 * Under Composer (--from-composer) it never hangs or fails: it exits 0 and
 * prints the next steps when there is no terminal or the install is done.
 */
#[AsCommand(name: 'sightpress:install', description: 'Sets up SightPress: system settings, database, media storage and plugins.')]
final class InstallCommand extends Command
{
    private const string NEXT_COMMAND = 'php bin/console sightpress:install';

    /** Extensions SightPress can't run without. */
    private const array EXTENSIONS = ['intl', 'pdo_pgsql', 'sodium', 'mbstring', 'fileinfo'];

    /** Optional tools, and what they add. */
    private const array TOOLS = [
        'ffmpeg' => 'the length of video and audio files, and video posters',
        'tesseract' => 'finding text in images',
        'qpdf' => 'checking that PDF files are tagged',
        'pandoc' => 'importing DOCX files with the file-importer plugin',
    ];

    /** @var array<string, string> */
    private array $environment;
    private Terminal $terminal;
    private InputInterface $input;
    private InstallState $state;
    private bool $interactive = true;
    private bool $fromComposer = false;
    /** @var list<string> plugins installed by this run */
    private array $installedNow = [];

    /** @param array<string, string>|null $environment the process environment; tests pass their own */
    public function __construct(
        private readonly InstallStateStore $store,
        private readonly RequirementProbe $requirements,
        private readonly DatabaseProbe $database,
        private readonly ConfigWriter $config,
        private readonly ConfigureMediaStorageHandler $media,
        private readonly OfficialPluginCatalogue $catalogue,
        private readonly InstallOfficialPlugins $installPlugins,
        private readonly ListInstalledPlugins $installedPlugins,
        private readonly CommandRunner $runner,
        private readonly TranslatorInterface $translator,
        private readonly string $projectDir,
        ?array $environment = null,
    ) {
        $this->environment = $environment ?? array_filter(getenv(), \is_string(...));
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from-composer', null, InputOption::VALUE_NONE, 'Run from Composer\'s post-install-cmd: never wait for input without a terminal, and always exit 0')
            ->addOption('resume', null, InputOption::VALUE_NONE, 'Carry on after the installer restarted itself to load new plugins');
        foreach (InstallFields::all() as $field) {
            // Secrets never get an option: options end up in shell history and process lists.
            if (null !== $field->option && !$field->secret) {
                $this->addOption($field->option, null, InputOption::VALUE_REQUIRED, \sprintf('%s (or %s)', $field->label, $field->env));
            }
        }
        $this->setHelp(<<<'HELP'
            Every answer can be given as an option or a SIGHTPRESS_* environment variable.
            Secrets (the database password, the mailer DSN, the application secret) are read only
            from environment variables or from *_FILE variables that point to a file.
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ('' !== ($this->environment['NO_COLOR'] ?? '')) {
            $output->setDecorated(false);
        }
        $this->input = $input;
        $this->terminal = new Terminal($input, $output);
        $this->fromComposer = (bool) $input->getOption('from-composer');
        $resume = (bool) $input->getOption('resume');
        $this->interactive = $input->isInteractive()
            && '1' !== ($this->environment['COMPOSER_NO_INTERACTION'] ?? '')
            && '' === ($this->environment['CI'] ?? '');

        if ($this->fromComposer && '1' === ($this->environment['SIGHTPRESS_SKIP_INSTALL'] ?? '')) {
            $this->terminal->line('SightPress installer skipped, because SIGHTPRESS_SKIP_INSTALL is 1. When you are ready, run: '.self::NEXT_COMMAND);

            return Command::SUCCESS;
        }

        $this->state = $this->store->load();
        if (null !== $this->state->installedAt && !$resume) {
            if ($this->fromComposer) {
                return $this->alreadyInstalled();
            }
            $this->terminal->line('SightPress is already installed. The setup runs again, and your earlier answers are the defaults.');
            $this->state = $this->state->restarted();
        }

        if (!$this->interactive) {
            try {
                if ($this->fromComposer && !$this->hasAnyAnswer() && !$resume) {
                    return $this->notInteractive();
                }
                $missing = $this->missingAnswers();
            } catch (InstallProblem $problem) {
                // A *_FILE variable that can't be read: say so, and never crash the Composer hook.
                $this->terminal->failed($problem->getMessage());

                return $this->finish(Command::FAILURE);
            }
            if ([] !== $missing) {
                $this->terminal->line('The install can\'t run unattended, because these values are missing:');
                foreach ($missing as $line) {
                    $this->terminal->line('- '.$line);
                }

                return $this->finish(Command::FAILURE);
            }
        }

        if (!$resume) {
            $this->introduce();
        }

        $steps = InstallStep::cases();
        foreach ($steps as $index => $step) {
            $this->terminal->step($index + 1, \count($steps), $step->title());
            if ($this->state->isCompleted($step->value)) {
                $this->terminal->line('Already done in an earlier run, so this step is skipped.');
                continue;
            }
            try {
                $restartCode = $this->runStep($step);
            } catch (InstallProblem $problem) {
                $this->terminal->failed($problem->getMessage());
                $this->state = $this->state->withFailure($step->value, $problem->getMessage());
                $this->store->save($this->state);
                $this->terminal->line(\sprintf('The install stopped at step %d. Fix the problem, then run %s again: it carries on from this step.', $index + 1, self::NEXT_COMMAND));

                return $this->finish(Command::FAILURE);
            }
            if ($step->isRequired()) {
                $this->state = $this->state->withCompleted($step->value);
            }
            $this->store->save($this->state);
            if (null !== $restartCode) {
                return $this->finish($restartCode);
            }
        }

        $this->state = $this->state->withInstalledAt(new \DateTimeImmutable()->format(\DATE_ATOM));
        $this->store->save($this->state);
        $this->closing();

        return Command::SUCCESS;
    }

    /** @return int|null the exit code of the restarted installer, when it restarted */
    private function runStep(InstallStep $step): ?int
    {
        return match ($step) {
            InstallStep::Requirements => $this->requirementsStep(),
            InstallStep::Environment => $this->environmentStep(),
            InstallStep::Plugins => $this->pluginsStep(),
            InstallStep::MediaStorage => $this->mediaStep(),
            InstallStep::Database => $this->migrationsStep(),
            InstallStep::SuperAdmin => $this->superAdminStep(),
            InstallStep::Availability => $this->availabilityStep(),
        };
    }

    private function introduce(): void
    {
        $this->terminal->line('SightPress installer.');
        $this->terminal->line('Part 1 sets up the system. Part 2 creates your first site.');
        if ($this->interactive) {
            $this->terminal->line('Each question states its default. Press Enter to accept it.');
        } else {
            $this->terminal->line('Running unattended, with the answers from options and SIGHTPRESS_* variables.');
        }
    }

    private function requirementsStep(): null
    {
        $problems = [];
        $php = $this->requirements->phpVersion();
        if (version_compare($php, '8.4.0', '>=')) {
            $this->terminal->passed(\sprintf('PHP %s (8.4 or later is needed).', $php));
        } else {
            $problems[] = \sprintf('PHP %s is too old. Install PHP 8.4 or later.', $php);
        }
        foreach (self::EXTENSIONS as $extension) {
            if ($this->requirements->hasExtension($extension)) {
                $this->terminal->passed(\sprintf('PHP extension %s is loaded.', $extension));
            } else {
                $problems[] = \sprintf('PHP extension %s is not loaded. Install or enable it, for example with the php8.4-%s package.', $extension, 'pdo_pgsql' === $extension ? 'pgsql' : $extension);
            }
        }
        if ($this->requirements->hasExtension('gd') || $this->requirements->hasExtension('imagick')) {
            $this->terminal->passed('An image extension (gd or imagick) is loaded.');
        } else {
            $problems[] = 'Neither the gd nor the imagick PHP extension is loaded. Install one of them, for example the php8.4-gd package.';
        }
        $mediaPath = $this->state->answers[InstallFields::MEDIA_PATH] ?? (string) InstallFields::get(InstallFields::MEDIA_PATH)->default;
        foreach (['var' => 'var/', $mediaPath => 'The media folder '.$mediaPath] as $path => $name) {
            $absolute = str_starts_with($path, '/') ? $path : $this->projectDir.'/'.$path;
            if ($this->requirements->isWritable($absolute)) {
                $this->terminal->passed(\sprintf('%s is writable.', 'var' === $path ? 'var/' : $name));
            } else {
                $problems[] = \sprintf('%s is not writable. Give the user that runs SightPress write access to it.', 'var' === $path ? 'var/' : $name);
            }
        }
        foreach ($problems as $problem) {
            $this->terminal->failed($problem);
        }
        foreach (self::TOOLS as $tool => $adds) {
            $this->terminal->line($this->requirements->hasExecutable($tool)
                ? \sprintf('Optional tool %s: found. It adds %s.', $tool, $adds)
                : \sprintf('Optional tool %s: not found. Without it, SightPress works, but without %s.', $tool, $adds));
        }
        if ([] !== $problems) {
            throw new InstallProblem(\sprintf('%d %s not met, listed above.', \count($problems), 1 === \count($problems) ? 'requirement is' : 'requirements are'));
        }

        return null;
    }

    private function environmentStep(): null
    {
        $appEnv = $this->choice(InstallFields::get(InstallFields::APP_ENV), ['prod' => 'Production (prod)', 'dev' => 'Development (dev), on your own computer only'], 'APP_ENV');

        $secret = $this->supplied(InstallFields::get(InstallFields::APP_SECRET));
        if (null !== $secret) {
            $this->terminal->line('Application secret: taken from SIGHTPRESS_APP_SECRET.');
        } elseif (null !== ($secret = $this->config->currentSecret('APP_SECRET')) && '' !== $secret) {
            $this->terminal->line('Application secret: kept from the earlier setup.');
        } else {
            $secret = bin2hex(random_bytes(32));
            $this->terminal->line('Application secret: generated. It is saved with the other secrets and never printed.');
        }

        $database = $this->databaseQuestions();

        $this->terminal->line('');
        $this->terminal->line('Mail. SightPress sends invitations, password resets and security alerts by email.');
        $mailer = $this->answer(InstallFields::get(InstallFields::MAILER_DSN), $this->config->currentSecret('MAILER_DSN'));
        $from = $this->answer(InstallFields::get(InstallFields::MAIL_FROM), $this->config->current('SIGHTPRESS_MAIL_FROM'));
        $messenger = $this->choice(InstallFields::get(InstallFields::MESSENGER), ['doctrine' => 'In the database (recommended: email and slow work run in the background)', 'sync' => 'Straight away, during the request (only for trying SightPress out)'], null);

        $written = $this->config->write(
            [
                'APP_ENV' => $appEnv,
                'SIGHTPRESS_MAIL_FROM' => $from,
                'MESSENGER_TRANSPORT_DSN' => 'sync' === $messenger ? 'sync://' : 'doctrine://default?auto_setup=0',
            ],
            ['APP_SECRET' => $secret, 'DATABASE_URL' => $database->url(), 'MAILER_DSN' => $mailer],
            'prod' === $appEnv,
        );
        $this->terminal->passed(\sprintf('Settings saved to %s. Secrets are never printed or logged.', implode(' and ', $written)));

        return null;
    }

    private function databaseQuestions(): DatabaseParameters
    {
        $current = $this->currentDatabase();
        $this->terminal->line('');
        $this->terminal->line('Database. SightPress needs PostgreSQL 17 or later.');
        while (true) {
            $parameters = new DatabaseParameters(
                $this->answer(InstallFields::get(InstallFields::DB_HOST), $current['host'] ?? null),
                (int) $this->answer(InstallFields::get(InstallFields::DB_PORT), $current['port'] ?? null),
                $this->answer(InstallFields::get(InstallFields::DB_NAME), $current['name'] ?? null),
                $this->answer(InstallFields::get(InstallFields::DB_USER), $current['user'] ?? null),
                $this->answer(InstallFields::get(InstallFields::DB_PASSWORD), $current['password'] ?? null),
            );

            $check = $this->database->check($parameters);
            $problem = null;
            if (!$check->reachable) {
                $problem = \sprintf('Database: SightPress could not connect to %s. The server said: %s', $parameters->describe(), $check->error ?? 'no reason given.');
            } elseif (!$check->exists) {
                $create = !$this->interactive || $this->terminal->confirm(\sprintf('The database "%s" doesn\'t exist yet. Create it now?', $parameters->name), true);
                if (!$create) {
                    throw new InstallProblem(\sprintf('Database: "%s" doesn\'t exist. Create it, then run the installer again.', $parameters->name));
                }
                $error = $this->database->create($parameters);
                if (null === $error) {
                    $this->terminal->passed(\sprintf('Database "%s" created.', $parameters->name));

                    return $parameters;
                }
                $problem = \sprintf('Database: "%s" could not be created. The server said: %s', $parameters->name, $error);
            } else {
                $this->terminal->passed(\sprintf('Connected to the database %s.', $parameters->describe()));

                return $parameters;
            }

            if (!$this->interactive) {
                throw new InstallProblem($problem);
            }
            $this->terminal->failed($problem);
            $this->terminal->line('Check the database details and answer again. Your answers are the new defaults.');
            $current = ['host' => $parameters->host, 'port' => (string) $parameters->port, 'name' => $parameters->name, 'user' => $parameters->user, 'password' => $parameters->password];
        }
    }

    /** @return array{host?: string, port?: string, name?: string, user?: string, password?: string} */
    private function currentDatabase(): array
    {
        $url = $this->config->currentSecret('DATABASE_URL');
        $parts = null === $url ? false : parse_url($url);
        if (!\is_array($parts)) {
            return [];
        }

        return array_filter([
            'host' => isset($parts['host']) ? trim($parts['host'], '[]') : null,
            'port' => isset($parts['port']) ? (string) $parts['port'] : null,
            'name' => isset($parts['path']) ? rawurldecode(ltrim($parts['path'], '/')) : null,
            'user' => isset($parts['user']) ? rawurldecode($parts['user']) : null,
            'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
        ], static fn (?string $v): bool => null !== $v && '' !== $v);
    }

    private function pluginsStep(): ?int
    {
        $catalogue = array_values($this->catalogue->all());
        $installed = array_map(static fn (InstalledPlugin $p): string => $p->package, ($this->installedPlugins)());
        $this->terminal->line('SightPress has official plugins that add features to the core. None is installed unless you choose it here, and you can add any of them later.');
        foreach ($catalogue as $number => $plugin) {
            $this->terminal->line(\sprintf(
                '%d. %s: %s %s.%s',
                $number + 1,
                $this->name($plugin),
                $this->translator->trans($plugin->descriptionKey()),
                \in_array($plugin->package, $installed, true) ? 'Installed' : 'Not installed',
                [] === $plugin->requires ? '' : ' Needs '.implode(', ', array_map(fn (string $id): string => $this->name($this->catalogue->all()[$id]), $plugin->requires)).'.',
            ));
        }

        $field = InstallFields::get(InstallFields::PLUGINS);
        $supplied = $this->supplied($field) ?? ($this->interactive ? null : ($this->state->answers[$field->key] ?? $field->default));
        $ids = null === $supplied ? $this->askPluginNumbers($catalogue) : $this->pluginIds($supplied, $catalogue);
        $ids = array_values(array_filter($ids, fn (string $id): bool => !\in_array($this->catalogue->all()[$id]->package, $installed, true)));
        $this->state = $this->state->withAnswer($field->key, [] === $ids ? 'none' : implode(',', $ids));

        $selection = $this->catalogue->select($ids);
        foreach ($selection->addedFor as $added => $neededBy) {
            $this->terminal->line(\sprintf('%s is added too, because %s needs it.', $this->name($this->catalogue->all()[$added]), $this->name($this->catalogue->all()[$neededBy])));
        }
        if ($selection->isEmpty()) {
            $this->terminal->line('No plugins will be installed.');

            return null;
        }
        $names = implode(', ', array_map($this->name(...), $selection->plugins));
        if ($this->interactive && !$this->terminal->confirm(\sprintf('These plugins will be installed: %s. Go ahead?', $names), true)) {
            $this->terminal->line('No plugins will be installed.');
            $this->state = $this->state->withAnswer($field->key, 'none');

            return null;
        }

        $this->terminal->line(\sprintf('Installing with Composer: %s. Composer\'s output follows.', $names));
        $report = ($this->installPlugins)($selection, $this->terminal->raw(...), false);
        foreach ($report->plugins as $plugin) {
            $this->terminal->line(\sprintf('%s: %s.', $this->name($plugin), $report->installed ? 'Installed' : 'Failed'));
        }
        if (!$report->installed) {
            $this->terminal->line('Reason: '.$report->reason);
            $this->terminal->line('The install carries on without them. To try again later, run: '.$report->retryCommand());

            return null;
        }
        $this->installedNow = $selection->ids();

        // New bundles need a new kernel, so the rest of the install runs in a fresh process.
        $this->state = $this->state->withCompleted(InstallStep::Plugins->value);
        $this->store->save($this->state);
        $this->terminal->line('Restarting the installer to load the new plugins.');
        $command = [\PHP_BINARY, 'bin/console', 'sightpress:install', '--resume'];
        if ($this->fromComposer) {
            $command[] = '--from-composer';
        }
        if (!$this->interactive) {
            $command[] = '--no-interaction';
        }

        return $this->runner->runAttached($command);
    }

    /**
     * @param list<OfficialPlugin> $catalogue
     *
     * @return list<string>
     */
    private function askPluginNumbers(array $catalogue): array
    {
        $ids = [];
        $this->terminal->ask('Which official plugins do you want to install? Type their numbers separated by commas, "all", or "none"', 'none', function (string $answer) use ($catalogue, &$ids): ?string {
            try {
                $ids = $this->pluginIds($answer, $catalogue);
            } catch (InstallProblem $e) {
                return $e->getMessage();
            }

            return null;
        });

        return $ids;
    }

    /**
     * Reads "none", "all", numbers or ids, separated by commas.
     *
     * @param list<OfficialPlugin> $catalogue
     *
     * @return list<string>
     */
    private function pluginIds(string $answer, array $catalogue): array
    {
        $answer = strtolower(trim($answer));
        if ('' === $answer || 'none' === $answer) {
            return [];
        }
        if ('all' === $answer) {
            return array_map(static fn (OfficialPlugin $p): string => $p->id, $catalogue);
        }
        $ids = [];
        $unknown = [];
        foreach (array_filter(array_map(trim(...), explode(',', $answer)), static fn (string $part): bool => '' !== $part) as $part) {
            if (ctype_digit($part) && isset($catalogue[(int) $part - 1])) {
                $ids[] = $catalogue[(int) $part - 1]->id;
            } elseif (null !== $this->catalogue->find($part)) {
                $ids[] = $part;
            } else {
                $unknown[] = $part;
            }
        }
        if ([] !== $unknown) {
            try {
                $this->catalogue->select($unknown);
            } catch (UnknownOfficialPlugin $e) {
                throw new InstallProblem(\sprintf('Official plugins: %s Type numbers from 1 to %d, "all" or "none".', $e->getMessage(), \count($catalogue)), 0, $e);
            }
        }

        return array_values(array_unique($ids));
    }

    private function mediaStep(): null
    {
        $this->terminal->line('Media storage: on this server\'s filesystem. Cloud storage and CDNs come with the media library in a later version.');
        $field = InstallFields::get(InstallFields::MEDIA_PATH);
        $supplied = $this->supplied($field);
        $default = $this->state->answers[$field->key] ?? (string) $field->default;
        $result = null;
        $configure = function (string $path) use (&$result): ?string {
            try {
                $result = ($this->media)(new ConfigureMediaStorage(ConfigureMediaStorage::LOCAL, $path));
            } catch (InvalidMediaStorage $e) {
                return 'Media folder: '.$e->getMessage();
            }

            return null;
        };

        if (null !== $supplied || !$this->interactive) {
            $path = $supplied ?? $default;
            $error = $configure($path);
            if (null !== $error) {
                throw new InstallProblem($error);
            }
        } else {
            $path = $this->terminal->ask($field->label, $default, $configure);
        }
        \assert(null !== $result);
        $this->state = $this->state->withAnswer($field->key, $path);
        $this->terminal->passed(\sprintf('Media files are kept in %s%s, outside the public folder.', $result->absolutePath, $result->created ? ' (created now)' : ''));

        return null;
    }

    private function migrationsStep(): null
    {
        $this->terminal->line('Running every database migration, in a separate process so the new settings are used.');
        $result = $this->runner->run([\PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration', '--no-ansi'], $this->terminal->raw(...));
        if (!$result->isSuccessful()) {
            throw new InstallProblem('Database migrations: '.('' === $result->lastLine() ? 'they stopped with exit code '.$result->exitCode.'.' : $result->lastLine()));
        }
        $this->terminal->passed('The database is up to date.');

        return null;
    }

    private function superAdminStep(): null
    {
        $this->terminal->line('Creating the super-admin account isn\'t part of this version yet, so there is nothing to answer. A later version adds it to this installer.');

        return null;
    }

    private function availabilityStep(): null
    {
        $installed = ($this->installedPlugins)();
        $field = InstallFields::get(InstallFields::AVAILABLE_PLUGINS);
        if ([] === $installed) {
            $this->terminal->line('No plugins are installed, so there is nothing to make available. You can add official plugins later with sightpress:plugin:install.');
            $this->state = $this->state->withAnswer($field->key, 'all');

            return null;
        }
        $this->terminal->line(\sprintf('Installed plugins (%d). Each is available to every site unless you choose a subset, and is off on a site until it is enabled there:', \count($installed)));
        foreach ($installed as $number => $plugin) {
            $this->terminal->line(\sprintf('%d. %s (%s)', $number + 1, $this->installedName($plugin), $plugin->id));
        }
        $ids = array_map(static fn (InstalledPlugin $p): string => $p->id, $installed);
        $validate = static function (string $answer) use ($ids, $field): ?string {
            if ('all' === $answer) {
                return null;
            }
            $unknown = array_diff(array_map(trim(...), explode(',', $answer)), $ids);

            return [] === $unknown ? null : \sprintf('%s: "%s" %s not installed. Type "all" or ids from the list, separated by commas.', $field->label, implode('", "', $unknown), 1 === \count($unknown) ? 'is' : 'are');
        };

        $supplied = $this->supplied($field);
        if (null !== $supplied || !$this->interactive) {
            $answer = $supplied ?? ($this->state->answers[$field->key] ?? 'all');
            $error = $validate($answer);
            if (null !== $error) {
                throw new InstallProblem($error);
            }
        } elseif ($this->terminal->confirm('Make every installed plugin available to sites?', true)) {
            $answer = 'all';
        } else {
            $answer = $this->terminal->ask('Which plugins should be available? Type their ids separated by commas', 'all', $validate);
        }
        $this->state = $this->state->withAnswer($field->key, $answer);
        $this->terminal->passed('all' === $answer ? 'Every installed plugin is available to sites.' : 'Available to sites: '.$answer.'.');

        return null;
    }

    private function closing(): void
    {
        $this->terminal->line('');
        $this->terminal->line('Part 1 is done: the system is set up.');
        $this->terminal->line('Part 2, creating your first site, isn\'t part of this version yet. A later version adds it to this installer.');
        $this->nextSteps();
    }

    private function nextSteps(): void
    {
        $steps = [];
        if ('prod' === ($this->config->current('APP_ENV') ?? '')) {
            $steps[] = 'Run composer dump-env prod, so the settings load faster.';
            $steps[] = 'Keep config/secrets/prod/prod.decrypt.private.php secret and out of version control: it decrypts the production secrets.';
        }
        $installedPackages = array_map(static fn (InstalledPlugin $p): string => $p->package, ($this->installedPlugins)());
        foreach ($this->catalogue->all() as $plugin) {
            $isInstalled = \in_array($plugin->package, $installedPackages, true) || \in_array($plugin->id, $this->installedNow, true);
            if ($isInstalled && null !== $plugin->setupCommand) {
                $steps[] = \sprintf('Set up %s\'s install-wide settings: %s', $this->name($plugin), $plugin->setupCommand);
            }
        }

        $missing = array_filter($this->catalogue->all(), fn (OfficialPlugin $p): bool => !\in_array($p->package, $installedPackages, true) && !\in_array($p->id, $this->installedNow, true));
        if ([] !== $steps) {
            $this->terminal->line('');
            $this->terminal->line('Next steps:');
            foreach ($steps as $number => $step) {
                $this->terminal->line(\sprintf('%d. %s', $number + 1, $step));
            }
        }
        if ([] !== $missing) {
            $this->terminal->line('');
            $this->terminal->line(\sprintf('Official plugins you can install later (%d):', \count($missing)));
            foreach (array_values($missing) as $number => $plugin) {
                $this->terminal->line(\sprintf('%d. %s: %s Install it with: %s', $number + 1, $this->name($plugin), $this->translator->trans($plugin->descriptionKey()), $plugin->installCommand()));
            }
        }
    }

    private function alreadyInstalled(): int
    {
        $this->terminal->line('SightPress is already installed, so the installer has nothing to do.');
        $result = $this->runner->run([\PHP_BINARY, 'bin/console', 'doctrine:migrations:up-to-date', '--no-interaction', '--no-ansi'], static function (string $buffer): void {
        });
        $this->terminal->line($result->isSuccessful()
            ? 'Database migrations: up to date.'
            : 'Database migrations: some may be waiting, or the database can\'t be reached. Run: php bin/console doctrine:migrations:migrate');
        $this->terminal->line('To run the setup again, run: '.self::NEXT_COMMAND);

        return Command::SUCCESS;
    }

    private function notInteractive(): int
    {
        $this->terminal->line('SightPress isn\'t set up yet. There is no terminal to ask questions in, so the installer didn\'t run.');
        $this->terminal->line('To finish the install, run: '.self::NEXT_COMMAND);
        $this->terminal->line('For an unattended install, give every answer as a SIGHTPRESS_* environment variable; run '.self::NEXT_COMMAND.' --help to see them.');
        $this->nextSteps();

        return Command::SUCCESS;
    }

    /** Under Composer the hook must never fail. */
    private function finish(int $code): int
    {
        return $this->fromComposer ? Command::SUCCESS : $code;
    }

    private function hasAnyAnswer(): bool
    {
        return array_any(InstallFields::all(), fn ($field) => null !== $this->supplied($field));
    }

    /** @return list<string> one line per required answer an unattended run doesn't have */
    private function missingAnswers(): array
    {
        $missing = [];
        foreach (InstallFields::all() as $field) {
            if (!$field->required || null !== $this->supplied($field) || isset($this->state->answers[$field->key])) {
                continue;
            }
            $kept = match ($field->key) {
                InstallFields::DB_PASSWORD => $this->currentDatabase()['password'] ?? null,
                InstallFields::MAILER_DSN => $this->config->currentSecret('MAILER_DSN'),
                InstallFields::MAIL_FROM => $this->config->current('SIGHTPRESS_MAIL_FROM'),
                default => null,
            };
            if (null !== $kept && '' !== $kept) {
                continue;
            }
            $missing[] = $field->secret
                ? \sprintf('%s: set %s, or %s to the path of a file that holds it.', $field->label, $field->env, $field->fileEnv())
                : \sprintf('%s: give --%s or set %s.', $field->label, $field->option, $field->env);
        }

        return $missing;
    }

    /** A value from an option (never for secrets), the environment, or a *_FILE variable. */
    private function supplied(InstallField $field): ?string
    {
        if (!$field->secret && null !== $field->option) {
            $option = $this->input->getOption($field->option);
            if (\is_string($option) && '' !== trim($option)) {
                return trim($option);
            }
        }
        $value = $this->environment[$field->env] ?? '';
        if ('' !== $value) {
            return $value;
        }
        $file = $this->environment[$field->fileEnv()] ?? '';
        if ('' === $file) {
            return null;
        }
        $content = is_file($file) && is_readable($file) ? file_get_contents($file) : false;
        if (false === $content) {
            throw new InstallProblem(\sprintf('%s: %s points to %s, which can\'t be read. Check the path and its permissions.', $field->label, $field->fileEnv(), $file));
        }

        return rtrim($content, "\r\n");
    }

    /**
     * The answer to one question: supplied, else asked (with the earlier answer
     * as the default), else the default.
     */
    private function answer(InstallField $field, #[\SensitiveParameter] ?string $current = null): string
    {
        $supplied = $this->supplied($field);
        if (null !== $supplied) {
            $error = $field->validate($supplied);
            if (null !== $error) {
                throw new InstallProblem($error);
            }
            $this->remember($field, $supplied);

            return $supplied;
        }

        $earlier = $field->secret ? null : ($this->state->answers[$field->key] ?? $current);
        if (!$this->interactive) {
            $value = $field->secret ? $current : ($earlier ?? $field->default);
            if (null === $value) {
                throw new InstallProblem(\sprintf('%s: no value was given. Set %s.', $field->label, $field->env));
            }
            $this->remember($field, $value);

            return $value;
        }

        $hasCurrent = $field->secret && null !== $current && '' !== $current;
        $value = $this->terminal->ask(
            $field->label,
            $field->secret ? ($hasCurrent ? $current : null) : ($earlier ?? $field->default),
            static fn (string $answer): ?string => $field->validate($answer),
            $field->secret,
            $hasCurrent,
        );
        $this->remember($field, $value);

        return $value;
    }

    /**
     * @param array<string, string> $labels value => label
     */
    private function choice(InstallField $field, array $labels, ?string $currentVariable): string
    {
        $supplied = $this->supplied($field);
        if (null !== $supplied) {
            $error = $field->validate($supplied);
            if (null !== $error) {
                throw new InstallProblem($error);
            }
            $value = $supplied;
        } else {
            $default = $this->state->answers[$field->key] ?? (null === $currentVariable ? null : $this->config->current($currentVariable)) ?? (string) $field->default;
            $default = \in_array($default, $field->choices, true) ? $default : (string) $field->default;
            $value = $this->interactive ? $this->terminal->choose($field->label, $labels, $default) : $default;
        }
        $this->remember($field, $value);

        return $value;
    }

    private function remember(InstallField $field, string $value): void
    {
        if (!$field->secret) {
            $this->state = $this->state->withAnswer($field->key, $value);
        }
    }

    private function name(OfficialPlugin $plugin): string
    {
        return $this->translator->trans($plugin->nameKey());
    }

    private function installedName(InstalledPlugin $plugin): string
    {
        $official = $this->catalogue->findByPackage($plugin->package);

        return null === $official ? $plugin->id : $this->name($official);
    }
}
