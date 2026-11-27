<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHall, Planet};
use App\Service\Fleet\{FleetNavigation, GalaxyGeometry, MovementCalculator};

/** Route du convoi vers le Hall attesté, distincte d’une destination publique. */
final readonly class GuildHallFlight
{
    public const DOCK_POSITION = 0;

    public function __construct(private MovementCalculator $movement, private FleetNavigation $navigation)
    {
    }

    /**
     * L’appelant valide l’origine et le Hall sous les verrous de départ.
     * La quote publique de mouvement refuse la position 0 : ne pas élargir son
     * contrat. Ici le serveur construit seul cette extrémité depuis le Hall posé.
     *
     * @param list<array<string,mixed>> $ships
     * @return array{movement:array<string,mixed>,navigation:array<string,mixed>}
     */
    public function quote(
        Planet $origin,
        GuildHall $hall,
        array $ships,
        float $ratio,
        float $timeMultiplier,
        string $fuelPolicy,
        \DateTimeImmutable $now,
    ): array {
        [$colonyPoint, $hallPoint] = $this->endpoints($origin, $hall);

        return $this->quoteBetween($colonyPoint, $hallPoint, $ships, $ratio, $timeMultiplier, $fuelPolicy, $now);
    }

    /**
     * L’Atelier livre depuis la position 0. La moitié retour reste réservée
     * au cas où la colonie destinataire ne pourrait plus recevoir la flotte.
     * L’appelant atteste le Hall et la colonie sous verrou avant toute réservation.
     *
     * @param list<array<string,mixed>> $ships
     * @return array{movement:array<string,mixed>,navigation:array<string,mixed>}
     */
    public function quoteDelivery(
        GuildHall $hall,
        Planet $destination,
        array $ships,
        float $ratio,
        float $timeMultiplier,
        string $fuelPolicy,
        \DateTimeImmutable $now,
    ): array {
        [$colonyPoint, $hallPoint] = $this->endpoints($destination, $hall);

        return $this->quoteBetween($hallPoint, $colonyPoint, $ships, $ratio, $timeMultiplier, $fuelPolicy, $now);
    }

    /** @return array{array{int,int,int,int,int},array{int,int,int,int,int}} */
    private function endpoints(Planet $colony, GuildHall $hall): array
    {
        $from = $colony->solarSystem;
        $to = $hall->solarSystem;
        if ($hall->id === null || $hall->placedAt === null || $hall->guild->archivedAt !== null || $to === null
            || $from->id === null || $to->id === null || $from->galaxy->id === null || $to->galaxy->id === null
            || $from->galaxy->universe->id !== $hall->guild->universe->id
            || $to->galaxy->universe->id !== $hall->guild->universe->id
            || $colony->position < 1 || $colony->position > 9) {
            throw new \DomainException('La route entre cette colonie et le Hall n’est pas disponible.');
        }
        $colonyPoint = [$from->id, $from->galaxy->id, $from->coordX, $from->coordY, $colony->position];
        $hallPoint = [$to->id, $to->galaxy->id, $to->coordX, $to->coordY, self::DOCK_POSITION];

        return [$colonyPoint, $hallPoint];
    }

    /**
     * @param array{int,int,int,int,int} $start
     * @param array{int,int,int,int,int} $target
     * @param list<array<string,mixed>> $ships
     * @return array{movement:array<string,mixed>,navigation:array<string,mixed>}
     */
    private function quoteBetween(array $start, array $target, array $ships, float $ratio, float $timeMultiplier, string $fuelPolicy, \DateTimeImmutable $now): array
    {
        $distances = $this->movement->distances($start, $target);
        $quote = $this->movement->quoteDistances(
            $distances['normal'], $distances['hyper'], $distances['same'], $ships, $ratio, $timeMultiplier, $fuelPolicy,
        );
        $quote['distance_geometry'] = GalaxyGeometry::SPIRAL;

        return [
            'movement' => $quote,
            // Le rappel et l’arrivée doivent réutiliser cet itinéraire au lieu
            // de deviner la position depuis une destination qui n’est pas une planète.
            'navigation' => $this->navigation->freeze($start, $target, $ships, $ratio, $fuelPolicy, $quote, $now),
        ];
    }
}
