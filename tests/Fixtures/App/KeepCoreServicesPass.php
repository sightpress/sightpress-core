<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Makes the core's own services public in the test kernel, so unused ones are not removed. */
final class KeepCoreServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_starts_with($id, 'SightPress\\Core\\')) {
                $definition->setPublic(true);
            }
        }
        foreach ($container->getAliases() as $id => $alias) {
            if (str_starts_with($id, 'SightPress\\') && !str_starts_with($id, 'SightPress\\Core\\Tests\\')) {
                $alias->setPublic(true);
            }
        }
    }
}
