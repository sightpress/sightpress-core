<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Kernel\Infrastructure\Performance;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Kernel\Infrastructure\Performance\ServerTimingListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ServerTimingListenerTest extends TestCase
{
    public function testTheMainRequestGetsAServerTimingHeader(): void
    {
        $kernel = self::createStub(HttpKernelInterface::class);
        $listener = new ServerTimingListener();
        $request = Request::create('/');

        $listener->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $response = new Response();
        $listener->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertMatchesRegularExpression('/^app;dur=\d+\.\d;desc="Application"$/', (string) $response->headers->get('Server-Timing'));
    }

    public function testSubRequestsAreIgnored(): void
    {
        $kernel = self::createStub(HttpKernelInterface::class);
        $listener = new ServerTimingListener();
        $request = Request::create('/');

        $listener->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST));
        $response = new Response();
        $listener->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response));

        self::assertFalse($response->headers->has('Server-Timing'));
    }
}
