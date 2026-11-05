<?php

declare(strict_types=1);

namespace SightPress\Core\Media\Application;

/**
 * Chooses where media files are kept. M0 only knows local filesystem storage;
 * the S3, GCS, Azure and plugin adapters join this command with the Media milestone.
 */
final readonly class ConfigureMediaStorage
{
    public const string LOCAL = 'local';

    public function __construct(
        public string $type,
        public string $path,
    ) {
    }
}
