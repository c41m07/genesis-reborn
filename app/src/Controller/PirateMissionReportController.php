<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PirateReportRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatReportPresenter;
use App\Service\PirateCampaign\PirateReconReportReader;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PirateMissionReportController extends AbstractController
{
    #[Route('/rapports/campagne/{id}', name: 'app_pirate_mission_report', requirements: ['id' => '[1-9][0-9]{0,9}'], methods: ['GET'])]
    public function show(int $id, UniverseContext $context, PirateReportRepository $reports, PirateReconReportReader $scouts,
        CombatReportPresenter $combats, GameCatalog $catalog): Response
    {
        $member = $context->membership();
        $report = $reports->visible($member, $id);
        if ($report === null) {
            throw $this->createNotFoundException();
        }
        $view = ['report' => $report, 'membership' => $member, 'campaign_report_id' => $id];
        switch ($report->category) {
            case 'espionage':
                $intel = $scouts->read($member, $report->reconnaissance->id);
                if ($intel === null) {
                    throw $this->createNotFoundException();
                }
                $view['report'] = $intel;
                $template = 'player/pirate_campaign/recon_report.html.twig';
                break;
            case 'combat':
                $rounds = $report->snapshot['rounds'] ?? [];
                $view += $combats->present($report->snapshot, $rounds, $member->id);
                $view['report'] = ['id' => $id, 'outcome' => $report->status, 'resolvedAt' => $report->resolvedAt,
                    'snapshot' => $report->snapshot, 'rounds' => $rounds];
                $view['campaign_location'] = $report->location;
                $view['loot_rows'] = [];
                $template = 'combat/report.html.twig';
                break;
            case 'economy':
                $view['resources'] = $catalog->rules()['resources'];
                $template = 'transport/report.html.twig';
                break;
            default:
                throw $this->createNotFoundException();
        }

        return $this->render($template, $view, new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
}
