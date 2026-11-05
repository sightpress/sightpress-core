<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

/** Whether the database server answered, and whether the database exists. */
final readonly class DatabaseCheck
{
    public function __construct(
        public bool $reachable,
        public bool $exists,
        public ?string $error = null,
    ) {
    }
}
