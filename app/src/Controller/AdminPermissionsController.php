<?php

namespace App\Controller;

use App\Security\AdminCapability;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[IsGranted(AdminCapability::PLAYER_VIEW)]
final class AdminPermissionsController extends AbstractController
{
    #[Route('/admin/permissions', name: 'app_admin_permissions', methods: ['GET'])]
    public function index(): Response
    {
        // Cet écran documente les contrôles existants sans écrire ni attribuer de droits.
        return $this->render('admin/permissions.html.twig', [
            'capabilities' => AdminCapability::definitions(),
        ]);
    }
}
