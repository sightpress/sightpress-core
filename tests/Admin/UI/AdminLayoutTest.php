<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Admin\UI;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SightPress\Core\Admin\UI\Controller\DashboardController;
use SightPress\Core\Tests\Fixtures\App\TestKernel;
use SightPress\Plugin\Core\TestKit\AccessibleHtmlAssertions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** The base admin layout and the placeholder dashboard (M0 step 3c). */
final class AdminLayoutTest extends TestCase
{
    use AccessibleHtmlAssertions;

    /** @var list<TestKernel> */
    private array $kernels = [];

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
    }

    /**
     * @param list<string>|null     $granted
     * @param array<string, string> $post    form fields; given fields make it a POST
     */
    private function get(string $path, ?array $granted = [DashboardController::ACCESS], string $language = 'en-GB', ?array $post = null): Response
    {
        $kernel = new TestKernel('test', false, [], $granted);
        $kernel->boot();
        $this->kernels[] = $kernel;
        $request = Request::create('http://example.org'.$path, null === $post ? 'GET' : 'POST', $post ?? []);
        $request->headers->set('Accept-Language', $language);

        return $kernel->handle($request);
    }

    public function testTheDashboardHasTheSameStructureAsEveryScreen(): void
    {
        $response = $this->get('/admin');
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        $this->assertPageStructure($html);
        $this->assertLiveRegion($html);
        $this->assertFocusTarget($html, 'main');
        self::assertStringContainsString('<html lang="en-GB" dir="ltr">', $html);
        self::assertStringContainsString('<title>Dashboard: SightPress admin</title>', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringNotContainsString('sp-error-summary', $html, 'With no errors there is no error summary.');
    }

    public function testAdminPagesAreNeverStoredByASharedCache(): void
    {
        $cacheControl = (string) $this->get('/admin')->headers->get('Cache-Control');

        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
    }

    public function testTheDashboardIsRefusedWithoutTheAdminAccessAttribute(): void
    {
        self::assertSame(403, $this->get('/admin', [])->getStatusCode());
    }

    public function testTheDashboardIsRefusedWhenNoAuthorizationCheckerExists(): void
    {
        self::assertSame(403, $this->get('/admin', null)->getStatusCode());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function locales(): iterable
    {
        yield 'source' => ['en-GB', 'en-GB', 'Dashboard'];
        yield 'regional override' => ['pt-BR,pt;q=0.8', 'pt-BR', 'Painel'];
        yield 'language in another region' => ['de-AT', 'de-DE', 'Übersicht'];
        yield 'unsupported language' => ['nl-NL', 'en-GB', 'Dashboard'];
    }

    #[DataProvider('locales')]
    public function testTheInterfaceFollowsTheNegotiatedLaunchLocale(string $accept, string $lang, string $h1): void
    {
        $html = (string) $this->get('/admin', language: $accept)->getContent();

        self::assertStringContainsString(\sprintf('<html lang="%s" dir="ltr">', $lang), $html);
        self::assertMatchesRegularExpression('#<h1>'.preg_quote($h1, '#').'</h1>#u', $html);
        self::assertStringNotContainsString('<body class="sp-admin" lang=', $html);
    }

    public function testAFormWithoutErrorsHasNoErrorSummary(): void
    {
        $html = (string) $this->get('/admin/fixture-form')->getContent();

        $this->assertPageStructure($html);
        $this->assertFieldLabelled($html, 'title');
        $this->assertDescribedBy($html, 'title', 'The name shown at the top of the page.');
        self::assertStringNotContainsString('sp-error-summary', $html);
        self::assertStringNotContainsString('aria-invalid', $html);
    }

    public function testTheErrorSummaryTakesFocusAndLinksToEachField(): void
    {
        $response = $this->get('/admin/fixture-form', post: ['title' => ' ']);
        $html = (string) $response->getContent();

        self::assertSame(422, $response->getStatusCode());
        $this->assertPageStructure($html);
        $this->assertErrorSummary($html, ['title' => 'Enter a title']);
        $this->assertFieldLabelled($html, 'title');
        $this->assertDescribedBy($html, 'title', 'Enter a title');
        $this->assertDescribedBy($html, 'title', 'The name shown at the top of the page.');
        self::assertStringContainsString('1 problem needs fixing', $html);
    }

    public function testFlashMessagesAreRenderedIntoThePoliteLiveRegion(): void
    {
        $html = (string) $this->get('/admin/fixture-form', post: ['title' => 'Opening hours'])->getContent();

        self::assertMatchesRegularExpression('#<div class="sp-flash" role="status" aria-live="polite"[^>]*>\s*<p class="sp-flash__message sp-flash__message--success">Saved &quot;Opening hours&quot;.</p>#', $html);
        $this->assertLiveRegion($html);
    }

    /** @return iterable<string, array{string}> */
    public static function rtlTestLocales(): iterable
    {
        yield 'Hebrew (Israel)' => ['he-IL'];
        yield 'Arabic (Saudi Arabia)' => ['ar-SA'];
    }

    /** RTL test locales have no catalogue: the page keeps lang and dir, the English strings say lang="en-GB". */
    #[DataProvider('rtlTestLocales')]
    public function testRtlTestLocalesKeepTheirDirectionAndMarkTheEnglishFallback(string $locale): void
    {
        $html = (string) $this->get('/admin?ui_locale='.$locale)->getContent();

        $this->assertPageStructure($html);
        self::assertStringContainsString(\sprintf('<html lang="%s" dir="rtl">', $locale), $html);
        self::assertStringContainsString('<body class="sp-admin" lang="en-GB">', $html);
        self::assertStringContainsString('<h1>Dashboard</h1>', $html);
    }
}
