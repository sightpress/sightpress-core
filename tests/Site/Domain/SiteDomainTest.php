<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Site\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SightPress\Core\Shared\Domain\Ids;
use SightPress\Core\Site\Domain\Hostname;
use SightPress\Core\Site\Infrastructure\ConfiguredSiteDomainProvider;
use SightPress\Plugin\Core\Value\SiteId;
use Symfony\Component\Uid\Ulid;

final class SiteDomainTest extends TestCase
{
    private const string SITE = '01JC0000000000000000000001';

    /** @return iterable<string, array{?string, ?string}> */
    public static function hosts(): iterable
    {
        yield 'plain' => ['example.org', 'example.org'];
        yield 'upper case and spaces' => ['  Example.ORG ', 'example.org'];
        yield 'with port' => ['example.org:8443', 'example.org'];
        yield 'trailing dot' => ['example.org.', 'example.org'];
        yield 'ipv6 with port' => ['[::1]:8000', '[::1]'];
        yield 'ipv4' => ['127.0.0.1', '127.0.0.1'];
        yield 'null' => [null, null];
        yield 'empty' => ['', null];
        yield 'underscore' => ['bad_host.org', null];
        yield 'leading hyphen' => ['-example.org', null];
        yield 'two ports' => ['example.org:1:2', null];
        yield 'path' => ['example.org/evil', null];
    }

    #[DataProvider('hosts')]
    public function testHostnamesAreNormalised(?string $host, ?string $expected): void
    {
        self::assertSame($expected, Hostname::normalize($host));
    }

    public function testIdsConvertBothWays(): void
    {
        $ulid = new Ulid();

        $siteId = Ids::fromUlid(SiteId::class, $ulid);

        self::assertSame($ulid->toBase32(), $siteId->toString());
        self::assertTrue($ulid->equals(Ids::toUlid($siteId)));
    }

    public function testConfiguredSitesAreFoundByAnyOfTheirHosts(): void
    {
        $provider = new ConfiguredSiteDomainProvider([
            ['id' => self::SITE, 'hosts' => ['Example.org', 'www.example.org:443'], 'default_locale' => 'en-GB', 'locales' => ['en-GB', 'fr-FR']],
        ], ['localhost'], false);

        $site = $provider->findByHost('www.example.org');

        self::assertNotNull($site);
        self::assertSame(self::SITE, $site->siteId->toString());
        self::assertSame('www.example.org', $site->host);
        self::assertSame('en-GB', $site->defaultLocale->toString());
        self::assertSame(['en-GB', 'fr-FR'], array_map(static fn ($l): string => $l->toString(), $site->locales));
        self::assertTrue($provider->isKnownHost('example.org'));
        self::assertFalse($provider->isKnownHost('localhost'), 'Development hosts are only accepted in debug mode.');
        self::assertNull($provider->findByHost('other.org'));
    }

    public function testASiteWithoutLocalesUsesItsDefault(): void
    {
        $provider = new ConfiguredSiteDomainProvider([
            ['id' => self::SITE, 'hosts' => ['example.org'], 'default_locale' => 'pt-PT', 'locales' => []],
        ], [], false);

        self::assertSame(['pt-PT'], array_map(static fn ($l): string => $l->toString(), $provider->findByHost('example.org')->locales ?? []));
    }

    public function testDevelopmentHostsAreKnownInDebugModeButOwnedByNoSite(): void
    {
        $provider = new ConfiguredSiteDomainProvider([], ['localhost', '[::1]', 'not a host'], true);

        self::assertTrue($provider->isKnownHost('localhost'));
        self::assertTrue($provider->isKnownHost('[::1]'));
        self::assertNull($provider->findByHost('localhost'));
    }

    public function testAnInvalidSiteHostIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"bad host" is not a valid host for site '.self::SITE.'.');

        new ConfiguredSiteDomainProvider([
            ['id' => self::SITE, 'hosts' => ['bad host'], 'default_locale' => 'en-GB', 'locales' => []],
        ], [], false);
    }
}
