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

    /**
     * Les routes sont orientées dans le sens réel du vol. Le retour de combat
     * au Hall et le repli direct vers la colonie d’origine ne sont pas le
     * même trajet. La colonie de rattachement de la flotte reste inchangée.
     *
     * @param list<array<string,mixed>> $ships
     * @return array{
     *     outbound:array{movement:array<string,mixed>,navigation:array<string,mixed>},
     *     hall_return:array{movement:array<string,mixed>,navigation:array<string,mixed>},
     *     colony_return:array{movement:array<string,mixed>,navigation:array<string,mixed>},
     *     reserved_hall_fuel:float
     * }
     */
    public function quoteCommandSortie(
        GuildHall $hall,
        Planet $home,
        Planet $target,
        array $ships,
        float $ratio,
        float $timeMultiplier,
        string $fuelPolicy,
        \DateTimeImmutable $now,
    ): array {
        [$homePoint, $hallPoint] = $this->endpoints($home, $hall);
        [$targetPoint] = $this->endpoints($target, $hall);
        if ($target->id === null || $home->id === null || $target->id === $home->id) {
            throw new \DomainException('Choisissez une autre colonie pour la sortie du Centre.');
        }

        // Les trois devis restent distincts : le carburant dû ne doit jamais
        // être calculé en redoublant l’aller lorsqu’un repli direct est retenu.
        $outbound = $this->quoteBetween($hallPoint, $targetPoint, $ships, $ratio, $timeMultiplier, $fuelPolicy, $now);
        $hallReturn = $this->quoteBetween($targetPoint, $hallPoint, $ships, $ratio, $timeMultiplier, $fuelPolicy, $now);
        $colonyReturn = $this->quoteBetween($targetPoint, $homePoint, $ships, $ratio, $timeMultiplier, $fuelPolicy, $now);

        return [
            'outbound' => $outbound,
            'hall_return' => $hallReturn,
            'colony_return' => $colonyReturn,
            // Le Hall finance un seul retour. Ce devis ne puise jamais dans
            // la réserve privée déjà embarquée pour le rappel depuis le Hall.
            'reserved_hall_fuel' => $outbound['navigation']['one_way_fuel'] + max(
                $hallReturn['navigation']['one_way_fuel'],
                $colonyReturn['navigation']['one_way_fuel'],
            ),
        ];
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
