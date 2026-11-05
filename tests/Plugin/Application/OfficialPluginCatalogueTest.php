<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Plugin\Application;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Plugin\Application\InstalledPlugin;
use SightPress\Core\Plugin\Application\ListInstalledPlugins;
use SightPress\Core\Plugin\Application\OfficialPlugin;
use SightPress\Core\Plugin\Application\OfficialPluginCatalogue;
use SightPress\Core\Plugin\Application\PluginInstallReport;
use SightPress\Core\Plugin\Application\UnknownOfficialPlugin;
use SightPress\Core\Plugin\Infrastructure\ComposerInstalledPluginSource;
use SightPress\Core\Tests\Support\CoreTranslator;
use SightPress\Core\Tests\Support\FakeInstalledPluginSource;
use SightPress\Plugin\Core\Quality\XliffChecker;

final class OfficialPluginCatalogueTest extends TestCase
{
    public function testTheCatalogueListsTheOfficialPluginsByTheirCurrentNames(): void
    {
        self::assertSame(
            ['articles', 'community-helper', 'form-builder', 'newsletter-manager', 'notifications-helper', 'image-finder', 'ai-helper', 'file-importer'],
            array_keys(new OfficialPluginCatalogue()->all()),
        );
    }

    public function testEveryEntryNamesItsPackageAndHasATranslatedNameAndDescription(): void
    {
        $translator = CoreTranslator::create();
        foreach (new OfficialPluginCatalogue()->all() as $id => $plugin) {
            self::assertSame('sightpress/plugin-'.$id, $plugin->package);
            self::assertNotSame($plugin->nameKey(), $translator->trans($plugin->nameKey(), [], 'messages+intl-icu'), $id.' has no name in en-GB.');
            self::assertNotSame($plugin->descriptionKey(), $translator->trans($plugin->descriptionKey(), [], 'messages+intl-icu'), $id.' has no description in en-GB.');
            self::assertStringStartsWith('sightpress/plugin-'.$id.':', $plugin->requirement());
            self::assertSame('php bin/console sightpress:plugin:install '.$id, $plugin->installCommand());
        }
    }

    public function testOnlyPluginsWithInstallWideSettingsHaveASetupCommand(): void
    {
        $withSetup = array_keys(array_filter(new OfficialPluginCatalogue()->all(), static fn (OfficialPlugin $p): bool => null !== $p->setupCommand));

        self::assertSame(['notifications-helper', 'image-finder'], $withSetup);
    }

    public function testLookupsByIdAndPackage(): void
    {
        $catalogue = new OfficialPluginCatalogue();

        self::assertSame('form-builder', $catalogue->find('form-builder')?->id);
        self::assertNull($catalogue->find('forms'), 'The old name is gone.');
        self::assertSame('ai-helper', $catalogue->findByPackage('sightpress/plugin-ai-helper')?->id);
        self::assertNull($catalogue->findByPackage('acme/plugin-events'));
    }

    public function testASelectionKeepsCatalogueOrderAndDropsDuplicates(): void
    {
        $selection = new OfficialPluginCatalogue()->select(['file-importer', 'articles', 'articles']);

        self::assertSame(['articles', 'file-importer'], $selection->ids());
        self::assertSame([], $selection->addedFor);
        self::assertFalse($selection->isEmpty());
        self::assertTrue(new OfficialPluginCatalogue()->select([])->isEmpty());
    }

    public function testUnknownIdsAreNamedWithTheValidOnes(): void
    {
        try {
            new OfficialPluginCatalogue()->select(['articles', 'forms', 'community']);
            self::fail('Unknown ids must be refused.');
        } catch (UnknownOfficialPlugin $e) {
            self::assertSame(['forms', 'community'], $e->unknown);
            self::assertStringStartsWith('"forms", "community" are not official plugins. The official plugins are: articles, community-helper,', $e->getMessage());
        }

        $this->expectExceptionMessage('"forms" is not an official plugin.');
        new OfficialPluginCatalogue()->select(['forms']);
    }

    public function testThePluginsAnotherNeedsAreAddedAndSaidSo(): void
    {
        $catalogue = new OfficialPluginCatalogue([
            new OfficialPlugin('articles', 'sightpress/plugin-articles', '^1.0'),
            new OfficialPlugin('wordpress-import', 'sightpress/plugin-wordpress-import', '^1.0', ['articles']),
        ]);

        $selection = $catalogue->select(['wordpress-import']);
        self::assertSame(['articles', 'wordpress-import'], $selection->ids());
        self::assertSame(['articles' => 'wordpress-import'], $selection->addedFor);
        self::assertSame([], $catalogue->select(['wordpress-import', 'articles'])->addedFor, 'A plugin chosen on purpose is not "added".');
    }

    public function testTheRetryCommandNamesEveryPlugin(): void
    {
        $catalogue = new OfficialPluginCatalogue();
        $report = new PluginInstallReport($catalogue->select(['articles', 'form-builder'])->plugins, false, 'no network');

        self::assertSame('php bin/console sightpress:plugin:install articles form-builder', $report->retryCommand());
    }

    public function testInstalledPluginsAreListedById(): void
    {
        $list = new ListInstalledPlugins(new FakeInstalledPluginSource([
            new InstalledPlugin('form-builder', 'sightpress/plugin-form-builder', '1.0.0', ''),
            new InstalledPlugin('articles', 'sightpress/plugin-articles', '1.0.0', ''),
        ]));

        self::assertSame(['articles', 'form-builder'], array_map(static fn (InstalledPlugin $p): string => $p->id, $list()));
    }

    public function testComposerListsNoPluginInTheCoreRepository(): void
    {
        // Core CI runs with no plugin installed.
        self::assertSame([], new ComposerInstalledPluginSource()->all());
    }

    public function testTheCatalogueStringsAreInEveryLaunchLocale(): void
    {
        $report = new XliffChecker()->check(\dirname(__DIR__, 3).'/translations', 'messages');

        self::assertSame([], $report->problems());
    }
}
