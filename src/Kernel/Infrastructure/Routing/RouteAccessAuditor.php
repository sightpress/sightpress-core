<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Routing;

use Symfony\Component\Routing\RouteCollection;

/**
 * Every route must state who may reach it, with the "_access" route default:
 * "public" for anonymous pages, or the name of the voter attribute that guards
 * it. The test suite runs this over every route, so a route with no access
 * rule fails the build. Framework routes (names starting with "_") are skipped.
 */
final class RouteAccessAuditor
{
    public const string DEFAULT_KEY = '_access';

    /** @return list<string> one sentence per route without an access rule */
    public function violations(RouteCollection $routes): array
    {
        $violations = [];
        foreach ($routes as $name => $route) {
            if (str_starts_with($name, '_')) {
                continue;
            }
            $access = $route->getDefault(self::DEFAULT_KEY);
            if (!\is_string($access) || '' === trim($access)) {
                $violations[] = \sprintf('Route "%s" (%s) has no access rule. Add defaults: {%s: public} or the voter attribute that guards it.', $name, $route->getPath(), self::DEFAULT_KEY);
            }
        }

        return $violations;
    }
}
