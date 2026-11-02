<?php
namespace App\Controller;

use App\Entity\CombatLog;
use App\Service\Combat\CombatReportReader;
use App\Service\Catalog\GameCatalog;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CombatReportController extends AbstractController
{
    #[Route('/rapports', name: 'app_combat_reports', methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, CombatReportReader $reader, \App\Service\Fleet\EspionageReportReader $espionageReader): Response
    {
        $membership = $context->membership();
        $combatReports = $reader->list($membership);
        $espionageReports = $espionageReader->list($membership);
        $filter = $request->query->getString('type', 'all');
        if (!in_array($filter, ['all', 'combat', 'economy', 'espionage'], true)) {
            $filter = 'all';
        }

        return $this->render('combat/reports.html.twig', [
            'membership' => $membership,
            'reports' => $filter === 'espionage' ? [] : $combatReports,
            'espionage_reports' => in_array($filter, ['combat', 'economy'], true) ? [] : $espionageReports,
            'filter' => $filter,
            'report_count' => count($combatReports) + count($espionageReports),
        ]);
    }

    #[Route('/rapports/combat/{id}', name: 'app_combat_report', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UniverseContext $context, CombatReportReader $reader, GameCatalog $catalog): Response
    {
        $membership = $context->membership();
        $log = $reader->findVisible($membership, $id);
        if (!$log) {
            throw $this->createNotFoundException();
        }
        $rows = [];
        foreach ($log->snapshot['participants'] ?? [] as $participant) {
            $own = (int) ($participant['player_universe_id'] ?? 0) === $membership->id;
            $isNpc = (bool) ($participant['pve_npc'] ?? false);
            foreach ($participant['units'] ?? [] as $unit) {
                $slug = (string) ($unit['slug'] ?? '');
                $key = ($isNpc ? 'npc:' . (string) ($participant['planet_id'] ?? '') : (string) ($participant['fleet_id'] ?? '')) . ':' . $slug;
                $rows[] = [
                    'label' => $isNpc ? 'Flotte PNJ' : ($own ? 'Votre flotte' : ($participant['role'] === 'defender' ? 'Garnison engagée' : 'Autre flotte engagée')),
                    'unit' => (string) ($unit['label'] ?? $slug),
                    'initial' => (int) ($unit['quantity'] ?? 0),
                    'lost' => (int) ($log->snapshot['losses'][$key] ?? 0),
                    'round' => (int) ($participant['arrival_round'] ?? 1),
                ];
            }
        }
        foreach ($log->snapshot['defenses'] ?? [] as $defense) {
            $slug = (string) ($defense['slug'] ?? '');
            $key = 'planet:' . (string) ($defense['id'] ?? '') . ':' . $slug;
            $rows[] = [
                'label' => 'Défense planétaire',
                'unit' => (string) ($defense['label'] ?? $slug),
                'initial' => (int) ($defense['quantity'] ?? 0),
                'lost' => (int) ($log->snapshot['losses'][$key] ?? 0),
                'round' => 1,
            ];
        }
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

        return $this->render('combat/report.html.twig', ['report' => $log, 'rows' => $rows, 'loot_rows' => $lootRows, 'membership' => $membership]);
    }
}
