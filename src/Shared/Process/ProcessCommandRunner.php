<?php

declare(strict_types=1);

namespace SightPress\Core\Shared\Process;

use Symfony\Component\Process\Process;

/** Runs child processes with no time limit (Composer and migrations can be slow) and no shell. */
final readonly class ProcessCommandRunner implements CommandRunner
{
    /**
     * @param resource|null $stdout where an attached command's output goes when there is no TTY; STDOUT by default
     * @param resource|null $stderr where its errors go; STDERR by default
     */
    public function __construct(
        private string $workingDirectory,
        private mixed $stdout = null,
        private mixed $stderr = null,
    ) {
    }

    public function run(array $command, callable $output, array $env = []): CommandResult
    {
        $process = new Process($command, $this->workingDirectory, [...$env, 'NO_COLOR' => '1'], null, null);
        $collected = '';
        try {
            $process->run(static function (string $type, string $buffer) use ($output, &$collected): void {
                $collected .= $buffer;
                $output($buffer);
            });
        } catch (\Throwable $e) {
            return new CommandResult(127, $collected.$e->getMessage());
        }

        return new CommandResult($process->getExitCode() ?? 1, $collected);
    }

    public function runAttached(array $command): int
    {
        $process = new Process($command, $this->workingDirectory, null, null, null);
        // With a terminal, the child asks its own questions in it. Given streams (tests) mean no TTY.
        if (null === $this->stdout && Process::isTtySupported()) {
            $process->setTty(true);
        }
        try {
            return $process->run(function (string $type, string $buffer): void {
                fwrite(Process::ERR === $type ? $this->errors() : $this->output(), $buffer);
            });
        } catch (\Throwable $e) {
            fwrite($this->errors(), $e->getMessage()."\n");

            return 127;
        }
    }

    /** @return resource */
    private function output(): mixed
    {
        return \is_resource($this->stdout) ? $this->stdout : \STDOUT;
    }

    /** @return resource */
    private function errors(): mixed
    {
        return \is_resource($this->stderr) ? $this->stderr : \STDERR;
    }
}
