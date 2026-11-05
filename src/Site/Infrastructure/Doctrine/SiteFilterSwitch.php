<?php

declare(strict_types=1);

namespace SightPress\Core\Site\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The only way to run a query without the SiteFilter. The PHPStan rule
 * SightPress\Core\Quality\PHPStan\SiteFilterRule allows calls to it (and to
 * FilterCollection::disable/suspend) only in super-admin namespaces, and every
 * use needs a stated reason and an isolation test.
 */
final readonly class SiteFilterSwitch
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runWithoutSiteFilter(string $reason, callable $callback): mixed
    {
        if ('' === trim($reason)) {
            throw new \InvalidArgumentException('Turning off the site filter needs a stated reason.');
        }

        $filters = $this->entityManager->getFilters();
        if (!$filters->isEnabled(SiteFilter::NAME)) {
            return $callback();
        }

        $this->logger->notice('Site filter turned off for a super-admin code path.', ['reason' => $reason]);
        $filters->suspend(SiteFilter::NAME);
        try {
            return $callback();
        } finally {
            $filters->restore(SiteFilter::NAME);
        }
    }
}
