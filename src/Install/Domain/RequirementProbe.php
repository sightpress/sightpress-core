<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

/** What the installer asks the machine it runs on. */
interface RequirementProbe
{
    public function phpVersion(): string;

    public function hasExtension(string $name): bool;

    /** Whether the folder (or its nearest existing parent) is writable. */
    public function isWritable(string $path): bool;

    public function hasExecutable(string $name): bool;
}
