<?php

declare(strict_types=1);

namespace SightPress\Core\Shared\Process;

/** How a child process ended. */
final readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $output = '',
    ) {
    }

    public function isSuccessful(): bool
    {
        return 0 === $this->exitCode;
    }

    /** The last non-empty line of output, to name the reason for a failure in one line. */
    public function lastLine(): string
    {
        $lines = array_values(array_filter(array_map(trim(...), preg_split('/\R/', $this->output) ?: []), static fn (string $line): bool => '' !== $line));

        return [] === $lines ? '' : $lines[\count($lines) - 1];
    }
}
