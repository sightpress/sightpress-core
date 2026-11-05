<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Site\Infrastructure;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use SightPress\Core\Site\Domain\SiteDomain;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilter;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilterConfigurator;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilterSwitch;
use SightPress\Core\Site\Infrastructure\RequestSiteContext;
use SightPress\Core\Tests\Fixtures\App\Entity\InstallNote;
use SightPress\Core\Tests\Fixtures\App\Entity\Note;
use SightPress\Core\Tests\Fixtures\App\TestKernel;
use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SiteIsolationTest extends TestCase
{
    private TestKernel $kernel;
    private EntityManagerInterface $em;
    private SiteId $siteA;
    private SiteId $siteB;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel();
        $this->kernel->boot();
        $em = $this->kernel->getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;
        new SchemaTool($em)->createSchema($em->getMetadataFactory()->getAllMetadata());

        $this->siteA = SiteId::generate();
        $this->siteB = SiteId::generate();
        $em->persist(new Note($this->siteA, 'A1'));
        $em->persist(new Note($this->siteA, 'A2'));
        $em->persist(new Note($this->siteB, 'B1'));
        $em->persist(new InstallNote('install-wide'));
        $em->flush();
        $em->clear();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    /** @return list<string> */
    private function noteTitles(): array
    {
        $titles = array_map(static fn (Note $note): string => $note->title, $this->em->getRepository(Note::class)->findAll());
        sort($titles);

        return $titles;
    }

    public function testTheFilterIsOnByDefaultAndFailsClosedWithNoSite(): void
    {
        self::assertTrue($this->em->getFilters()->isEnabled(SiteFilter::NAME));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ran with no current site');

        $this->em->getRepository(Note::class)->findAll();
    }

    public function testAQueryNeverReturnsAnotherSitesRows(): void
    {
        new SiteFilterConfigurator()->apply($this->em, $this->siteA);
        self::assertSame(['A1', 'A2'], $this->noteTitles());

        $this->em->clear();
        new SiteFilterConfigurator()->apply($this->em, $this->siteB);
        self::assertSame(['B1'], $this->noteTitles());
        self::assertSame(1, $this->em->getRepository(Note::class)->count([]));
    }

    public function testInstallWideEntitiesAreNotFiltered(): void
    {
        self::assertCount(1, $this->em->getRepository(InstallNote::class)->findAll());
    }

    public function testTheConfiguratorTurnsADisabledFilterBackOn(): void
    {
        $this->em->getFilters()->disable(SiteFilter::NAME);

        new SiteFilterConfigurator()->apply($this->em, $this->siteB);

        self::assertTrue($this->em->getFilters()->isEnabled(SiteFilter::NAME));
        self::assertSame(['B1'], $this->noteTitles());
    }

    public function testAnInvalidSiteIdIsRefused(): void
    {
        $this->em->getFilters()->getFilter(SiteFilter::NAME)->setParameter(SiteFilter::PARAMETER, 'not-a-ulid', 'string');

        $this->expectExceptionMessage('The current site id is not a valid ULID.');

        $this->em->getRepository(Note::class)->findAll();
    }

    public function testTheSwitchTurnsTheFilterOffForOneCallbackAndLogsWhy(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, array<array-key, mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [(string) $message, $context];
            }
        };
        new SiteFilterConfigurator()->apply($this->em, $this->siteA);

        $all = new SiteFilterSwitch($this->em, $logger)->runWithoutSiteFilter('Super-admin site list', $this->noteTitles(...));

        self::assertSame(['A1', 'A2', 'B1'], $all);
        self::assertSame([['Site filter turned off for a super-admin code path.', ['reason' => 'Super-admin site list']]], $logger->records);
        $this->em->clear();
        self::assertSame(['A1', 'A2'], $this->noteTitles(), 'The filter is back on after the callback.');
    }

    public function testTheSwitchNeedsAReason(): void
    {
        $this->expectExceptionMessage('Turning off the site filter needs a stated reason.');

        new SiteFilterSwitch($this->em, new \Psr\Log\NullLogger())->runWithoutSiteFilter(' ', static fn (): null => null);
    }

    public function testTheSwitchJustRunsTheCallbackWhenTheFilterIsAlreadyOff(): void
    {
        $this->em->getFilters()->disable(SiteFilter::NAME);

        self::assertSame(['A1', 'A2', 'B1'], new SiteFilterSwitch($this->em, new \Psr\Log\NullLogger())->runWithoutSiteFilter('test', $this->noteTitles(...)));
    }

    public function testTheSiteContextReadsTheMainRequest(): void
    {
        $stack = new RequestStack();
        $context = new RequestSiteContext($stack);
        self::assertFalse($context->hasSite());

        $request = Request::create('/');
        $request->attributes->set(RequestSiteContext::ATTRIBUTE, new SiteDomain($this->siteA, 'example.org', Locale::fromString('en-GB'), [Locale::fromString('en-GB'), Locale::fromString('de-CH')]));
        $stack->push($request);

        self::assertTrue($context->hasSite());
        self::assertTrue($context->siteId()->equals($this->siteA));
        self::assertSame('en-GB', $context->defaultLocale()->toString());
        self::assertCount(2, $context->locales());
    }

    public function testTheSiteContextRefusesARequestWithNoSite(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        $this->expectExceptionMessage('There is no current site');

        new RequestSiteContext($stack)->siteId();
    }
}
