<?php

declare(strict_types=1);

return [
    // Already registered by the core kernel: listed again, it is registered once.
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    SightPress\Core\Tests\Fixtures\App\FixtureBundle::class => ['test' => true],
    SightPress\Core\Tests\Fixtures\App\ProdOnlyBundle::class => ['prod' => true],
];
