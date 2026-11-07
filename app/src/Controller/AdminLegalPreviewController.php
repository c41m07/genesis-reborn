<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminLegalPreviewController extends AbstractController
{
    public const PAGES = [
        'cgu' => "Conditions générales d’utilisation",
        'mentions-legales' => 'Mentions légales',
        'confidentialite' => 'Politique de confidentialité',
        'cookies' => 'Cookies et connexion',
    ];

    #[Route('/admin/juridique/{page}', name: 'app_admin_legal_preview', defaults: ['page' => 'cgu'], methods: ['GET'])]
    public function preview(string $page): Response
    {
        if (!isset(self::PAGES[$page])) {
            throw $this->createNotFoundException('Page inconnue.');
        }

        $response = $this->render('admin/legal/preview.html.twig', [
            'pages' => self::PAGES,
            'page' => $page,
            'pageTitle' => self::PAGES[$page],
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }
}
