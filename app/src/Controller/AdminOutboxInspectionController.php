<?php

namespace App\Controller;

use App\Service\Admin\OutboxRecoveryInspection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminOutboxInspectionController extends AbstractController
{
    #[Route('/admin/exploitation/relais/{id}', name: 'app_admin_outbox_inspection', requirements: ['id' => '[0-9]+'], methods: ['GET'])]
    public function show(int $id, OutboxRecoveryInspection $inspection): Response
    {
        $result = $inspection->inspect($id);
        if ($result === null) {
            throw $this->createNotFoundException('Relais absent du relevé actuel ; son exécution ne peut pas être déduite.');
        }

        return $this->render('admin/outbox_inspection.html.twig', ['inspection' => $result]);
    }
}
