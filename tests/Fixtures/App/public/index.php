<?php

declare(strict_types=1);

// Test-only front controller for the Playwright + axe gate (npm run test:browser).
// It serves the fixture app in debug mode, so the development hosts (127.0.0.1,
// localhost) answer and assets are served from assets/ without a build step.
// The fixture authorization checker grants the admin, standing in for sign-in (M1).

use SightPress\Core\Admin\UI\Controller\DashboardController;
use SightPress\Core\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$kernel = new TestKernel('test', true, [], [DashboardController::ACCESS, 'ROLE_ADMIN']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
