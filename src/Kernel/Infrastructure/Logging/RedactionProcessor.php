<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Redacts known secret fields, authorisation headers, cookies and tokens from
 * every log record: by key name in the context and extra data, and by pattern
 * inside strings (bearer tokens, passwords in URLs, key=value pairs).
 */
final class RedactionProcessor implements ProcessorInterface
{
    public const string MASK = '[redacted]';

    private const array SENSITIVE_KEY_PARTS = [
        'password', 'passwd', 'secret', 'token', 'authorization', 'cookie', 'api_key', 'apikey', 'api-key',
        'dsn', 'credential', 'private_key', 'csrf', 'passphrase', 'signature',
    ];

    private const string KEY_PATTERN = '(?:password|passwd|secret|token|api[_-]?key|authorization|dsn|passphrase)';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactString($record->message),
            context: $this->redactArray($record->context),
            extra: $this->redactArray($record->extra),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function redactArray(array $data, int $depth = 0): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (\is_string($key) && $this->isSensitiveKey($key)) {
                $result[$key] = self::MASK;
            } elseif (\is_array($value)) {
                $result[$key] = $depth > 8 ? self::MASK : $this->redactArray($value, $depth + 1);
            } elseif (\is_string($value)) {
                $result[$key] = $this->redactString($value);
            } elseif ($value instanceof \Throwable) {
                $result[$key] = $value::class.': '.$this->redactString($value->getMessage());
            } elseif (\is_object($value) && !$value instanceof \Stringable) {
                $result[$key] = $value::class;
            } elseif ($value instanceof \Stringable) {
                $result[$key] = $this->redactString((string) $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        return array_any(self::SENSITIVE_KEY_PARTS, static fn ($part) => str_contains($lower, $part));
    }

    private function redactString(string $value): string
    {
        $patterns = [
            '#(://[^/\s:@]+:)[^@/\s]+(@)#' => '$1'.self::MASK.'$2',
            '#\b(Bearer|Basic)\s+[A-Za-z0-9._~+/=-]+#i' => '$1 '.self::MASK,
            '#\b('.self::KEY_PATTERN.'[\w-]*)(\s*[=:]\s*)("[^"]*"|\'[^\']*\'|[^\s,;&"\']+)#i' => '$1$2'.self::MASK,
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $value) ?? self::MASK;
    }
}
