<?php

declare(strict_types=1);

namespace SightPress\Core;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilter;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * The SightPress core. It brings its own configuration (prependExtension),
 * migrations, templates and translations, so a project never edits files to
 * use it.
 */
final class SightPressCoreBundle extends AbstractBundle
{
    protected string $extensionAlias = 'sightpress_core';

    /**
     * Bundles the core needs. The core kernel always registers them.
     *
     * @return list<class-string<BundleInterface>>
     */
    public static function requiredBundles(): array
    {
        return [
            FrameworkBundle::class,
            TwigBundle::class,
            MonologBundle::class,
            DoctrineBundle::class,
            DoctrineMigrationsBundle::class,
            self::class,
        ];
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('sites')
                    ->info('Stub site/domain provider until the Site module stores SiteDomain rows (M1).')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('hosts')->isRequired()->requiresAtLeastOneElement()->scalarPrototype()->end()->end()
                            ->scalarNode('default_locale')->defaultValue('en-GB')->end()
                            ->arrayNode('locales')->scalarPrototype()->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('dev_hosts')
                    ->info('Hosts accepted in debug mode when no site claims them.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['localhost', '127.0.0.1', '[::1]'])
                ->end()
                ->arrayNode('trusted_proxies')
                    ->info('Explicit proxy addresses or CIDR ranges. "Trust everything" is refused.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('admin_path_prefix')->defaultValue('/admin')->end()
                        ->arrayNode('media_hosts')->info('Media/CDN hostnames added to the CSP allowlist.')->scalarPrototype()->end()->defaultValue([])->end()
                        ->integerNode('hsts_max_age')->min(0)->defaultValue(31536000)->end()
                    ->end()
                ->end()
                ->arrayNode('performance')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('server_timing')->info('Server-Timing header. Defaults to debug mode only.')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array<string, mixed> $security */
        $security = $config['security'];
        /** @var array<string, mixed> $performance */
        $performance = $config['performance'];

        $container->parameters()
            ->set('sightpress_core.sites', $config['sites'])
            ->set('sightpress_core.dev_hosts', $config['dev_hosts'])
            ->set('sightpress_core.security.admin_path_prefix', $security['admin_path_prefix'])
            ->set('sightpress_core.security.media_hosts', $security['media_hosts'])
            ->set('sightpress_core.security.hsts_max_age', $security['hsts_max_age'])
            ->set('sightpress_core.performance.server_timing', $performance['server_timing'] ?? $builder->getParameter('kernel.debug'));

        $container->import('../config/services.php');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $debug = (bool) $builder->getParameter('kernel.debug');
        $path = $this->getPath();

        $framework = [
            'http_method_override' => false,
            'session' => [
                'cookie_secure' => $debug ? 'auto' : true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'lax',
            ],
        ];
        $proxies = $this->trustedProxies($builder);
        if ([] !== $proxies) {
            $framework['trusted_proxies'] = implode(',', $proxies);
            $framework['trusted_headers'] = ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-port', 'x-forwarded-prefix'];
        }
        if (is_dir($path.'/translations')) {
            $framework['translator'] = ['paths' => [$path.'/translations']];
        }
        // The admin layout, themes and assets land in M0 step 3c: these hooks pick them up when the folders exist.
        if (is_dir($path.'/assets') && interface_exists('Symfony\Component\AssetMapper\AssetMapperInterface')) {
            $framework['asset_mapper'] = ['paths' => [$path.'/assets' => 'sightpress-core']];
        }
        $container->extension('framework', $framework);

        if (is_dir($path.'/templates')) {
            $container->extension('twig', ['paths' => [$path.'/templates' => 'SightPressCore']]);
        }

        $mappings = [];
        foreach (glob($path.'/src/*/Domain', \GLOB_ONLYDIR) ?: [] as $domainDir) {
            $module = basename(\dirname($domainDir));
            $mappings['SightPressCore'.$module] = [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => $domainDir,
                'prefix' => 'SightPress\Core\\'.$module.'\Domain',
                'alias' => 'SightPressCore'.$module,
            ];
        }
        $container->extension('doctrine', [
            'orm' => [
                'mappings' => $mappings,
                'filters' => ['sightpress_site' => ['class' => SiteFilter::class, 'enabled' => true]],
            ],
        ]);
        $container->extension('doctrine_migrations', [
            'migrations_paths' => ['SightPress\Core\Migrations' => $path.'/migrations'],
        ]);
    }

    /** @return list<string> */
    private function trustedProxies(ContainerBuilder $builder): array
    {
        $proxies = [];
        foreach ($builder->getExtensionConfig('sightpress_core') as $config) {
            $list = $config['trusted_proxies'] ?? [];
            foreach (\is_array($list) ? $list : [] as $proxy) {
                if (!\is_string($proxy)) {
                    continue;
                }
                if (\in_array($proxy, ['*', '0.0.0.0/0', '::/0'], true)) {
                    throw new \InvalidArgumentException('sightpress_core.trusted_proxies must list explicit proxies; trusting every address is not allowed.');
                }
                $proxies[] = $proxy;
            }
        }

        return array_values(array_unique($proxies));
    }
}
