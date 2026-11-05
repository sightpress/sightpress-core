<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Security;

/**
 * Builds the strict Content-Security-Policy: no inline scripts, no
 * unsafe-eval, nothing but this origin plus the media/CDN hostnames.
 */
final readonly class ContentSecurityPolicy
{
    /** @param list<string> $mediaHosts hostnames, without scheme */
    public function __construct(private array $mediaHosts = [])
    {
    }

    public function build(?string $nonce, bool $admin, bool $secure): string
    {
        $nonceSource = null === $nonce ? '' : " 'nonce-$nonce'";
        $media = '';
        foreach ($this->mediaHosts as $host) {
            $media .= ' https://'.$host;
        }

        $directives = [
            "default-src 'none'",
            "script-src 'self'".$nonceSource,
            "style-src 'self'".$nonceSource,
            "img-src 'self' data:".$media,
            "font-src 'self'".$media,
            "media-src 'self'".$media,
            "connect-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            'frame-ancestors '.($admin ? "'none'" : "'self'"),
        ];
        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
