<?php
namespace App\Controller;

use App\Entity\CombatLog;
use App\Service\Combat\CombatReportReader;
use App\Service\Combat\CombatReportPresenter;
use App\Service\Catalog\GameCatalog;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CombatReportController extends AbstractController
{
    #[Route('/rapports', name: 'app_combat_reports', methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, CombatReportReader $reader, \App\Service\Fleet\EspionageReportReader $espionageReader, \App\Repository\GameRepository $repo): Response
    {
        $membership = $context->membership();
        $combatReports = $reader->list($membership);
        $espionageReports = $espionageReader->list($membership);
        $transportReports = $repo->transportReportsFor($membership);
        $filter = $request->query->getString('type', 'all');
        if (!in_array($filter, ['all', 'combat', 'economy', 'espionage'], true)) {
            $filter = 'all';
        }

        return $this->render('combat/reports.html.twig', [
            'membership' => $membership,
            'reports' => in_array($filter, ['all', 'combat'], true) ? $combatReports : [],
            'espionage_reports' => in_array($filter, ['combat', 'economy'], true) ? [] : $espionageReports,
            'transport_reports' => in_array($filter, ['all', 'economy'], true) ? $transportReports : [],
            'filter' => $filter,
            'report_count' => count($combatReports) + count($espionageReports) + count($transportReports),
        ]);
    }

    #[Route('/rapports/combat/{id}', name: 'app_combat_report', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UniverseContext $context, CombatReportReader $reader, GameCatalog $catalog, CombatReportPresenter $presenter): Response
    {
        $membership = $context->membership();
        $log = $reader->findVisible($membership, $id);
        if (!$log) {
            throw $this->createNotFoundException();
        }
        $presentation = $presenter->present($log->snapshot, $log->rounds, $membership->id);
        $ownLoot = [];
        foreach ($log->snapshot['loot']['fleets'] ?? [] as $fleetId => $resources) {
            foreach ($log->snapshot['participants'] ?? [] as $participant) {
                if ((int) ($participant['fleet_id'] ?? 0) === (int) $fleetId && (int) ($participant['player_universe_id'] ?? 0) === $membership->id) {
                    foreach ($resources as $slug => $amount) {
                        $ownLoot[$slug] = ($ownLoot[$slug] ?? 0) + (int) $amount;
                    }
                }
            }
        }
        $lootRows = [];
        foreach ($ownLoot as $slug => $amount) {
            if ($amount > 0) {
                $lootRows[] = ['label' => $catalog->rules()['resources'][$slug] ?? $slug, 'amount' => $amount];
            }
        }

        return $this->render('combat/report.html.twig', ['report' => $log, 'loot_rows' => $lootRows, 'membership' => $membership] + $presentation);
    }
}
