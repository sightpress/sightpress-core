<?php

declare(strict_types=1);

namespace SightPress\Core\Admin\SuperAdmin\Sites;

use Doctrine\ORM\EntityManagerInterface;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilter;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilterSwitch;

final readonly class Allowed
{
    public function __construct(private EntityManagerInterface $em, private SiteFilterSwitch $switch)
    {
    }

    public function run(): void
    {
        $this->switch->runWithoutSiteFilter('super-admin site list', static fn (): int => 1);
        $this->em->getFilters()->disable(SiteFilter::NAME);
    }
}
