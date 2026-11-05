<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

/** One answer the installer needs: where it can come from, its default, and how it is checked. */
final readonly class InstallField
{
    public const string TEXT = 'text';
    public const string HOST = 'host';
    public const string IDENTIFIER = 'identifier';
    public const string PORT = 'port';
    public const string EMAIL = 'email';
    public const string DSN = 'dsn';
    public const string CHOICE = 'choice';
    public const string PATH = 'path';

    /** @param list<string> $choices */
    public function __construct(
        public string $key,
        public string $label,
        public string $env,
        public ?string $option = null,
        public ?string $default = null,
        public bool $secret = false,
        public bool $required = false,
        public string $type = self::TEXT,
        public array $choices = [],
        public ?string $example = null,
    ) {
    }

    /** The environment variable that may hold a path to a file with the value (Docker secrets). */
    public function fileEnv(): string
    {
        return $this->env.'_FILE';
    }

    /** @return string|null an error that names the field and says how to fix it, or null when the value is fine */
    public function validate(string $value): ?string
    {
        $label = $this->label;
        $example = null === $this->example ? '' : " such as $this->example";
        // A secret's value is never echoed back in an error message.
        $shown = $this->secret ? 'That value' : '"'.$value.'"';

        return match ($this->type) {
            self::PORT => ctype_digit($value) && (int) $value >= 1 && (int) $value <= 65535
                ? null
                : "$label: $shown is not a port number. Enter a whole number from 1 to 65535$example.",
            self::EMAIL => false !== filter_var($value, \FILTER_VALIDATE_EMAIL)
                ? null
                : "$label: $shown is not an email address. Enter one in the form name@example.org.",
            self::DSN => 1 === preg_match('#^[a-z][a-z0-9+.-]*://\S+$#i', $value)
                ? null
                : "$label: $shown is not a DSN. It must look like$example.",
            self::HOST => 1 === preg_match('/^[A-Za-z0-9.:\[\]_-]+$/', $value)
                ? null
                : "$label: $shown is not a host name or address. Use letters, digits, dots and hyphens$example.",
            self::IDENTIFIER => 1 === preg_match('/^[A-Za-z0-9_.-]+$/', $value)
                ? null
                : "$label: $shown can only contain letters, digits, underscores, dots and hyphens$example.",
            self::CHOICE => \in_array($value, $this->choices, true)
                ? null
                : "$label: $shown is not one of the choices. Enter one of: ".implode(', ', $this->choices).'.',
            default => '' !== trim($value)
                ? null
                : "$label: no value was given. Enter a value$example.",
        };
    }
}
