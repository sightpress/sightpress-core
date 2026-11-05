<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Support;

use SightPress\Core\Shared\Process\CommandResult;
use SightPress\Core\Shared\Process\CommandRunner;

/** Records commands and answers with prepared results, matched by a word in the command. */
final class FakeCommandRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $ran = [];
    /** @var list<list<string>> */
    public array $attached = [];

    /** @param array<string, CommandResult> $results keyed by a word that appears in the command */
    public function __construct(private array $results = [], public int $attachedExitCode = 0)
    {
    }

    public function willReturn(string $word, CommandResult $result): self
    {
        $this->results[$word] = $result;

        return $this;
    }

    public function run(array $command, callable $output, array $env = []): CommandResult
    {
        $this->ran[] = $command;
        foreach ($this->results as $word => $result) {
            if (\in_array($word, $command, true)) {
                $output($result->output);

                return $result;
            }
        }

        return new CommandResult(0);
    }

    public function runAttached(array $command): int
    {
        $this->attached[] = $command;

        return $this->attachedExitCode;
    }
}
