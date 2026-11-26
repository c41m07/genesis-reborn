<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Boost\InventoryGiftNotices;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class InventoryGiftNoticeController extends AbstractController
{
    #[Route('/inventaire/cadeaux/consultation', name: 'app_inventory_gift_notice_consult', methods: ['POST'])]
    public function consult(Request $request, UniverseContext $context, InventoryGiftNotices $notices): Response
    {
        $membership = $context->membership();
        $through = $request->request->getString('through');
        $page = $request->request->getString('page');
        $returnRoute = match ($page) {
            'boxes' => 'app_inventory_boxes',
            'pending' => 'app_inventory_pending',
            'boosts' => 'app_boosts',
            default => throw $this->createNotFoundException(),
        };
        if (strlen($through) > 500 || !$this->isCsrfTokenValid('inventory-gifts-'.$membership->id.'-'.$page.'-'.$through, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez l’inventaire.');
        }

        // L'acquittement est distinct de l'ouverture ou de l'utilisation d'un objet.
        try {
            $limits = json_decode($through, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez l’inventaire.');
        }
        if (!is_array($limits)) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez l’inventaire.');
        }
        $notices->acknowledge($membership, $limits);

        return $this->redirectToRoute($returnRoute, [], Response::HTTP_SEE_OTHER);
    }
}
