<?php

declare(strict_types=1);

namespace SightPress\Core\Site\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use SightPress\Plugin\Core\Value\SiteId;

/** Points the SiteFilter of an entity manager at a site. */
final class SiteFilterConfigurator
{
    public function apply(EntityManagerInterface $entityManager, SiteId $siteId): void
    {
        $filters = $entityManager->getFilters();
        $filter = $filters->isEnabled(SiteFilter::NAME) ? $filters->getFilter(SiteFilter::NAME) : $filters->enable(SiteFilter::NAME);
        $filter->setParameter(SiteFilter::PARAMETER, $siteId->toString(), 'string');
    }
}
