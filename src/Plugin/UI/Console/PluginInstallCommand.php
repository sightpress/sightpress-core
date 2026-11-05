<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\UI\Console;

use SightPress\Core\Plugin\Application\InstallOfficialPlugins;
use SightPress\Core\Plugin\Application\OfficialPluginCatalogue;
use SightPress\Core\Plugin\Application\UnknownOfficialPlugin;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Installs official plugins from the operator's shell: one `composer require`
 * of the catalogue's packages, then the migrations. It is never exposed in
 * the admin or the API, so code is only installed by the operator.
 */
#[AsCommand(name: 'sightpress:plugin:install', description: 'Installs official plugins with Composer, then runs their migrations.')]
final class PluginInstallCommand extends Command
{
    public function __construct(
        private readonly OfficialPluginCatalogue $catalogue,
        private readonly InstallOfficialPlugins $install,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ids', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Official plugin ids, for example articles form-builder')
            ->addOption('no-migrate', null, InputOption::VALUE_NONE, 'Install the packages but leave the migrations for later');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids = [];
        foreach ((array) $input->getArgument('ids') as $id) {
            if (\is_string($id) && '' !== trim($id)) {
                $ids[] = trim($id);
            }
        }
        $ids = array_values(array_unique($ids));
        try {
            $selection = $this->catalogue->select($ids);
        } catch (UnknownOfficialPlugin $e) {
            $output->writeln('Error: '.$e->getMessage());

            return Command::INVALID;
        }

        foreach ($selection->addedFor as $added => $neededBy) {
            $output->writeln(\sprintf('Also installing %s, because %s needs it.', $this->name($added), $this->name($neededBy)));
        }
        $output->writeln(\sprintf('Installing with Composer: %s.', implode(', ', array_map(fn ($p): string => $this->name($p->id), $selection->plugins))));

        $report = ($this->install)($selection, static function (string $buffer) use ($output): void {
            $output->write($buffer, false, OutputInterface::OUTPUT_RAW);
        }, !(bool) $input->getOption('no-migrate'));

        foreach ($report->plugins as $plugin) {
            $output->writeln(\sprintf('%s: %s.', $this->name($plugin->id), $report->installed ? 'Installed' : 'Failed'));
        }
        if (!$report->installed) {
            $output->writeln('Reason: '.$report->reason);
            $output->writeln('To try again, run: '.$report->retryCommand());

            return Command::FAILURE;
        }
        if (null !== $report->migrationProblem) {
            $output->writeln($report->migrationProblem);
            $output->writeln('To run them again: php bin/console doctrine:migrations:migrate');

            return Command::FAILURE;
        }
        foreach ($report->plugins as $plugin) {
            if (null !== $plugin->setupCommand) {
                $output->writeln(\sprintf('Next step for %s: set up its install-wide settings with %s', $this->name($plugin->id), $plugin->setupCommand));
            }
        }
        $output->writeln('Installed plugins are off on every site until a site admin enables them.');

        return Command::SUCCESS;
    }

    private function name(string $id): string
    {
        $plugin = $this->catalogue->find($id);

        return null === $plugin ? $id : $this->translator->trans($plugin->nameKey());
    }
}
