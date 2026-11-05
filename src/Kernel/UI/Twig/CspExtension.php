<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\UI\Twig;

use SightPress\Core\Kernel\Infrastructure\Security\CspNonce;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** `csp_nonce()` returns the nonce to put on a script or style element the page must carry. */
final class CspExtension extends AbstractExtension
{
    public function __construct(private readonly CspNonce $nonce)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('csp_nonce', $this->nonce->current(...))];
    }
}
