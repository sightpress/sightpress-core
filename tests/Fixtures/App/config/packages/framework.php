<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'secret' => 'test-only-secret',
        'test' => true,
        'router' => ['utf8' => true],
        'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
    ]);
    $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates']);
    $container->extension('monolog', ['handlers' => ['main' => ['type' => 'test']]]);
    $container->extension('doctrine', [
        'dbal' => ['url' => 'sqlite:///:memory:'],
        'orm' => [
            'mappings' => [
                'Fixtures' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/Entity',
                    'prefix' => 'SightPress\Core\Tests\Fixtures\App\Entity',
                ],
            ],
        ],
    ]);
    $container->extension('sightpress_core', [
        'sites' => [
            ['id' => '01JC0000000000000000000001', 'hosts' => ['example.org', 'www.example.org'], 'default_locale' => 'en-GB', 'locales' => ['en-GB', 'fr-FR']],
        ],
    ]);
};
