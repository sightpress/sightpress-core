<?php

declare(strict_types=1);

namespace SightPress\Core\Media\Application;

/** What ConfigureMediaStorage settled on. */
final readonly class MediaStorageResult
{
    public function __construct(
        public string $type,
        public string $absolutePath,
        public bool $created,
    ) {
    }
}
