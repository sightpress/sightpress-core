<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Support;

use SightPress\Core\Install\Domain\RequirementProbe;

final class FakeRequirementProbe implements RequirementProbe
{
    /**
     * @param list<string> $missingExtensions
     * @param list<string> $unwritable        paths ending with one of these are not writable
     * @param list<string> $executables
     */
    public function __construct(
        public string $php = '8.4.3',
        public array $missingExtensions = [],
        public array $unwritable = [],
        public array $executables = ['ffmpeg'],
    ) {
    }

    public function phpVersion(): string
    {
        return $this->php;
    }

    public function hasExtension(string $name): bool
    {
        return !\in_array($name, $this->missingExtensions, true);
    }

    public function isWritable(string $path): bool
    {
        return array_all($this->unwritable, static fn ($suffix) => !str_ends_with($path, $suffix));
    }

    public function hasExecutable(string $name): bool
    {
        return \in_array($name, $this->executables, true);
    }
}
