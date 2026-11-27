<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{PirateMissionReport, Player};
use App\Repository\GameRepository;
use App\Service\PirateCampaign\PirateReconReportReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[When(env: 'dev')]
#[When(env: 'prod')]
#[IsGranted('ROLE_USER')]
final class PirateReconReportController extends AbstractController
{
    #[Route('/rapports/espionnage-pirate/{id}', name: 'app_pirate_recon_report', requirements: ['id' => '[1-9][0-9]{0,9}'], methods: ['GET'], env: ['dev', 'prod'])]
    public function report(int $id, PirateReconReportReader $reader, GameRepository $games): Response
    {
        $account = $this->getUser();
        $viewer = $account instanceof Player ? $account->currentPlayerUniverse : null;
        $report = $viewer === null ? null : $reader->read($viewer, $id);
        $headers = ['Cache-Control' => 'private, no-store'];
        if ($report === null) {
            return new Response('Rapport introuvable.', Response::HTTP_NOT_FOUND, $headers);
        }

        $receipt = $games->one(PirateMissionReport::class, ['reconnaissance' => $id, 'category' => 'espionage']);
        return $this->render('player/pirate_campaign/recon_report.html.twig', ['report' => $report, 'campaign_report_id' => $receipt?->id], new Response(headers: $headers));
    }
}
