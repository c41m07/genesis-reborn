<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'app_legal_mentions', defaults: ['page' => 'mentions-legales'], methods: ['GET'])]
    #[Route('/cgu', name: 'app_legal_terms', defaults: ['page' => 'cgu'], methods: ['GET'])]
    #[Route('/confidentialite', name: 'app_legal_privacy', defaults: ['page' => 'confidentialite'], methods: ['GET'])]
    #[Route('/cookies', name: 'app_legal_cookies', defaults: ['page' => 'cookies'], methods: ['GET'])]
    public function show(string $page, #[Autowire('%env(LEGAL_PUBLISHER_NAME)%')] string $publisherName): Response
    {
        $response = $this->render('legal/page.html.twig', [
            'page' => $page,
            'publisherName' => trim($publisherName),
            'pageTitle' => AdminLegalPreviewController::PAGES[$page],
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, follow');
        return $response;
    }
}
