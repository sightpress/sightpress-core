<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Support;

use SightPress\Core\Install\Domain\DatabaseCheck;
use SightPress\Core\Install\Domain\DatabaseParameters;
use SightPress\Core\Install\Domain\DatabaseProbe;

/** Answers each check from a queue; the last answer repeats. */
final class FakeDatabaseProbe implements DatabaseProbe
{
    /** @var list<DatabaseParameters> */
    public array $checked = [];
    /** @var list<DatabaseParameters> */
    public array $created = [];

    /** @param list<DatabaseCheck> $checks */
    public function __construct(private array $checks = [new DatabaseCheck(true, true)], private readonly ?string $createError = null)
    {
    }

    public function check(DatabaseParameters $parameters): DatabaseCheck
    {
        $this->checked[] = $parameters;

        return \count($this->checks) > 1 ? array_shift($this->checks) : $this->checks[0];
    }

    public function create(DatabaseParameters $parameters): ?string
    {
        $this->created[] = $parameters;

        return $this->createError;
    }
}
