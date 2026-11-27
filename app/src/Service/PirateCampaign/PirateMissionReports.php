<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateAttackCampaign, PirateConvoyDelivery, PirateMissionReport, PirateReconnaissance, Planet, PlayerUniverse, ReportNotice};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

/** Les rapports suivent la transaction du worker qui atteste la remise, le combat ou le trajet. */
final readonly class PirateMissionReports
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private GameCatalog $catalog,
    ) {
    }

    public function reconnaissance(PirateReconnaissance $scout, \DateTimeImmutable $at): void
    {
        $system = $scout->existingSystemFor($scout->membership(), $at);
        $this->record(new PirateMissionReport($scout, 'espionage', 1, $at, $system->name, 'reported'));
    }

    public function battle(PirateAttackCampaign $attack, array $result, \DateTimeImmutable $at): void
    {
        $scout = $attack->reconnaissance;
        $owner = $scout->membership();
        $system = $scout->existingSystemFor($owner, $at);
        $ships = $this->catalog->forUniverse($owner->universe)['catalog']['ships'];
        $participants = [];
        foreach (['escort' => $attack->timeline['units'], 'pirates' => $attack->scenario['units']] as $camp => $units) {
            $formation = [];
            foreach ($units as $unit) {
                $definition = $ships[$unit['slug']] ?? [];
                $formation[] = ['slug' => $unit['slug'], 'tier' => $unit['tier'], 'quantity' => $unit['quantity'],
                    'label' => $definition['tier_labels'][$unit['tier']] ?? $definition['label'] ?? $unit['slug']];
            }
            $participants[] = ['report_owner' => $camp, 'camp' => $camp, 'role' => $camp === 'escort' ? 'attacker' : 'defender',
                'player_universe_id' => $camp === 'escort' ? $owner->id : null, 'units' => $formation];
        }
        $this->record(new PirateMissionReport($scout, 'combat', $attack->timeline['attempt'], $at, $system->name,
            $result['outcome'], ['participants' => $participants, 'losses' => $result['losses'],
                'location_code' => $system->galaxy->coordinateCode().':'.$system->number(), 'max_rounds' => $attack->scenario['rounds'],
                'rounds_played' => $result['rounds_played'], 'rounds' => $result['rounds'] ?? []], $at));
    }

    public function startTransport(PirateConvoyDelivery $right, PirateConvoyTimeline $timeline, Planet $planet, PlayerUniverse $owner, \DateTimeImmutable $at): void
    {
        $this->record($this->transport($right, $timeline, $owner, $planet->name.' · '.$planet->coordinates(), $at));
    }

    public function finishTransport(PirateConvoyDelivery $right, PirateConvoyTimeline $timeline, ?Planet $planet, PlayerUniverse $owner, \DateTimeImmutable $at): void
    {
        $report = $this->repository->one(PirateMissionReport::class, ['reconnaissance' => $right->reconnaissance(),
            'category' => 'economy', 'sequenceNumber' => $timeline->checkpoint()['generation']]);
        if ($report === null) {
            // Un convoi parti avant la mise à jour a quand même un reçu à son arrivée.
            $destination = $planet === null ? 'Ancienne destination' : $planet->name.' · '.$planet->coordinates();
            $report = $this->transport($right, $timeline, $owner, $destination, $at);
            $this->record($report);
        }
        $report->status = $planet === null ? 'destination_lost' : 'delivered';
        $report->resolvedAt = $at;
        $report->snapshot['boxed'] = $planet !== null;
        $report->snapshot['delivered'] = $planet === null ? [] : $timeline->quote->depositAmounts();
    }

    private function transport(PirateConvoyDelivery $right, PirateConvoyTimeline $timeline, PlayerUniverse $owner, string $destination, \DateTimeImmutable $at): PirateMissionReport
    {
        $origin = $right->reconnaissance()->existingSystemFor($owner, $at);
        $generation = $timeline->checkpoint()['generation'];

        return new PirateMissionReport($right->reconnaissance(), 'economy', $generation, $timeline->chronology()['departed_at'], $destination, 'traveling',
            ['sender' => 'Convoi de remerciement', 'recipient' => $owner->player->username,
                'origin' => $generation === 1 ? $origin->name : 'Destination précédente du convoi', 'destination' => $destination,
                'sent' => $timeline->quote->depositAmounts(), 'boxed' => true]);
    }

    private function record(PirateMissionReport $report): void
    {
        $this->manager->save($report);
        $this->manager->save(new ReportNotice($report->reconnaissance->membership(), $report));
    }
}
