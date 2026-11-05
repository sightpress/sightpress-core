<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Shared;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Shared\Process\CommandResult;
use SightPress\Core\Shared\Process\ProcessCommandRunner;

final class ProcessCommandRunnerTest extends TestCase
{
    public function testOutputIsPassedThroughAndCollected(): void
    {
        $seen = '';
        $result = new ProcessCommandRunner(sys_get_temp_dir())->run([\PHP_BINARY, '-r', 'echo getenv("NO_COLOR"), "|", getenv("EXTRA"), "\n", "last line\n\n";'], static function (string $buffer) use (&$seen): void {
            $seen .= $buffer;
        }, ['EXTRA' => 'yes']);

        self::assertTrue($result->isSuccessful());
        self::assertSame("1|yes\nlast line\n\n", $seen);
        self::assertSame('last line', $result->lastLine());
    }

    public function testTheExitCodeIsKept(): void
    {
        $result = new ProcessCommandRunner(sys_get_temp_dir())->run([\PHP_BINARY, '-r', 'exit(3);'], static function (string $b): void {
        });

        self::assertSame(3, $result->exitCode);
        self::assertFalse($result->isSuccessful());
        self::assertSame('', $result->lastLine());
    }

    public function testAProgramThatCannotStartIsReportedInsteadOfThrown(): void
    {
        $result = new ProcessCommandRunner('/nonexistent-'.bin2hex(random_bytes(4)))->run([\PHP_BINARY, '-v'], static function (string $b): void {
        });

        self::assertSame(127, $result->exitCode);
        self::assertNotSame('', $result->lastLine());
    }

    public function testAnAttachedCommandReturnsItsExitCodeAndPassesItsOutputThrough(): void
    {
        [$stdout, $stderr] = [fopen('php://memory', 'w+'), fopen('php://memory', 'w+')];
        \assert(\is_resource($stdout) && \is_resource($stderr));

        self::assertSame(4, new ProcessCommandRunner(sys_get_temp_dir(), $stdout, $stderr)->runAttached([\PHP_BINARY, '-r', 'echo "out"; fwrite(STDERR, "err"); exit(4);']));
        rewind($stdout);
        rewind($stderr);
        self::assertSame('out', stream_get_contents($stdout));
        self::assertSame('err', stream_get_contents($stderr));
    }

    public function testAnAttachedCommandThatCannotStartReturns127AndSaysWhy(): void
    {
        $stderr = fopen('php://memory', 'w+');
        \assert(\is_resource($stderr));

        self::assertSame(127, new ProcessCommandRunner('/nonexistent-dir', null, $stderr)->runAttached([\PHP_BINARY, '-v']));
        rewind($stderr);
        self::assertStringContainsString('/nonexistent-dir', (string) stream_get_contents($stderr));
    }

    public function testTheLastLineSkipsBlankLines(): void
    {
        self::assertSame('b', new CommandResult(1, "a\r\n  b  \r\n\r\n")->lastLine());
    }
}
