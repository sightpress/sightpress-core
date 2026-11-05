<?php

declare(strict_types=1);

namespace SightPress\Core\Shared\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * The id convention for every core entity: a ULID (`symfony/uid`), generated
 * in the constructor so an entity has its id before it is persisted.
 * Use it in an entity: `use UlidIdentity;` and call `$this->initialiseId()`.
 */
trait UlidIdentity
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    public function id(): Ulid
    {
        return $this->id;
    }

    private function initialiseId(?Ulid $id = null): void
    {
        $this->id = $id ?? new Ulid();
    }
}
