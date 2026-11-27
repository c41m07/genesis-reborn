<?php

declare(strict_types=1);

namespace App\Service\Market;

use App\Entity\{Galaxy, MarketTrade, PlayerUniverse};
use App\Repository\ResourceMarketRepository;

/** Projection privée des convois pour le panneau de flotte et la carte, y compris après fermeture de la station. */
final readonly class MarketTraffic
{
    public function __construct(private ResourceMarketRepository $markets) {}

    public static function containsStation(Galaxy $galaxy): bool
    {
        return in_array(strtolower($galaxy->coordinateCode()), ['a', 'alpha'], true);
    }

    /**
     * @return list<array{id:int,status:string,label:string,from:string,to:string,shipCount:int,
     *     departureAt:?string,arrivalAt:?string,colonySystemId:int,stationHere:bool,destinationOnly:bool,returnFraction:float|int}>
     */
    public function forViewer(PlayerUniverse $member, ?Galaxy $galaxy = null): array
    {
        if ($galaxy && $galaxy->universe->id !== $member->universe->id) {
            return [];
        }
        $atStation = $galaxy && self::containsStation($galaxy);
        $movements = [];
        foreach ($this->markets->activeTraffic($member, $atStation ? null : $galaxy) as $row) {
            /** @var MarketTrade $trade */
            $trade = $row[0];
            $outbound = $trade->status === 'outbound';
            $waiting = $trade->status === 'awaiting_colony';
            $redirected = !$outbound && $trade->originId !== $trade->returnPlanetId;
            $station = 'Bourse · '.MarketTerms::COORDINATE;
            $colony = (int) $row['planetOwner'] === $member->id ? $row['colonyName'] : 'Ancienne colonie';
            $from = match (true) {
                $outbound => $colony,
                $redirected => 'Position de repli',
                $trade->recalled => 'Position du rappel',
                default => $station,
            };
            $to = match (true) {
                $outbound => $station,
                $waiting => 'Colonie à choisir',
                default => $colony,
            };
            $label = match (true) {
                $waiting => 'En attente',
                $outbound => 'Aller',
                $trade->recalled => 'Rappel',
                default => 'Retour',
            };
            $duration = MarketTerms::LEG_SECONDS;
            // Au rappel, le voyage retour dure le temps déjà parcouru. L’échéance
            // couvre donc deux fois cette durée depuis le départ (une seconde minimum).
            if ($trade->recalled && !$redirected && $trade->dueAt) {
                $elapsed = $trade->dueAt->getTimestamp() - $trade->departedAt->getTimestamp();
                $duration = max(1, min($duration, intdiv($elapsed + 1, 2)));
            }
            $start = $outbound ? $trade->departedAt : $trade->dueAt?->modify('-'.$duration.' seconds');
            $ownGalaxy = $galaxy && (int) $row['galaxyId'] === $galaxy->id;
            // Après une redirection la position de repli précédente n’est pas enregistrée.
            // Montrer la destination connue sans inventer un départ depuis la station.
            if ($galaxy && ($redirected || $waiting) && !$ownGalaxy) {
                continue;
            }
            $movements[] = [
                'id' => $trade->id, 'status' => $trade->status,
                'label' => $label,
                'from' => $from, 'to' => $to, 'shipCount' => array_sum($trade->ships),
                'departureAt' => $start?->format(DATE_ATOM), 'arrivalAt' => $trade->dueAt?->format(DATE_ATOM),
                'colonySystemId' => (int) $row['systemId'], 'stationHere' => $atStation,
                'destinationOnly' => $redirected || $waiting, 'returnFraction' => $duration / MarketTerms::LEG_SECONDS,
            ];
        }
        return $movements;
    }
}
