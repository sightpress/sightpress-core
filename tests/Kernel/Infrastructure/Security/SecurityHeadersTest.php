<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Kernel\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Kernel\Infrastructure\Security\ContentSecurityPolicy;
use SightPress\Core\Kernel\Infrastructure\Security\CspNonce;
use SightPress\Core\Kernel\Infrastructure\Security\SecurityHeadersListener;
use SightPress\Core\Kernel\UI\Twig\CspExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersTest extends TestCase
{
    private function respond(Request $request, ?Response $response = null, int $type = HttpKernelInterface::MAIN_REQUEST, int $hsts = 31536000): Response
    {
        $response ??= new Response();
        $listener = new SecurityHeadersListener(new ContentSecurityPolicy(['media.example.net']), '/admin', $hsts);
        $listener(new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, $type, $response));

        return $response;
    }

    public function testThePolicyIsStrict(): void
    {
        $policy = new ContentSecurityPolicy(['cdn.example.net', 'media.example.net'])->build('abc', false, true);

        self::assertSame(
            "default-src 'none'; script-src 'self' 'nonce-abc'; style-src 'self' 'nonce-abc'; img-src 'self' data: https://cdn.example.net https://media.example.net; font-src 'self' https://cdn.example.net https://media.example.net; media-src 'self' https://cdn.example.net https://media.example.net; connect-src 'self'; manifest-src 'self'; worker-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; upgrade-insecure-requests",
            $policy,
        );
        self::assertStringNotContainsString('unsafe-inline', $policy);
        self::assertStringNotContainsString('unsafe-eval', $policy);
    }

    public function testEveryResponseGetsTheHeaders(): void
    {
        $response = $this->respond(Request::create('http://example.org/about'));

        self::assertStringContainsString("script-src 'self' 'nonce-", (string) $response->headers->get('Content-Security-Policy'));
        self::assertStringContainsString("frame-ancestors 'self'", (string) $response->headers->get('Content-Security-Policy'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
        self::assertFalse($response->headers->has('Strict-Transport-Security'), 'HSTS is only sent over HTTPS.');
    }

    public function testHeadersOverwriteWhatAFeatureSet(): void
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy', "script-src * 'unsafe-eval'");

        $this->respond(Request::create('/'), $response);

        self::assertStringNotContainsString('unsafe-eval', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testAdminPagesCannotBeFramed(): void
    {
        foreach (['/admin', '/admin/pages'] as $path) {
            self::assertStringContainsString("frame-ancestors 'none'", (string) $this->respond(Request::create($path))->headers->get('Content-Security-Policy'));
        }
        self::assertStringContainsString("frame-ancestors 'self'", (string) $this->respond(Request::create('/administrators'))->headers->get('Content-Security-Policy'));
    }

    public function testHttpsAddsHstsAndUpgradesRequests(): void
    {
        $response = $this->respond(Request::create('https://example.org/'));

        self::assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
        self::assertStringEndsWith('upgrade-insecure-requests', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testHstsCanBeTurnedOffWithAZeroMaxAge(): void
    {
        self::assertFalse($this->respond(Request::create('https://example.org/'), hsts: 0)->headers->has('Strict-Transport-Security'));
    }

    public function testACacheablePageGetsNoNonceUnlessItsTemplateUsedOne(): void
    {
        $cacheable = new Response();
        $cacheable->setPublic();
        $cacheable->setSharedMaxAge(60);
        $plain = $this->respond(Request::create('/'), $cacheable);
        self::assertStringNotContainsString('nonce-', (string) $plain->headers->get('Content-Security-Policy'));

        $request = Request::create('/');
        $stack = new RequestStack();
        $stack->push($request);
        $nonce = new CspNonce($stack)->current();
        $used = new Response();
        $used->setPublic();
        self::assertStringContainsString("'nonce-$nonce'", (string) $this->respond($request, $used)->headers->get('Content-Security-Policy'));
    }

    public function testSubRequestsAreLeftAlone(): void
    {
        self::assertFalse($this->respond(Request::create('/'), type: HttpKernelInterface::SUB_REQUEST)->headers->has('Content-Security-Policy'));
    }

    public function testTheNonceIsPerRequestAndStable(): void
    {
        $first = Request::create('/');
        $second = Request::create('/');

        self::assertSame(CspNonce::forRequest($first), CspNonce::forRequest($first));
        self::assertNotSame(CspNonce::forRequest($first), CspNonce::forRequest($second));
        self::assertSame(24, \strlen(CspNonce::forRequest($first)));
        self::assertFalse(CspNonce::wasUsed($first));
    }

    public function testTheNonceIsEmptyOutsideARequest(): void
    {
        self::assertSame('', new CspNonce(new RequestStack())->current());
    }

    public function testTheTwigFunctionReturnsTheMainRequestNonce(): void
    {
        $request = Request::create('/');
        $stack = new RequestStack();
        $stack->push($request);

        $functions = new CspExtension(new CspNonce($stack))->getFunctions();
        $callable = $functions[0]->getCallable();

        self::assertSame('csp_nonce', $functions[0]->getName());
        self::assertIsCallable($callable);
        self::assertSame(CspNonce::forRequest($request), $callable());
        self::assertTrue(CspNonce::wasUsed($request));
    }
}
