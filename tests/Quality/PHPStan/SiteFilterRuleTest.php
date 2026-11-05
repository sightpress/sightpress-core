<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Quality\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use SightPress\Core\Quality\PHPStan\SiteFilterRule;

/** @extends RuleTestCase<SiteFilterRule> */
final class SiteFilterRuleTest extends RuleTestCase
{
    private const string MESSAGE = 'The site filter is turned off outside a super-admin code path. Only code in SightPress\Core\Admin\SuperAdmin\ may do this, through SiteFilterSwitch::runWithoutSiteFilter() with a reason and an isolation test.';

    protected function getRule(): Rule
    {
        return new SiteFilterRule();
    }

    public function testTurningTheFilterOffOutsideSuperAdminCodeIsRefused(): void
    {
        $this->analyse([__DIR__.'/../Fixtures/SiteFilter/Forbidden.php'], [
            [self::MESSAGE, 19],
            [self::MESSAGE, 20],
            [self::MESSAGE, 21],
            [self::MESSAGE, 22],
        ]);
    }

    public function testSuperAdminCodeMayTurnItOff(): void
    {
        $this->analyse([__DIR__.'/../Fixtures/SiteFilter/Allowed.php'], []);
    }

    public function testTheSwitchItselfMayTurnItOff(): void
    {
        $this->analyse([__DIR__.'/../../../src/Site/Infrastructure/Doctrine/SiteFilterSwitch.php'], []);
    }
}
