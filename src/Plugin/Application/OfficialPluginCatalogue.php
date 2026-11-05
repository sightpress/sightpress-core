<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Application;

/**
 * The official plugins (plugins.md §1.1). None is installed by default; the
 * installer, sightpress:plugin:list and the super-admin's plugin screen read
 * this list to tell the operator what exists.
 */
final readonly class OfficialPluginCatalogue
{
    /** Every official plugin works with Plugin API 1. */
    private const string CONSTRAINT = '^0.1 || ^1.0';

    /** @var array<string, OfficialPlugin> */
    private array $plugins;

    /** @param list<OfficialPlugin>|null $plugins the catalogue's entries; tests pass their own */
    public function __construct(?array $plugins = null)
    {
        $plugins ??= [
            new OfficialPlugin('articles', 'sightpress/plugin-articles', self::CONSTRAINT),
            new OfficialPlugin('community-helper', 'sightpress/plugin-community-helper', self::CONSTRAINT),
            new OfficialPlugin('form-builder', 'sightpress/plugin-form-builder', self::CONSTRAINT),
            new OfficialPlugin('newsletter-manager', 'sightpress/plugin-newsletter-manager', self::CONSTRAINT),
            new OfficialPlugin('notifications-helper', 'sightpress/plugin-notifications-helper', self::CONSTRAINT, setupCommand: 'php bin/console sightpress:notifications-helper:setup'),
            new OfficialPlugin('image-finder', 'sightpress/plugin-image-finder', self::CONSTRAINT, setupCommand: 'php bin/console sightpress:image-finder:setup'),
            new OfficialPlugin('ai-helper', 'sightpress/plugin-ai-helper', self::CONSTRAINT),
            new OfficialPlugin('file-importer', 'sightpress/plugin-file-importer', self::CONSTRAINT),
        ];
        $byId = [];
        foreach ($plugins as $plugin) {
            $byId[$plugin->id] = $plugin;
        }
        $this->plugins = $byId;
    }

    /** @return array<string, OfficialPlugin> keyed by id, in the order they are offered */
    public function all(): array
    {
        return $this->plugins;
    }

    public function find(string $id): ?OfficialPlugin
    {
        return $this->all()[$id] ?? null;
    }

    public function findByPackage(string $package): ?OfficialPlugin
    {
        foreach ($this->all() as $plugin) {
            if ($plugin->package === $package) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * Turns ids into plugins, adding the official plugins they need.
     *
     * @param list<string> $ids
     *
     * @throws UnknownOfficialPlugin when an id isn't in the catalogue
     */
    public function select(array $ids): PluginSelection
    {
        $unknown = array_values(array_filter($ids, fn (string $id): bool => null === $this->find($id)));
        if ([] !== $unknown) {
            throw new UnknownOfficialPlugin($unknown, array_keys($this->all()));
        }

        $chosen = [];
        $added = [];
        $queue = $ids;
        while ([] !== $queue) {
            $id = array_shift($queue);
            if (isset($chosen[$id])) {
                continue;
            }
            $plugin = $this->all()[$id];
            $chosen[$id] = $plugin;
            foreach ($plugin->requires as $required) {
                if (!isset($chosen[$required]) && !\in_array($required, $ids, true)) {
                    $added[$required] = $id;
                }
                $queue[] = $required;
            }
        }

        // Catalogue order, so the read-back is predictable.
        $ordered = array_values(array_filter($this->all(), static fn (OfficialPlugin $p): bool => isset($chosen[$p->id])));

        return new PluginSelection($ordered, $added);
    }
}
