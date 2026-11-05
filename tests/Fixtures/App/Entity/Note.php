<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use SightPress\Core\Shared\Domain\Ids;
use SightPress\Core\Shared\Domain\UlidIdentity;
use SightPress\Plugin\Core\Value\SiteId;
use SightPress\Plugin\Core\Value\SiteOwnedInterface;
use Symfony\Component\Uid\Ulid;

/** A site-owned entity for SiteFilter tests. */
#[ORM\Entity]
#[ORM\Table(name: 'note')]
class Note implements SiteOwnedInterface
{
    use UlidIdentity;

    #[ORM\Column(name: 'site_id', type: 'ulid')]
    private Ulid $siteId;

    public function __construct(SiteId $siteId, #[ORM\Column] public string $title, ?Ulid $id = null)
    {
        $this->initialiseId($id);
        $this->siteId = Ids::toUlid($siteId);
    }

    public function getSiteId(): SiteId
    {
        return Ids::fromUlid(SiteId::class, $this->siteId);
    }
}
