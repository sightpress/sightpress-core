<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Infrastructure;

use SightPress\Core\Install\Domain\RequirementProbe;
use Symfony\Component\Process\ExecutableFinder;

final readonly class SystemRequirementProbe implements RequirementProbe
{
    public function phpVersion(): string
    {
        return \PHP_VERSION;
    }

    public function hasExtension(string $name): bool
    {
        return \extension_loaded($name);
    }

    public function isWritable(string $path): bool
    {
        while (!file_exists($path)) {
            $parent = \dirname($path);
            if ($parent === $path) {
                return false;
            }
            $path = $parent;
        }

        return is_dir($path) && is_writable($path);
    }

    public function hasExecutable(string $name): bool
    {
        return null !== new ExecutableFinder()->find($name);
    }
}
