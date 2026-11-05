<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Install\Domain;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Install\Domain\InstallState;

final class InstallStateTest extends TestCase
{
    public function testCompletingAStepClearsAnEarlierFailure(): void
    {
        $state = new InstallState()->withFailure('database', 'Could not connect.')->withCompleted('database');

        self::assertTrue($state->isCompleted('database'));
        self::assertNull($state->failedStep);
        self::assertNull($state->failureMessage);
    }

    public function testCompletingAStepTwiceRecordsItOnce(): void
    {
        $state = new InstallState()->withCompleted('requirements')->withCompleted('requirements');

        self::assertSame(['requirements'], $state->completed);
    }

    public function testAFailureKeepsProgressAndAnswers(): void
    {
        $state = new InstallState()->withCompleted('requirements')->withAnswer('db_host', 'db')->withFailure('database', 'Could not connect.');

        self::assertSame(['requirements'], $state->completed);
        self::assertSame(['db_host' => 'db'], $state->answers);
        self::assertSame('database', $state->failedStep);
        self::assertSame('Could not connect.', $state->failureMessage);
    }

    public function testAnswersCanBeReplaced(): void
    {
        $state = new InstallState()->withAnswer('db_host', 'a')->withAnswer('db_host', 'b');

        self::assertSame(['db_host' => 'b'], $state->answers);
    }

    public function testInstalledAtClearsTheFailure(): void
    {
        $state = new InstallState()->withFailure('database', 'x')->withInstalledAt('2026-11-05T10:00:00+00:00');

        self::assertSame('2026-11-05T10:00:00+00:00', $state->installedAt);
        self::assertNull($state->failedStep);
    }

    public function testRestartForgetsProgressButKeepsAnswers(): void
    {
        $state = new InstallState()->withCompleted('requirements')->withAnswer('db_host', 'db')->withInstalledAt('now')->restarted();

        self::assertSame([], $state->completed);
        self::assertSame(['db_host' => 'db'], $state->answers);
        self::assertNull($state->installedAt);
    }

    public function testHasCompletedAll(): void
    {
        $state = new InstallState()->withCompleted('a')->withCompleted('b');

        self::assertTrue($state->hasCompletedAll(['a', 'b']));
        self::assertTrue($state->hasCompletedAll([]));
        self::assertFalse($state->hasCompletedAll(['a', 'c']));
    }
}
