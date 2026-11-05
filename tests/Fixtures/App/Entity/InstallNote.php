<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use SightPress\Core\Shared\Domain\UlidIdentity;

/** An install-wide entity: the SiteFilter leaves it alone. */
#[ORM\Entity]
#[ORM\Table(name: 'install_note')]
class InstallNote
{
    use UlidIdentity;

    public function __construct(#[ORM\Column] public string $title)
    {
        $this->initialiseId();
    }
}
