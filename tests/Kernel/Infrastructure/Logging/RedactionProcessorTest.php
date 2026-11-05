<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Kernel\Infrastructure\Logging;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use SightPress\Core\Kernel\Infrastructure\Logging\RedactionProcessor;

final class RedactionProcessorTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $context
     * @param array<array-key, mixed> $extra
     */
    private function process(string $message, array $context = [], array $extra = []): LogRecord
    {
        return new RedactionProcessor()(new LogRecord(new \DateTimeImmutable(), 'app', Level::Info, $message, $context, $extra));
    }

    public function testSensitiveKeysAreMaskedWhateverTheirValue(): void
    {
        $record = $this->process('x', [
            'password' => 'hunter2',
            'API_KEY' => 'k',
            'db_dsn' => 'pgsql://u:p@h/d',
            'Authorization' => ['Bearer abc'],
            'user' => 'ada',
        ]);

        self::assertSame([
            'password' => '[redacted]',
            'API_KEY' => '[redacted]',
            'db_dsn' => '[redacted]',
            'Authorization' => '[redacted]',
            'user' => 'ada',
        ], $record->context);
    }

    public function testSecretsInsideStringsAreMasked(): void
    {
        $record = $this->process('Connecting to pgsql://app:s3cret@db:5432/site with Bearer abc.def-ghi and token=xyz; password: "p w"');

        self::assertSame('Connecting to pgsql://app:[redacted]@db:5432/site with Bearer [redacted] and token=[redacted]; password: [redacted]', $record->message);
        self::assertStringNotContainsString('s3cret', $record->message);
    }

    public function testNestedArraysExceptionsAndObjects(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'secret=abc';
            }
        };

        $record = $this->process('x', [
            'request' => ['headers' => ['cookie' => 'sid=1', 'accept' => 'text/html'], 'query' => 'api_key=123'],
            'exception' => new \RuntimeException('Login failed for password=hunter2'),
            'object' => new \stdClass(),
            'stringable' => $stringable,
            'count' => 3,
            7 => 'token=abc',
        ], ['url' => 'https://u:p@example.org/']);

        self::assertSame([
            'request' => ['headers' => ['cookie' => '[redacted]', 'accept' => 'text/html'], 'query' => 'api_key=[redacted]'],
            'exception' => 'RuntimeException: Login failed for password=[redacted]',
            'object' => 'stdClass',
            'stringable' => 'secret=[redacted]',
            'count' => 3,
            7 => 'token=[redacted]',
        ], $record->context);
        self::assertSame(['url' => 'https://u:[redacted]@example.org/'], $record->extra);
    }

    public function testVeryDeepDataIsMaskedRatherThanWalkedForever(): void
    {
        $deep = ['leaf' => 'value'];
        for ($i = 0; $i < 12; ++$i) {
            $deep = ['level' => $deep];
        }

        $record = $this->process('x', ['deep' => $deep]);

        self::assertStringNotContainsString('value', (string) json_encode($record->context));
        self::assertStringContainsString('[redacted]', (string) json_encode($record->context));
    }
}
