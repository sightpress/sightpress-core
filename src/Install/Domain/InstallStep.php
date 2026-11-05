<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

/** The steps of Part 1, in order (PLAN §3.4). Each is idempotent, so a failed run resumes. */
enum InstallStep: string
{
    case Requirements = 'requirements';
    case Environment = 'environment';
    case Plugins = 'plugins';
    case MediaStorage = 'media_storage';
    case Database = 'database';
    case SuperAdmin = 'super_admin';
    case Availability = 'availability';

    public function title(): string
    {
        return match ($this) {
            self::Requirements => 'Requirements',
            self::Environment => 'Environment, database and mail',
            self::Plugins => 'Official plugins',
            self::MediaStorage => 'Media storage',
            self::Database => 'Database migrations',
            self::SuperAdmin => 'Super-admin account',
            self::Availability => 'Plugin availability',
        };
    }

    /** Steps that must pass before the install counts as done. */
    public function isRequired(): bool
    {
        return self::SuperAdmin !== $this;
    }

    /** @return list<string> */
    public static function requiredKeys(): array
    {
        return array_values(array_map(static fn (self $s): string => $s->value, array_filter(self::cases(), static fn (self $s): bool => $s->isRequired())));
    }
}
