<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

interface DatabaseProbe
{
    public function check(DatabaseParameters $parameters): DatabaseCheck;

    /** @return string|null an error in words, or null when the database was created */
    public function create(DatabaseParameters $parameters): ?string;
}
