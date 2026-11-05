<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/config', __DIR__.'/tests'])
    ->withSkip([__DIR__.'/tests/Fixtures/App/config/reference.php'])
    ->withSets([LevelSetList::UP_TO_PHP_84])
    ->withCache(__DIR__.'/var/rector');
