<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

// A container cached by an earlier run skips the bundle's configuration code, so
// coverage would miss it. Every run starts from a clean test cache.
new Filesystem()->remove(dirname(__DIR__).'/var/cache');
