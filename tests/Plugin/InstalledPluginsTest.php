<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Plugin;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use SightPress\Core\Plugin\Application\InstalledPlugin;
use SightPress\Core\Plugin\Application\InstalledPluginSource;
use SightPress\Core\Plugin\Application\ListInstalledPlugins;
use SightPress\Core\Plugin\Infrastructure\ComposerInstalledPluginSource;

final class InstalledPluginsTest extends TestCase
{
    private string $dir;

    /** @var list<array{root: array{name: string, pretty_version: string, version: string, reference: string|null, type: string, install_path: string, aliases: string[], dev: bool}, versions: array<string, array{pretty_version?: string, version?: string, reference?: string|null, type?: string, install_path?: string, aliases?: string[], dev_requirement: bool, replaced?: string[], provided?: string[]}>}> */
    private array $installed;

    protected function setUp(): void
    {
        $this->installed = InstalledVersions::getAllRawData();
        $this->dir = sys_get_temp_dir().'/sightpress-plugins-'.bin2hex(random_bytes(6));
        mkdir($this->dir.'/acme-events', 0o777, true);
        mkdir($this->dir.'/forms', 0o777, true);
        mkdir($this->dir.'/broken', 0o777, true);
        file_put_contents($this->dir.'/acme-events/composer.json', (string) json_encode([
            'name' => 'acme/events',
            'description' => 'Events for SightPress sites.',
            'extra' => ['sightpress' => ['id' => 'acme.events']],
        ]));
        file_put_contents($this->dir.'/broken/composer.json', 'not json');
    }

    protected function tearDown(): void
    {
        foreach (['acme-events/composer.json', 'broken/composer.json'] as $file) {
            unlink($this->dir.'/'.$file);
        }
        foreach (['acme-events', 'forms', 'broken', ''] as $folder) {
            rmdir($this->dir.'/'.$folder);
        }
        // Forget the fake packages: put back the install data this project was loaded with.
        foreach ($this->installed as $data) {
            if ('sightpress/core' === $data['root']['name']) {
                InstalledVersions::reload($data);
            }
        }
    }

    public function testTheListIsSortedById(): void
    {
        $source = new class implements InstalledPluginSource {
            public function all(): array
            {
                return [
                    new InstalledPlugin('newsletter', 'sightpress/plugin-newsletter', '1.0.0', ''),
                    new InstalledPlugin('articles', 'sightpress/plugin-articles', '1.0.0', ''),
                ];
            }
        };

        $ids = array_map(static fn (InstalledPlugin $p): string => $p->id, new ListInstalledPlugins($source)());

        self::assertSame(['articles', 'newsletter'], $ids);
    }

    public function testComposerPackagesOfThePluginTypeAreListed(): void
    {
        InstalledVersions::reload([
            'root' => ['name' => 'test/app', 'pretty_version' => 'dev-main', 'version' => 'dev-main', 'reference' => null, 'type' => 'project', 'install_path' => $this->dir, 'aliases' => [], 'dev' => true],
            'versions' => [
                'acme/events' => ['pretty_version' => '2.1.0', 'version' => '2.1.0.0', 'reference' => null, 'type' => 'sightpress-plugin', 'install_path' => $this->dir.'/acme-events', 'aliases' => [], 'dev_requirement' => false],
                'sightpress/plugin-forms' => ['pretty_version' => '1.0.0', 'version' => '1.0.0.0', 'reference' => null, 'type' => 'sightpress-plugin', 'install_path' => $this->dir.'/forms', 'aliases' => [], 'dev_requirement' => false],
                'acme/broken' => ['pretty_version' => '0.1.0', 'version' => '0.1.0.0', 'reference' => null, 'type' => 'sightpress-plugin', 'install_path' => $this->dir.'/broken', 'aliases' => [], 'dev_requirement' => false],
                'acme/library' => ['pretty_version' => '1.0.0', 'version' => '1.0.0.0', 'reference' => null, 'type' => 'library', 'install_path' => $this->dir, 'aliases' => [], 'dev_requirement' => false],
            ],
        ]);

        $plugins = [];
        foreach (new ComposerInstalledPluginSource()->all() as $plugin) {
            $plugins[$plugin->package] = $plugin;
        }

        self::assertEquals(new InstalledPlugin('acme.events', 'acme/events', '2.1.0', 'Events for SightPress sites.'), $plugins['acme/events']);
        self::assertEquals(new InstalledPlugin('forms', 'sightpress/plugin-forms', '1.0.0', ''), $plugins['sightpress/plugin-forms']);
        self::assertEquals(new InstalledPlugin('broken', 'acme/broken', '0.1.0', ''), $plugins['acme/broken']);
        self::assertArrayNotHasKey('acme/library', $plugins);
    }
}
