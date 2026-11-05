<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Support;

use SightPress\Core\Install\Domain\InstallState;
use SightPress\Core\Install\Domain\InstallStateStore;

final class InMemoryInstallStateStore implements InstallStateStore
{
    public function __construct(public InstallState $state = new InstallState())
    {
    }

    public function load(): InstallState
    {
        return $this->state;
    }

    public function save(InstallState $state): void
    {
        $this->state = $state;
    }
}
