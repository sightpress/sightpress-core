<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Application;

/** Ids that aren't official plugins. The message names them and lists the valid ones. */
final class UnknownOfficialPlugin extends \InvalidArgumentException
{
    /**
     * @param list<string> $unknown
     * @param list<string> $known
     */
    public function __construct(public readonly array $unknown, array $known)
    {
        parent::__construct(\sprintf(
            '%s %s. The official plugins are: %s.',
            '"'.implode('", "', $unknown).'"',
            1 === \count($unknown) ? 'is not an official plugin' : 'are not official plugins',
            implode(', ', $known),
        ));
    }
}
