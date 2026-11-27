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
        $from = $origin->solarSystem;
        $to = $hall->solarSystem;
        if ($hall->id === null || $hall->placedAt === null || $hall->guild->archivedAt !== null || $to === null
            || $from->id === null || $to->id === null || $from->galaxy->id === null || $to->galaxy->id === null
            || $from->galaxy->universe->id !== $hall->guild->universe->id
            || $to->galaxy->universe->id !== $hall->guild->universe->id
            || $origin->position < 1 || $origin->position > 9) {
            throw new \DomainException('La route vers ce Hall n’est pas disponible.');
        }
        $start = [$from->id, $from->galaxy->id, $from->coordX, $from->coordY, $origin->position];
        $target = [$to->id, $to->galaxy->id, $to->coordX, $to->coordY, self::DOCK_POSITION];

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
