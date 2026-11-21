<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PirateCampaign\PiratePilotLocalReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[When(env: 'dev')]
final class PiratePilotLocalController extends AbstractController
{
    #[Route('/evenements/campagne-pirate/{missionNumber}', name: 'app_dev_pirate_pilot',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['GET'], env: 'dev')]
    public function dossier(PiratePilotLocalReader $reader, ?int $missionNumber = null): Response
    {
        $dossier = $reader->dossier($missionNumber);
        $headers = ['Cache-Control' => 'private, no-store'];
        if ($dossier === null) {
            return new Response('Page introuvable.', Response::HTTP_NOT_FOUND, $headers);
        }

        return $this->render('player/pirate_campaign/pilot_local.html.twig', [
            'dossier' => $dossier,
        ], new Response(headers: $headers));
    }
}
