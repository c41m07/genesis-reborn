<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Guild\{GuildCommandReturn, GuildHallConvoyRecovery};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class GuildHallConvoyRecoveryController extends AbstractController
{
    #[Route('/flottes/quai/hall/{id}/retour', name: 'app_fleet_return_hall_station', requirements: ['id' => '[1-9][0-9]*'], methods: ['POST'])]
    public function returnStation(int $id, Request $request, UniverseContext $context, GuildCommandReturn $return): Response
    {
        if (!$this->isCsrfTokenValid('hall_station_return_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez les flottes à quai.');
        }
        try {
            if ($request->request->getString('confirm') !== '1') {
                throw new \DomainException('Confirmez le départ vers la colonie d’origine.');
            }
            if ($return->depart($context->membership(), $id, $request->request->getString('request_key'))) {
                $this->addFlash('success', 'Votre flotte quitte le Centre pour sa colonie d’origine.');
            }
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_fleet_holds', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/flottes/quai/hall/{id}/poser', name: 'app_fleet_recover_hall_convoy', requirements: ['id' => '[1-9][0-9]*'], methods: ['POST'])]
    public function land(int $id, Request $request, UniverseContext $context, GuildHallConvoyRecovery $recovery): Response
    {
        if (!$this->isCsrfTokenValid('hall_convoy_recover_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez les flottes à quai.');
        }
        try {
            if ($recovery->land($context->membership(), $id, $request->request->getString('request_key'))) {
                $this->addFlash('success', 'Le convoi a rejoint votre colonie. Le surplus reste à bord.');
            }
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_fleet_holds', status: Response::HTTP_SEE_OTHER);
    }
}
