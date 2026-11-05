<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Application;

/** Where the installed plugins come from. */
interface InstalledPluginSource
{
    /** @return list<InstalledPlugin> */
    public function all(): array;
}
