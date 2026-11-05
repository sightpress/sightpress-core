<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\UI\Console;

use SightPress\Core\Plugin\Application\InstalledPlugin;
use SightPress\Core\Plugin\Application\ListInstalledPlugins;
use SightPress\Core\Plugin\Application\OfficialPluginCatalogue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lists the installed plugins, then the official plugins that aren't
 * installed, each with its description and the command that installs it.
 * Plain text only, so it reads well with a screen reader.
 */
#[AsCommand(name: 'sightpress:plugin:list', description: 'Lists installed plugins and the official plugins you can install.')]
final class PluginListCommand extends Command
{
    public function __construct(
        private readonly ListInstalledPlugins $installed,
        private readonly OfficialPluginCatalogue $catalogue,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $installed = ($this->installed)();
        $packages = array_map(static fn (InstalledPlugin $p): string => $p->package, $installed);

        if ([] === $installed) {
            $output->writeln('Installed plugins: none. SightPress works fully with no plugin installed.');
        } else {
            $output->writeln(\sprintf('Installed plugins (%d):', \count($installed)));
            foreach ($installed as $number => $plugin) {
                $official = $this->catalogue->findByPackage($plugin->package);
                $name = null === $official ? $plugin->id : $this->translator->trans($official->nameKey());
                $description = null === $official ? $plugin->description : $this->translator->trans($official->descriptionKey());
                $output->writeln(\sprintf('%d. %s (%s), version %s, package %s.', $number + 1, $name, $plugin->id, $plugin->version, $plugin->package));
                if ('' !== $description) {
                    $output->writeln('   '.$description);
                }
                if (null === $official) {
                    $output->writeln('   Not an official plugin: it was added with Composer.');
                }
            }
        }

        $missing = array_values(array_filter($this->catalogue->all(), static fn ($p): bool => !\in_array($p->package, $packages, true)));
        $output->writeln('');
        if ([] === $missing) {
            $output->writeln('Every official plugin is installed.');

            return Command::SUCCESS;
        }

        $output->writeln(\sprintf('Official plugins not installed (%d):', \count($missing)));
        foreach ($missing as $number => $plugin) {
            $output->writeln(\sprintf('%d. %s (%s), not installed.', $number + 1, $this->translator->trans($plugin->nameKey()), $plugin->id));
            $output->writeln('   '.$this->translator->trans($plugin->descriptionKey()));
            $output->writeln('   Install it with: '.$plugin->installCommand());
        }

        return Command::SUCCESS;
    }
}
