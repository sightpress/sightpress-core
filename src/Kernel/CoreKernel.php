<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel;

use SightPress\Core\SightPressCoreBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The kernel the skeleton's Kernel extends. It always registers the bundles
 * the core needs, then the project's config/bundles.php, loads the project's
 * config, and adds the core's own routes.
 */
abstract class CoreKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        $registered = [];
        foreach (SightPressCoreBundle::requiredBundles() as $class) {
            $registered[$class] = true;
            yield new $class();
        }

        $file = $this->getProjectDir().'/config/bundles.php';
        if (!is_file($file)) {
            return;
        }

        /** @var array<class-string<BundleInterface>, array<string, bool>> $bundles */
        $bundles = require $file;
        foreach ($bundles as $class => $environments) {
            if (isset($registered[$class])) {
                continue;
            }
            if ($environments[$this->environment] ?? $environments['all'] ?? false) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $config = $this->getProjectDir().'/config';

        $container->import($config.'/{packages}/*.{php,yaml}');
        $container->import($config.'/{packages}/'.$this->environment.'/*.{php,yaml}');

        if (is_file($config.'/services.yaml')) {
            $container->import($config.'/services.yaml');
            $container->import($config.'/{services}_'.$this->environment.'.yaml');
        } else {
            $container->import($config.'/{services}.php');
            $container->import($config.'/{services}_'.$this->environment.'.php');
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 2).'/config/routes.php');

        $config = $this->getProjectDir().'/config';
        $routes->import($config.'/{routes}/'.$this->environment.'/*.{php,yaml}');
        $routes->import($config.'/{routes}/*.{php,yaml}');
        if (is_file($config.'/routes.yaml')) {
            $routes->import($config.'/routes.yaml');
        } else {
            $routes->import($config.'/{routes}.php');
        }
    }
}
