<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\PlayerUniverse;
use App\Repository\PirateCampaignReadRepository;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;

/** La lecture et l'import relisent la même reconnaissance. Ni nouveau rapport, ni écriture. */
final readonly class PirateReconReportReader
{
    public function __construct(
        private PirateCampaignReadRepository $repository,
        private PiratePilotGate $gate,
        private PirateCampaignCatalogue $campaign,
        private GameCatalog $catalog,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Ne retourne jamais de scénario au navigateur. Seul le simulateur peut en demander
     * la version métier via ce même contrôle, après la remise réelle de la sonde.
     *
     * @return array{id:int,mission:int,targetName:string,location:string,date:\DateTimeImmutable,
     *     formation:list<array>,detected:?bool,complete:bool,rounds:?int,recharge:?float,
     *     forces:array,scenario?:PirateBattleScenario}|null
     */
    public function read(PlayerUniverse $viewer, int $id, bool $forSimulation = false): ?array
    {
        $at = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        if ($id < 1 || $viewer->id === null || $viewer->player?->id === null) {
            return null;
        }
        $viewer = $this->repository->activeMembership($viewer->player->id, $viewer->id, $at);
        if ($viewer === null || !$this->gate->admits($viewer->player, $viewer, $at)) {
            return null;
        }

        $scout = $this->repository->deliveredReconnaissance($viewer, $id, $at);
        $report = $scout?->projectFor($viewer, $at);
        if (($report['status'] ?? null) !== 'reported') {
            return null;
        }
        $encounter = $scout->battleProofFor($viewer, $at);
        if ($forSimulation && $encounter === null) {
            // Une composition ancienne ne prouve pas les statistiques ou règles du combat.
            return null;
        }
        $key = $scout->reportedMissionKeyFor($viewer, $at);
        $position = array_search($key, $this->campaign->keys($viewer->universe), true);
        if ($position === false) {
            return null;
        }
        $system = $scout->existingSystemFor($viewer, $at);
        $narrative = $this->campaign->narrativeFor(
            $key, PirateDistressSite::inExistingSystem($viewer, $system), $viewer, PirateCampaignMoment::RenseignementRecu,
        );
        $scenario = $encounter === null ? null : $encounter['prepared']->battleForServer();
        $snapshot = $scenario?->snapshot();
        $ships = $this->catalog->forUniverse($viewer->universe)['catalog']['ships'];
        $formation = [];
        $units = [];
        foreach ($snapshot['units'] ?? [] as $unit) {
            $units[$unit['slug']][$unit['tier']] = $unit;
        }
        foreach ($report['composition'] as $slug => $tiers) {
            foreach (GameCatalog::TIERS as $tier) {
                if (isset($tiers[$tier])) {
                    // Les noms n'ont qu'une fonction de présentation. Les quantités restent celles de la sonde.
                    $unit = $units[$slug][$tier] ?? [];
                    $formation[] = [
                        'slug' => $slug,
                        'label' => $ships[$slug]['tier_labels'][$tier] ?? $ships[$slug]['label'] ?? $slug,
                        'tier' => $tier, 'quantity' => $tiers[$tier],
                        'hp' => $unit['hp'] ?? null, 'shield' => $unit['shield'] ?? null, 'attack' => $unit['base_attack'] ?? null,
                    ];
                }
            }
        }

        return [
            'id' => $id, 'mission' => $position + 1, 'targetName' => $narrative['title'],
            'location' => $system->galaxy->coordinateCode().':'.$system->number(),
            'date' => new \DateTimeImmutable($report['reported_at']), 'formation' => $formation,
            'detected' => $report['detected'], 'complete' => $scenario !== null,
            'rounds' => $snapshot['rounds'] ?? null, 'recharge' => $snapshot['recharge'] ?? null,
            // Vides par construction dans PirateBattleScenario : ni défense de colonie ni recherche distincte.
            'forces' => ['ships' => $report['composition'], 'defenses' => [], 'research' => [], 'fortress' => [], 'arrival' => 1],
        ] + ($forSimulation ? ['scenario' => $scenario] : []);
    }
}
