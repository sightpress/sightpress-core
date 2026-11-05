<?php

declare(strict_types=1);

namespace SightPress\Core\Content\Application;

use Doctrine\ORM\EntityManagerInterface;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilter;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilterSwitch;

final readonly class Forbidden
{
    public function __construct(private EntityManagerInterface $em, private SiteFilterSwitch $switch)
    {
    }

    public function run(string $name): void
    {
        $this->switch->runWithoutSiteFilter('listing every site', static fn (): int => 1);
        $this->em->getFilters()->disable(SiteFilter::NAME);
        $this->em->getFilters()->suspend('sightpress_site');
        $this->em->getFilters()->disable($name);
        // Other filters may be turned off.
        $this->em->getFilters()->disable('soft_delete');
        $this->em->getFilters()->enable(SiteFilter::NAME);
    }
}
