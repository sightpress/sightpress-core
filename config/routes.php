<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// Controllers carry their own paths (admin ones start with /admin) and must declare
// an access rule through the "_access" route default (see RouteAccessAuditor).
return static function (RoutingConfigurator $routes): void {
    foreach (['Admin', 'Front', 'Api'] as $area) {
        $directory = __DIR__.'/../src/'.$area.'/UI/Controller';
        if ([] !== (glob($directory.'/*.php') ?: [])) {
            $routes->import($directory.'/', 'attribute');
        }
    }
};
