<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App\Controller;

use SightPress\Plugin\Core\Port\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/', name: 'page_home', defaults: ['_access' => 'public'])]
    public function home(SiteContext $site): Response
    {
        return $this->render('page.html.twig', ['site' => $site->siteId()->toString()]);
    }

    #[Route('/cached', name: 'page_cached', defaults: ['_access' => 'public'])]
    public function cached(): Response
    {
        $response = new Response('cached');
        $response->setPublic();
        $response->setSharedMaxAge(60);

        return $response;
    }

    #[Route('/admin', name: 'admin_home', defaults: ['_access' => 'ROLE_ADMIN'])]
    public function admin(): Response
    {
        return new Response('admin');
    }
}
