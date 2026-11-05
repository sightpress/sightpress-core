<?php

declare(strict_types=1);

use SightPress\Core\Kernel\Infrastructure\Logging\RedactionProcessor;
use SightPress\Core\Kernel\Infrastructure\Performance\ServerTimingListener;
use SightPress\Core\Kernel\Infrastructure\Routing\RouteAccessAuditor;
use SightPress\Core\Kernel\Infrastructure\Security\ContentSecurityPolicy;
use SightPress\Core\Kernel\Infrastructure\Security\CspNonce;
use SightPress\Core\Kernel\Infrastructure\Security\SecurityHeadersListener;
use SightPress\Core\Kernel\UI\Twig\CspExtension;
use SightPress\Core\Media\Application\ConfigureMediaStorageHandler;
use SightPress\Core\Plugin\Application\InstalledPluginSource;
use SightPress\Core\Plugin\Application\ListInstalledPlugins;
use SightPress\Core\Plugin\Infrastructure\ComposerInstalledPluginSource;
use SightPress\Core\Site\Domain\SiteDomainProvider;
use SightPress\Core\Site\Infrastructure\ConfiguredSiteDomainProvider;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilterConfigurator;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilterSwitch;
use SightPress\Core\Site\Infrastructure\Http\SiteResolutionListener;
use SightPress\Core\Site\Infrastructure\RequestSiteContext;
use SightPress\Plugin\Core\Port\SiteContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    // Kernel: security headers, logging, routing rules.
    $services->set(ContentSecurityPolicy::class)
        ->arg('$mediaHosts', param('sightpress_core.security.media_hosts'));
    $services->set(CspNonce::class);
    $services->set(SecurityHeadersListener::class)
        ->arg('$adminPathPrefix', param('sightpress_core.security.admin_path_prefix'))
        ->arg('$hstsMaxAge', param('sightpress_core.security.hsts_max_age'));
    $services->set(CspExtension::class);
    $services->set(RedactionProcessor::class)
        ->tag('monolog.processor');
    $services->set(RouteAccessAuditor::class);

    // Server-Timing is only for development: it would tell anyone how long the server took.
    if (true === $builder->getParameter('sightpress_core.performance.server_timing')) {
        $services->set(ServerTimingListener::class);
    }

    // Site: host resolution and the site filter.
    $services->set(ConfiguredSiteDomainProvider::class)
        ->arg('$sites', param('sightpress_core.sites'))
        ->arg('$devHosts', param('sightpress_core.dev_hosts'))
        ->arg('$debug', param('kernel.debug'));
    $services->alias(SiteDomainProvider::class, ConfiguredSiteDomainProvider::class);
    $services->set(SiteFilterConfigurator::class);
    $services->set(SiteFilterSwitch::class)
        ->arg('$entityManager', service('doctrine.orm.entity_manager'));
    $services->set(SiteResolutionListener::class)
        ->arg('$registry', service('doctrine'));
    $services->set(RequestSiteContext::class);
    $services->alias(SiteContext::class, RequestSiteContext::class);

    // Media and plugins.
    $services->set(ConfigureMediaStorageHandler::class)
        ->arg('$projectDir', param('kernel.project_dir'));
    $services->set(ComposerInstalledPluginSource::class);
    $services->alias(InstalledPluginSource::class, ComposerInstalledPluginSource::class);
    $services->set(ListInstalledPlugins::class);
};
