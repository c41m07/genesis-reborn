<?php
namespace App\Service\Admin;

use App\Entity\{Fleet, FleetMissionTrace};
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;

final class FleetMissionTraceService
{
    public function __construct(private GameManager $manager, private GameCatalog $catalog) {}

    public function recordDeparture(Fleet $fleet, array $composition, array $freight): void
    {
        $trace = new FleetMissionTrace();$trace->fleet=$fleet;$trace->universe=$fleet->playerUniverse->universe;
        $trace->sender=$fleet->playerUniverse;$trace->recipient=$fleet->destination?->owner;
        $trace->targetWasPlayer=$trace->recipient?->isPlayerControlled() ?? false;
        $trace->mission=$fleet->mission;$trace->departedAt=$fleet->departureAt;
        $definitions = $this->catalog->forUniverse($trace->universe)['catalog']['ships'];$ships=[];
        foreach ($composition as $slug=>$tiers) foreach ($tiers as $tier=>$quantity) {
            $definition=$definitions[$slug] ?? null;
            $ships[]=['slug'=>$slug,'tier'=>$tier,'quantity'=>$quantity,'label'=>$definition ? $this->catalog->withTier($definition,$tier)['label'] : $slug];
        }
        $trace->snapshot=['sender'=>$fleet->playerUniverse->getDisplayName(),'recipient'=>$trace->recipient?->getDisplayName(),
            'origin'=>$fleet->homePlanet->name.' · '.$fleet->homePlanet->coordinates(),
            'target'=>$fleet->destination ? $fleet->destination->name.' · '.$fleet->destination->coordinates() : $fleet->destinationSystem?->name,
            'ships'=>$ships,'freight'=>$freight,'cargo'=>$fleet->cargo,'fuel'=>$fleet->reservedFuel,'arrival_at'=>$fleet->arrivalAt?->format(DATE_ATOM)];
        $this->manager->save($trace);
    }
}
