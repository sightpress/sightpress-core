<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

/** Where the installer keeps its progress. */
interface InstallStateStore
{
    public function load(): InstallState;

    public function save(InstallState $state): void;
}
