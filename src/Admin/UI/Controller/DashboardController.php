<?php

declare(strict_types=1);

namespace SightPress\Core\Admin\UI\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * The admin start page. In M0 it is the placeholder that the base layout and
 * the Playwright + axe gate are tested on. Only people the "admin.access"
 * voter attribute grants may see it.
 */
final readonly class DashboardController
{
    public const string ACCESS = 'admin.access';

    public function __construct(private Environment $twig)
    {
    }

    #[Route('/admin', name: 'sightpress_admin_dashboard', defaults: ['_access' => self::ACCESS], methods: ['GET'])]
    public function __invoke(): Response
    {
        $response = new Response($this->twig->render('@SightPressCore/admin/dashboard/index.html.twig'));
        // Admin pages are per person: never stored by a shared cache.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
