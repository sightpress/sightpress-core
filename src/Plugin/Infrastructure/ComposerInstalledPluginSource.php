<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Infrastructure;

use Composer\InstalledVersions;
use SightPress\Core\Plugin\Application\InstalledPlugin;
use SightPress\Core\Plugin\Application\InstalledPluginSource;

/** Reads the packages of type `sightpress-plugin` that Composer installed. */
final class ComposerInstalledPluginSource implements InstalledPluginSource
{
    public function all(): array
    {
        $plugins = [];
        foreach (InstalledVersions::getInstalledPackagesByType('sightpress-plugin') as $package) {
            $path = InstalledVersions::getInstallPath($package);
            $manifest = null !== $path && is_file($path.'/composer.json') ? json_decode((string) file_get_contents($path.'/composer.json'), true) : null;
            $manifest = \is_array($manifest) ? $manifest : [];
            $extra = \is_array($manifest['extra'] ?? null) ? $manifest['extra'] : [];
            $sightpress = \is_array($extra['sightpress'] ?? null) ? $extra['sightpress'] : [];

            $id = $sightpress['id'] ?? preg_replace('#^[^/]+/(plugin-)?#', '', $package);
            $description = $manifest['description'] ?? '';
            $plugins[] = new InstalledPlugin(
                \is_string($id) ? $id : $package,
                $package,
                InstalledVersions::getPrettyVersion($package) ?? 'unknown',
                \is_string($description) ? $description : '',
            );
        }

        return $plugins;
    }
}
