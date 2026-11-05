<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Install\Infrastructure;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Install\Domain\DatabaseParameters;
use SightPress\Core\Install\Domain\InstallState;
use SightPress\Core\Install\Infrastructure\DbalDatabaseProbe;
use SightPress\Core\Install\Infrastructure\FileInstallStateStore;
use SightPress\Core\Install\Infrastructure\ProjectConfigWriter;
use SightPress\Core\Install\Infrastructure\SystemRequirementProbe;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\Filesystem\Filesystem;

final class InstallInfrastructureTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sightpress-install-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @chmod($this->dir, 0o755);
        new Filesystem()->remove($this->dir);
    }

    public function testTheStateRoundTripsThroughItsFile(): void
    {
        $store = new FileInstallStateStore($this->dir.'/var/sightpress/install-state.json');
        self::assertEquals(new InstallState(), $store->load(), 'No file yet means a fresh install.');

        $state = new InstallState(['requirements'], ['db_host' => 'db'], 'environment', 'Database: no route', '2026-11-05T10:00:00+00:00');
        $store->save($state);

        self::assertEquals($state, $store->load());
        self::assertSame('0640', substr(\sprintf('%o', fileperms($this->dir.'/var/sightpress/install-state.json')), -4));
    }

    public function testACorruptOrOddStateFileIsReadSafely(): void
    {
        $file = $this->dir.'/state.json';
        $store = new FileInstallStateStore($file);

        file_put_contents($file, 'not json');
        self::assertEquals(new InstallState(), $store->load());

        file_put_contents($file, json_encode(['completed' => ['a', 3], 'answers' => ['k' => 'v', 'n' => 4, 7 => 'x'], 'failedStep' => 5]));
        self::assertEquals(new InstallState(['a'], ['k' => 'v']), $store->load());
    }

    public function testAnUnwritableFolderIsReportedInWords(): void
    {
        if (0 === posix_geteuid()) {
            self::markTestSkipped('root can write anywhere.');
        }
        chmod($this->dir, 0o500);

        $this->expectExceptionMessage('Check that var/ is writable.');
        new FileInstallStateStore($this->dir.'/var/state.json')->save(new InstallState());
    }

    public function testAnUnwritableStateFileIsReportedInWords(): void
    {
        if (0 === posix_geteuid()) {
            self::markTestSkipped('root can write anywhere.');
        }
        chmod($this->dir, 0o500);

        $this->expectExceptionMessage('could not save its progress');
        new FileInstallStateStore($this->dir.'/state.json')->save(new InstallState());
    }

    public function testDevelopmentSettingsAndSecretsGoToEnvLocal(): void
    {
        file_put_contents($this->dir.'/.env.local', "# kept\nOTHER=1\nexport APP_ENV=dev\nMAILER_DSN='smtp://old'\n");
        $writer = new ProjectConfigWriter($this->dir);

        $written = $writer->write(['APP_ENV' => 'dev', 'SIGHTPRESS_MAIL_FROM' => 'Team <team@example.org>'], ['APP_SECRET' => 'abc', 'MAILER_DSN' => "smtp://u:p'\$x@host"], false);

        self::assertSame(['.env.local'], $written);
        $content = (string) file_get_contents($this->dir.'/.env.local');
        self::assertStringContainsString("# kept\nOTHER=1\nAPP_ENV=dev\n", $content);
        self::assertStringContainsString("SIGHTPRESS_MAIL_FROM='Team <team@example.org>'", $content);
        self::assertSame('Team <team@example.org>', $writer->current('SIGHTPRESS_MAIL_FROM'));
        self::assertSame("smtp://u:p'\$x@host", $writer->currentSecret('MAILER_DSN'));
        self::assertSame('abc', $writer->current('APP_SECRET'));
        self::assertNull($writer->current('MISSING'));
    }

    public function testANewEnvFileIsPrivateAndSaysWhatItIs(): void
    {
        new ProjectConfigWriter($this->dir)->write(['APP_ENV' => 'dev'], [], false);

        self::assertStringStartsWith("# This machine's settings, written by sightpress:install.", (string) file_get_contents($this->dir.'/.env.local'));
        self::assertSame('0600', substr(\sprintf('%o', fileperms($this->dir.'/.env.local')), -4));
    }

    public function testDoubleQuotedValuesAreRead(): void
    {
        file_put_contents($this->dir.'/.env.local', "SIGHTPRESS_MAIL_FROM=\"a\\tb\"\n");

        self::assertSame("a\tb", new ProjectConfigWriter($this->dir)->current('SIGHTPRESS_MAIL_FROM'));
    }

    public function testProductionSecretsAreSealedInTheVaultAndLeaveEnvLocal(): void
    {
        file_put_contents($this->dir.'/.env.local', "DATABASE_URL=postgresql://old\n");
        $writer = new ProjectConfigWriter($this->dir);

        $written = $writer->write(['APP_ENV' => 'prod'], ['DATABASE_URL' => 'postgresql://u:secret@db:5432/sp'], true);

        self::assertSame(['config/secrets/prod', '.env.local'], $written);
        $content = (string) file_get_contents($this->dir.'/.env.local');
        self::assertStringNotContainsString('DATABASE_URL', $content, 'A value left in .env.local would override the vault.');
        self::assertStringNotContainsString('secret', $content);
        self::assertSame('postgresql://u:secret@db:5432/sp', new SodiumVault($this->dir.'/config/secrets/prod')->reveal('DATABASE_URL'));
        self::assertSame('postgresql://u:secret@db:5432/sp', $writer->currentSecret('DATABASE_URL'));
    }

    public function testAnUnwritableProjectIsReportedInWords(): void
    {
        if (0 === posix_geteuid()) {
            self::markTestSkipped('root can write anywhere.');
        }
        chmod($this->dir, 0o500);

        $this->expectExceptionMessage('The installer could not write .env.local.');
        new ProjectConfigWriter($this->dir)->write(['APP_ENV' => 'dev'], [], false);
    }

    public function testTheSystemProbeReadsThisMachine(): void
    {
        $probe = new SystemRequirementProbe();

        self::assertSame(\PHP_VERSION, $probe->phpVersion());
        self::assertTrue($probe->hasExtension('json'));
        self::assertFalse($probe->hasExtension('no_such_extension'));
        self::assertTrue($probe->isWritable($this->dir.'/not/yet/created'), 'The nearest existing parent decides.');
        self::assertTrue($probe->hasExecutable('php') || $probe->hasExecutable(basename(\PHP_BINARY)));
        self::assertFalse($probe->hasExecutable('no-such-tool-'.bin2hex(random_bytes(3))));

        file_put_contents($this->dir.'/file', '');
        self::assertFalse($probe->isWritable($this->dir.'/file'), 'A file is not a folder.');
    }

    public function testAnUnreachableDatabaseIsReportedWithoutThePassword(): void
    {
        $parameters = new DatabaseParameters('127.0.0.1', 1, 'sightpress', 'sightpress', 'pa ss/word');
        $probe = new DbalDatabaseProbe();

        $check = $probe->check($parameters);
        self::assertFalse($check->reachable);
        self::assertFalse($check->exists);
        self::assertNotSame('', (string) $check->error);
        self::assertStringNotContainsString('pa ss/word', (string) $check->error);
        self::assertStringNotContainsString(rawurlencode('pa ss/word'), (string) $check->error);

        $error = $probe->create($parameters);
        self::assertNotNull($error);
        self::assertStringNotContainsString('pa ss/word', $error);
    }

    public function testDatabaseParametersBuildTheUrlAndDescribeTheServerWithoutThePassword(): void
    {
        $parameters = new DatabaseParameters('::1', 5433, 'my db', 'user@x', 'p@ss');

        self::assertSame('postgresql://user%40x:p%40ss@[::1]:5433/my%20db?serverVersion=17&charset=utf8', $parameters->url());
        self::assertSame('my db on ::1 port 5433 as user@x', $parameters->describe());
    }
}
