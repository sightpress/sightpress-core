<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The per-request CSP nonce. It lives on the main request, so the service
 * itself holds no state and is safe in a long-running worker.
 */
final readonly class CspNonce
{
    public const string ATTRIBUTE = '_sightpress_csp_nonce';
    public const string USED_ATTRIBUTE = '_sightpress_csp_nonce_used';

    public function __construct(private RequestStack $requestStack)
    {
    }

    /** The nonce for the current main request, or an empty string outside a request. Calling it marks the nonce as used. */
    public function current(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (!$request instanceof Request) {
            return '';
        }
        $request->attributes->set(self::USED_ATTRIBUTE, true);

        return self::forRequest($request);
    }

    public static function forRequest(Request $request): string
    {
        $nonce = $request->attributes->get(self::ATTRIBUTE);
        if (!\is_string($nonce)) {
            $nonce = base64_encode(random_bytes(16));
            $request->attributes->set(self::ATTRIBUTE, $nonce);
        }

        return $nonce;
    }

    public static function wasUsed(Request $request): bool
    {
        return true === $request->attributes->get(self::USED_ATTRIBUTE);
    }
}
