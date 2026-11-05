<?php

declare(strict_types=1);

namespace SightPress\Core\Media\Application;

/** A media storage choice that can't be used. It names the field and says how to fix it. */
final class InvalidMediaStorage extends \InvalidArgumentException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
