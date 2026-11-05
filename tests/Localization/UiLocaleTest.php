<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Localization;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Localization\Application\UiLocale;
use SightPress\Core\Localization\Infrastructure\Http\UiLocaleListener;
use SightPress\Core\Localization\UI\Twig\UiLocaleExtension;
use SightPress\Plugin\Core\Value\Locale;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class UiLocaleTest extends TestCase
{
    public function testNegotiationPrefersAnExactLaunchLocale(): void
    {
        self::assertSame('fr-CA', UiLocale::negotiate(['fr_CA', 'fr-FR'])->lang());
        self::assertSame('es-419', UiLocale::negotiate(['ES-419'])->lang());
    }

    public function testNegotiationFallsBackToTheLanguageThenToTheSource(): void
    {
        self::assertSame('it-IT', UiLocale::negotiate(['nl', 'it-CH'])->lang());
        self::assertSame('en-GB', UiLocale::negotiate([])->lang());
        self::assertSame('en-GB', UiLocale::negotiate(['ja-JP'])->lang());
    }

    public function testALaunchLocaleHasItsOwnCatalogue(): void
    {
        $locale = new UiLocale(Locale::fromString('pt-BR'));

        self::assertSame('pt_BR', $locale->translatorLocale());
        self::assertSame('ltr', $locale->dir());
        self::assertNull($locale->fallbackLang());
        self::assertSame('en', UiLocale::source()->translatorLocale());
    }

    public function testAnRtlTestLocaleFallsBackToTheSourceStrings(): void
    {
        $locale = new UiLocale(Locale::fromString('ar-SA'));

        self::assertSame('rtl', $locale->dir());
        self::assertSame('en-GB', $locale->fallbackLang());
    }

    public function testTheListenerSetsTheRequestLocaleAndKeepsAnEarlierChoice(): void
    {
        $request = Request::create('/admin');
        $request->headers->set('Accept-Language', 'de-CH, de;q=0.8');
        new UiLocaleListener()($this->event($request));
        self::assertSame('de_CH', $request->getLocale());

        $chosen = Request::create('/admin');
        $chosen->headers->set('Accept-Language', 'de-CH');
        $chosen->attributes->set(UiLocale::ATTRIBUTE, new UiLocale(Locale::fromString('fr-FR')));
        new UiLocaleListener()($this->event($chosen));
        self::assertSame('fr', $chosen->getLocale());
    }

    public function testTheListenerIgnoresSubRequests(): void
    {
        $request = Request::create('/admin');
        new UiLocaleListener()($this->event($request, HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($request->attributes->has(UiLocale::ATTRIBUTE));
    }

    public function testTheTwigFunctionDefaultsToTheSourceWithoutARequest(): void
    {
        $extension = new UiLocaleExtension(new RequestStack());

        self::assertSame('sightpress_ui_locale', $extension->getFunctions()[0]->getName());
        self::assertSame(['lang' => 'en-GB', 'dir' => 'ltr', 'fallback_lang' => null], $extension->current());
    }

    private function event(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
    }
}
