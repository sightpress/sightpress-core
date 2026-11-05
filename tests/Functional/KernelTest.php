<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Functional;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Kernel\Infrastructure\Logging\RedactionProcessor;
use SightPress\Core\Kernel\Infrastructure\Performance\ServerTimingListener;
use SightPress\Core\Kernel\Infrastructure\Routing\RouteAccessAuditor;
use SightPress\Core\Media\Application\ConfigureMediaStorageHandler;
use SightPress\Core\Plugin\Application\ListInstalledPlugins;
use SightPress\Core\SightPressCoreBundle;
use SightPress\Core\Tests\Fixtures\App\FixtureBundle;
use SightPress\Core\Tests\Fixtures\App\ProdOnlyBundle;
use SightPress\Core\Tests\Fixtures\App\TestKernel;
use SightPress\Plugin\Core\Port\SiteContext;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleAdapter;
use Symfony\Component\Routing\RouterInterface;

final class KernelTest extends TestCase
{
    /** @var list<TestKernel> */
    private array $kernels = [];

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
    }

    /** @param array<string, mixed> $coreConfig */
    private function kernel(bool $debug = false, array $coreConfig = []): TestKernel
    {
        $kernel = new TestKernel('test', $debug, $coreConfig);
        $kernel->boot();
        $this->kernels[] = $kernel;

        return $kernel;
    }

    private function request(string $uri, bool $debug = false): Response
    {
        return $this->kernel($debug)->handle(Request::create($uri));
    }

    private function container(TestKernel $kernel): ContainerInterface
    {
        $container = $kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);

        return $container;
    }

    public function testTheCoreAndProjectBundlesAreRegistered(): void
    {
        // Symfony wraps bundles it registers itself (ServicesBundle, ConsoleBundle) in an adapter: compare the bundles inside.
        $bundles = array_map(
            static fn (BundleInterface $bundle): string => ($bundle instanceof BundleAdapter ? $bundle->getInnerBundle() : $bundle)::class,
            $this->kernel()->getBundles(),
        );

        foreach (SightPressCoreBundle::requiredBundles() as $required) {
            self::assertContains($required, $bundles);
        }
        self::assertContains(FixtureBundle::class, $bundles);
        self::assertNotContains(ProdOnlyBundle::class, $bundles);
        self::assertCount(\count(array_unique($bundles)), $bundles);
    }

    public function testAKnownHostIsServedWithItsSiteAndTheSecurityHeaders(): void
    {
        $response = $this->request('http://www.example.org/');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<h1>01JC0000000000000000000001</h1>', (string) $response->getContent());
        $policy = (string) $response->headers->get('Content-Security-Policy');
        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]{24})'/", $policy);
        self::assertSame(1, preg_match("/'nonce-([^']+)'/", $policy, $nonce));
        self::assertStringContainsString('nonce="'.$nonce[1].'"', (string) $response->getContent(), 'The page and the header carry the same nonce.');
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertFalse($response->headers->has('Server-Timing'), 'Server-Timing is off outside debug mode.');
    }

    public function testAnUnknownHostGetsAPlain404BeforeAnySiteCodeRuns(): void
    {
        $response = $this->request('http://attacker.example/');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame("Not found\n", $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertTrue($response->headers->has('Content-Security-Policy'));
    }

    public function testAnInvalidHostHeaderGets404(): void
    {
        $request = Request::create('http://example.org/');
        $request->headers->set('Host', 'example.org/evil');

        self::assertSame(404, $this->kernel()->handle($request)->getStatusCode());
    }

    public function testDevelopmentHostsWorkOnlyInDebugMode(): void
    {
        self::assertSame(404, $this->request('http://localhost/cached')->getStatusCode());

        $response = $this->request('http://localhost/cached', true);
        self::assertSame(200, $response->getStatusCode());
        self::assertMatchesRegularExpression('/^app;dur=/', (string) $response->headers->get('Server-Timing'));
    }

    public function testACachedPublicPageGetsNoNonce(): void
    {
        $response = $this->request('http://example.org/cached');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('nonce-', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testAdminPagesCannotBeFramed(): void
    {
        self::assertStringContainsString("frame-ancestors 'none'", (string) $this->request('http://example.org/admin')->headers->get('Content-Security-Policy'));
    }

    public function testEveryRouteDeclaresWhoMayReachIt(): void
    {
        $router = $this->container($this->kernel())->get('router');
        \assert($router instanceof RouterInterface);

        self::assertGreaterThanOrEqual(3, \count($router->getRouteCollection()));
        self::assertSame([], new RouteAccessAuditor()->violations($router->getRouteCollection()));
    }

    public function testCoreServicesAreWired(): void
    {
        $container = $this->container($this->kernel());

        self::assertInstanceOf(ConfigureMediaStorageHandler::class, $container->get(ConfigureMediaStorageHandler::class));
        self::assertInstanceOf(ListInstalledPlugins::class, $container->get(ListInstalledPlugins::class));
        self::assertInstanceOf(RedactionProcessor::class, $container->get(RedactionProcessor::class));
        self::assertInstanceOf(SiteContext::class, $container->get(SiteContext::class));
        self::assertFalse($container->has(ServerTimingListener::class));
    }

    public function testTheInstallerAndPluginCommandsAreRegistered(): void
    {
        $application = new Application($this->kernel());
        $application->setAutoExit(false);

        foreach (['sightpress:install', 'sightpress:plugin:list', 'sightpress:plugin:install'] as $name) {
            self::assertTrue($application->has($name), $name.' is registered.');
        }

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'sightpress:plugin:list']);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Installed plugins: none.', $tester->getDisplay());
        self::assertStringContainsString('1. Articles (articles), not installed.', $tester->getDisplay(), 'Names come from the core catalogue.');

        $tester->run(['command' => 'sightpress:install', '--from-composer' => true], ['interactive' => false]);
        self::assertSame(0, $tester->getStatusCode(), 'Under Composer the hook never fails.');
        self::assertStringContainsString('SightPress isn\'t set up yet.', $tester->getDisplay());
    }

    public function testServerTimingCanBeForcedOnOutsideDebugMode(): void
    {
        $kernel = $this->kernel(false, ['performance' => ['server_timing' => true]]);

        self::assertTrue($this->container($kernel)->has(ServerTimingListener::class));
    }

    public function testSessionCookiesAreSecureByDefault(): void
    {
        $container = $this->container($this->kernel());

        $options = $container->getParameter('session.storage.options');
        self::assertIsArray($options);
        self::assertTrue($options['cookie_secure']);
        self::assertTrue($options['cookie_httponly']);
        self::assertSame('lax', $options['cookie_samesite']);
    }

    public function testExplicitTrustedProxiesAreUsed(): void
    {
        $container = $this->container($this->kernel(false, ['trusted_proxies' => ['10.0.0.1', '10.0.0.1', '192.168.0.0/16', 42]]));

        self::assertSame('10.0.0.1,192.168.0.0/16', $container->getParameter('kernel.trusted_proxies'));
    }

    public function testTrustingEveryAddressIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('trusting every address is not allowed');

        $this->kernel(false, ['trusted_proxies' => ['0.0.0.0/0']]);
    }
}
