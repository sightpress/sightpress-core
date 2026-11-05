<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App;

use SightPress\Core\Kernel\CoreKernel;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** A minimal project on top of the core kernel, as the skeleton builds one. */
final class TestKernel extends CoreKernel
{
    /** @param array<string, mixed> $coreConfig extra sightpress_core configuration for one test */
    public function __construct(string $environment = 'test', bool $debug = false, private readonly array $coreConfig = [])
    {
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__, 3).'/var/cache/'.$this->environment.'-'.($this->debug ? 'debug' : 'nodebug').'-'.md5(serialize($this->coreConfig));
    }

    public function getLogDir(): string
    {
        return \dirname(__DIR__, 3).'/var/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        if ([] !== $this->coreConfig) {
            $container->loadFromExtension('sightpress_core', $this->coreConfig);
        }

        // Entry-point services (handlers that CLI commands and controllers will call) have no
        // caller yet, so the container would remove them. Keep them so tests can check the wiring.
        $container->addCompilerPass(new KeepCoreServicesPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }
}
