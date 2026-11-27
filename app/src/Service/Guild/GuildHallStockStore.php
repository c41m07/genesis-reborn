<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHall, GuildHallBuilding, GuildHallStock};
use App\Manager\GameManager;
use App\Repository\GameRepository;

/** Stock interne ; ni route publique, ni transfert direct depuis la colonie. */
final readonly class GuildHallStockStore
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
    ) {
    }

    /**
     * L’appelant tient le verrou du Hall exact et acquitte l’arrivée du convoi
     * dans cette même transaction. Le surplus reste dans le reçu du convoi.
     * @param array<string, int> $capacity
     * @param array<string, int> $cargo
     * @return array{stock:array<string,int>,delivered:array<string,int>,overflow:array<string,int>}
     */
    public function receiveLocked(GuildHall $hall, array $capacity, array $cargo): array
    {
        $stock = $this->existingLocked($hall);
        if (($stock?->hydrogenFraction ?? 0.0) > 0.0) {
            // Les apports sont entiers : le reliquat occupe déjà une part de la
            // dernière unité de capacité, qui ne peut pas accepter un dépôt entier.
            $capacity['hydrogen'] = max(0, ($capacity['hydrogen'] ?? 0) - 1);
        }
        $result = GuildHallStockPolicy::receive($stock?->materials ?? [], $capacity, $cargo);
        if (array_sum($result['delivered']) === 0) {
            return $result;
        }

        if ($stock === null) {
            // Le verrou du Hall sérialise aussi la toute première livraison.
            $stock = new GuildHallStock();
            $stock->hall = $hall;
            $this->manager->save($stock);
        }
        $stock->materials = $result['stock'];

        return $result;
    }

    /**
     * Frontière du futur traitement d’arrivée : conserver le résultat dans la flotte
     * et consommer son jeton dans la transaction courante avant de programmer le retour.
     *
     * @param array<string,int|float> $cargo
     * @return array{delivered:array<string,int>,retained:array<string,int|float>}
     */
    public function receiveConvoyLocked(
        GuildHall $hall,
        GuildHallBuildingSchedule $schedule,
        array $cargo,
        float $reservedFuel,
    ): array {
        // Les changements de niveau et les arrivées tiennent le même verrou du Hall.
        // Le niveau en cours de construction ne procure pas de capacité anticipée.
        $warehouse = $this->repository->one(GuildHallBuilding::class, ['hall' => $hall, 'slug' => GuildHallBuilding::WAREHOUSE]);
        if ($warehouse instanceof GuildHallBuilding) {
            $this->manager->lockCurrent($warehouse);
        }
        $capacity = $schedule->capacity($warehouse instanceof GuildHallBuilding ? $warehouse->level : 0);
        $received = $this->receiveLocked($hall, $capacity, GuildHallCargo::deliverable($cargo, $reservedFuel));

        return [
            'delivered' => $received['delivered'],
            'retained' => GuildHallCargo::retain($cargo, $reservedFuel, $received['delivered']),
        ];
    }

    /** @param array<string, int> $cost */
    public function consumeLocked(GuildHall $hall, array $cost): void
    {
        $stock = $this->existingLocked($hall);
        if ($stock === null) {
            throw new \DomainException('Aucun matériau n’a encore été livré au Hall.');
        }
        $stock->materials = GuildHallStockPolicy::consume($stock->materials, $cost);
    }

    /** À appeler sous le verrou du Hall, dans la transaction qui réserve les vaisseaux. */
    public function consumeFuelLocked(GuildHall $hall, float $fuel): void
    {
        $stock = $this->existingLocked($hall);
        $remaining = GuildHallFuelBalance::debit($stock?->materials['hydrogen'] ?? 0, $stock?->hydrogenFraction ?? 0.0, $fuel);
        if ($stock !== null) {
            $stock->materials['hydrogen'] = $remaining['whole'];
            $stock->hydrogenFraction = $remaining['fraction'];
        }
    }

    private function existingLocked(GuildHall $hall): ?GuildHallStock
    {
        if ($hall->id === null || $hall->placedAt === null || $hall->solarSystem === null || $hall->guild->archivedAt !== null) {
            throw new \DomainException('Le Hall de guilde n’est plus disponible.');
        }
        $stock = $this->repository->one(GuildHallStock::class, ['hall' => $hall]);
        if ($stock instanceof GuildHallStock) {
            $this->manager->lockCurrent($stock);

            return $stock;
        }

        return null;
    }
}
