<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Kernel\Infrastructure\Routing;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Kernel\Infrastructure\Routing\RouteAccessAuditor;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteAccessAuditorTest extends TestCase
{
    public function testRoutesWithoutAnAccessRuleAreReportedInWords(): void
    {
        $routes = new RouteCollection();
        $routes->add('public_page', new Route('/', ['_access' => 'public']));
        $routes->add('admin_users', new Route('/admin/users', ['_access' => 'IDENTITY_MANAGE_USERS']));
        $routes->add('forgotten', new Route('/forgotten'));
        $routes->add('blank', new Route('/blank', ['_access' => ' ']));
        $routes->add('_wdt', new Route('/_wdt/{token}'));

        self::assertSame([
            'Route "forgotten" (/forgotten) has no access rule. Add defaults: {_access: public} or the voter attribute that guards it.',
            'Route "blank" (/blank) has no access rule. Add defaults: {_access: public} or the voter attribute that guards it.',
        ], new RouteAccessAuditor()->violations($routes));
    }
}
