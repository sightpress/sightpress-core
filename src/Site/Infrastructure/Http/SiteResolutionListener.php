<?php

declare(strict_types=1);

namespace SightPress\Core\Site\Infrastructure\Http;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use SightPress\Core\Site\Domain\Hostname;
use SightPress\Core\Site\Domain\SiteDomainProvider;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilterConfigurator;
use SightPress\Core\Site\Infrastructure\RequestSiteContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs before routing and before any site code: an unknown host gets a plain
 * 404 (no host-header cache poisoning, no poisoned links), a known one gets
 * its site on the request and in the SiteFilter.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 2048)]
final readonly class SiteResolutionListener
{
    public function __construct(
        private SiteDomainProvider $domains,
        private ManagerRegistry $registry,
        private SiteFilterConfigurator $filterConfigurator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $host = Hostname::normalize($request->headers->get('Host'));
        if (null === $host || !$this->domains->isKnownHost($host)) {
            $event->setResponse(new Response("Not found\n", Response::HTTP_NOT_FOUND, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]));

            return;
        }

        $site = $this->domains->findByHost($host);
        if (null === $site) {
            return;
        }

        $request->attributes->set(RequestSiteContext::ATTRIBUTE, $site);
        $manager = $this->registry->getManager();
        if ($manager instanceof EntityManagerInterface) {
            $this->filterConfigurator->apply($manager, $site->siteId);
        }
    }
}
