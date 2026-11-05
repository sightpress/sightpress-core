<?php

declare(strict_types=1);

namespace SightPress\Core\Shared\Process;

/** Runs a command in a child process, in the project directory. */
interface CommandRunner
{
    /**
     * Runs the command and passes its output through, line by line, as plain text.
     *
     * @param list<string>           $command the program and its arguments, never a shell string
     * @param callable(string): void $output  receives the child's output as it arrives
     * @param array<string, string>  $env     extra environment variables for the child
     */
    public function run(array $command, callable $output, array $env = []): CommandResult;

    /**
     * Runs the command attached to this terminal when there is one, so it can
     * ask its own questions. Returns its exit code.
     *
     * @param list<string> $command
     */
    public function runAttached(array $command): int;
}
