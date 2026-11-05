<?php

declare(strict_types=1);

namespace SightPress\Core\Shared\Domain;

use SightPress\Plugin\Core\Value\AbstractId;
use Symfony\Component\Uid\Ulid;

/** Converts between the ULIDs Doctrine stores and the public id value types of the Plugin API. */
final class Ids
{
    public static function toUlid(AbstractId $id): Ulid
    {
        return Ulid::fromString($id->toString());
    }

    /**
     * @template T of AbstractId
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    public static function fromUlid(string $type, Ulid $ulid): AbstractId
    {
        return $type::fromString($ulid->toBase32());
    }
}
