<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App;

use SightPress\Core\Kernel\CoreKernel;
use SightPress\Core\Tests\Fixtures\App\Security\FixtureAuthorizationChecker;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** A minimal project on top of the core kernel, as the skeleton builds one. */
final class TestKernel extends CoreKernel
{
    /**
     * @param array<string, mixed> $coreConfig extra sightpress_core configuration for one test
     * @param list<string>|null    $granted    voter attributes the fixture authorization checker grants; null means no checker, as in M0
     */
    public function __construct(string $environment = 'test', bool $debug = false, private readonly array $coreConfig = [], private readonly ?array $granted = null)
    {
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__, 3).'/var/cache/'.$this->environment.'-'.($this->debug ? 'debug' : 'nodebug').'-'.md5(serialize([$this->coreConfig, $this->granted]));
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

        if (null !== $this->granted) {
            $container->register('security.authorization_checker', FixtureAuthorizationChecker::class)
                ->setArgument('$granted', $this->granted)
                ->setPublic(true);
        }

        // Entry-point services (handlers that CLI commands and controllers will call) have no
        // caller yet, so the container would remove them. Keep them so tests can check the wiring.
        $container->addCompilerPass(new KeepCoreServicesPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }
}
