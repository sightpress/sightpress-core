<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

/** Every answer of Part 1 of the installer. Each is also an option or a SIGHTPRESS_* variable. */
final class InstallFields
{
    public const string APP_ENV = 'app_env';
    public const string APP_SECRET = 'app_secret';
    public const string MESSENGER = 'messenger_transport';
    public const string DB_HOST = 'db_host';
    public const string DB_PORT = 'db_port';
    public const string DB_NAME = 'db_name';
    public const string DB_USER = 'db_user';
    public const string DB_PASSWORD = 'db_password';
    public const string MAILER_DSN = 'mailer_dsn';
    public const string MAIL_FROM = 'mail_from';
    public const string MEDIA_STORAGE = 'media_storage';
    public const string MEDIA_PATH = 'media_path';
    public const string PLUGINS = 'plugins';

    /** @return array<string, InstallField> keyed by field key */
    public static function all(): array
    {
        $fields = [
            new InstallField(self::APP_ENV, 'Application environment', 'SIGHTPRESS_APP_ENV', 'app-env', 'prod', type: InstallField::CHOICE, choices: ['prod', 'dev']),
            new InstallField(self::APP_SECRET, 'Application secret', 'SIGHTPRESS_APP_SECRET', secret: true),
            new InstallField(self::MESSENGER, 'Background job queue', 'SIGHTPRESS_MESSENGER_TRANSPORT', 'messenger-transport', 'doctrine', type: InstallField::CHOICE, choices: ['doctrine', 'sync']),
            new InstallField(self::DB_HOST, 'Database host', 'SIGHTPRESS_DB_HOST', 'db-host', '127.0.0.1', type: InstallField::HOST),
            new InstallField(self::DB_PORT, 'Database port', 'SIGHTPRESS_DB_PORT', 'db-port', '5432', type: InstallField::PORT, example: '5432'),
            new InstallField(self::DB_NAME, 'Database name', 'SIGHTPRESS_DB_NAME', 'db-name', 'sightpress', type: InstallField::IDENTIFIER),
            new InstallField(self::DB_USER, 'Database user', 'SIGHTPRESS_DB_USER', 'db-user', 'sightpress', type: InstallField::IDENTIFIER),
            new InstallField(self::DB_PASSWORD, 'Database password', 'SIGHTPRESS_DB_PASSWORD', secret: true, required: true),
            new InstallField(self::MAILER_DSN, 'Mailer DSN', 'SIGHTPRESS_MAILER_DSN', secret: true, required: true, type: InstallField::DSN, example: 'smtp://localhost:1025'),
            new InstallField(self::MAIL_FROM, 'Default sender address', 'SIGHTPRESS_MAIL_FROM', 'mail-from', required: true, type: InstallField::EMAIL, example: 'no-reply@example.org'),
            new InstallField(self::MEDIA_STORAGE, 'Media storage', 'SIGHTPRESS_MEDIA_STORAGE', 'media-storage', 'local', type: InstallField::CHOICE, choices: ['local']),
            new InstallField(self::MEDIA_PATH, 'Media folder', 'SIGHTPRESS_MEDIA_PATH', 'media-path', 'var/storage/media', type: InstallField::PATH),
            new InstallField(self::PLUGINS, 'Plugins available to sites', 'SIGHTPRESS_PLUGINS', 'plugins', 'all'),
        ];

        $byKey = [];
        foreach ($fields as $field) {
            $byKey[$field->key] = $field;
        }

        return $byKey;
    }

    public static function get(string $key): InstallField
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException(\sprintf('Unknown installer field "%s".', $key));
    }
}
