<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App\Controller;

use SightPress\Plugin\Core\Port\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/admin/fixture', name: 'admin_fixture', defaults: ['_access' => 'ROLE_ADMIN'])]
    public function admin(): Response
    {
        return new Response('admin');
    }

    /**
     * A test-only admin form for the layout's error summary, inline errors and
     * flash region (and the M0 screen-reader walkthrough). It saves nothing.
     */
    #[Route('/admin/fixture-form', name: 'admin_fixture_form', defaults: ['_access' => 'admin.access'], methods: ['GET', 'POST'])]
    public function form(Request $request): Response
    {
        $title = trim((string) $request->request->get('title', ''));
        $errors = [];
        if ($request->isMethod('POST')) {
            if ('' === $title) {
                $errors[] = ['field' => 'title', 'message' => 'Enter a title'];
            } else {
                $this->addFlash('success', \sprintf('Saved "%s".', $title));
            }
        }

        return $this->render('admin/form.html.twig', ['errors' => $errors, 'title' => $title], new Response(status: [] === $errors ? 200 : 422));
    }
}
