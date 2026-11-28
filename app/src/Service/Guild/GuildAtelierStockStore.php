<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildAtelierStock, GuildHall};
use App\Manager\GameManager;
use App\Repository\GameRepository;

/** Stock commun livré ; le reçu et son règlement sont gérés par l’appelant. */
final readonly class GuildAtelierStockStore
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
    ) {
    }

    /**
     * L’appelant détient le verrou de ce Hall, valide les unités par catalogue
     * et acquitte le reçu dans la même transaction. Ce verrou sérialise aussi
     * le premier dépôt, quand la ligne de stock n’existe pas encore.
     *
     * @param array<string,array<string,int>> $composition
     */
    public function receiveLocked(GuildHall $hall, array $composition): void
    {
        $stock = $this->existingLocked($hall);
        $ships = GuildAtelierInventory::receive($stock?->ships ?? [], $composition);
        if (($stock?->returnCapacity ?? []) !== []) {
            // Un don ne consomme jamais la place des vaisseaux susceptibles de revenir.
            GuildAtelierInventory::receive($ships, $stock->returnCapacity);
        }
        if ($stock === null) {
            $stock = new GuildAtelierStock();
            $stock->hall = $hall;
            $this->manager->save($stock);
        }
        $stock->ships = $ships;
    }

    /**
     * L’appelant vérifie les droits et la colonie, puis réserve le carburant
     * et crée le convoi dans la même transaction que ces unités.
     *
     * @param array<string,array<string,int>> $composition
     */
    public function reserveLocked(GuildHall $hall, array $composition): void
    {
        $stock = $this->existingLocked($hall);
        if ($stock === null) {
            throw new \DomainException('L’Atelier n’a pas encore reçu de vaisseaux.');
        }
        $available = GuildAtelierInventory::reserve($stock->ships, $composition);
        $return = GuildAtelierInventory::receive($stock->returnCapacity, $composition);
        $stock->ships = $available;
        $stock->returnCapacity = $return;
    }

    /**
     * Sous le verrou du Hall et du reçu, le retour consomme sa propre place ;
     * une livraison terminée libère cette place sans recréditer les vaisseaux.
     * Une fabrication terminée consomme de la même façon sa capacité réservée.
     *
     * @param array<string,array<string,int>> $composition
     */
    public function settleReservationLocked(GuildHall $hall, array $composition, bool $returned): void
    {
        $stock = $this->existingLocked($hall);
        if ($stock === null) {
            throw new \LogicException('Stock de la livraison introuvable.');
        }
        $remaining = GuildAtelierInventory::reserve($stock->returnCapacity, $composition);
        if ($returned) {
            $stock->ships = GuildAtelierInventory::receive($stock->ships, $composition);
        }
        $stock->returnCapacity = $remaining;
    }

    /** Garde la place des coques payées mais non terminées sans les rendre livrables.
     * Le verrou du Hall est détenu jusqu’à l’écriture de la commande et de son outbox.
     *
     * @param array<string,array<string,int>> $composition
     */
    public function reserveProductionCapacityLocked(GuildHall $hall, array $composition): void
    {
        $stock = $this->existingLocked($hall);
        $reserved = GuildAtelierInventory::receive($stock?->returnCapacity ?? [], $composition);
        GuildAtelierInventory::receive($stock?->ships ?? [], $reserved);
        if ($stock === null) {
            $stock = new GuildAtelierStock();
            $stock->hall = $hall;
            $this->manager->save($stock);
        }
        $stock->returnCapacity = $reserved;
    }

    /** @param array<string,array<string,int>> $composition */
    public function assertReservableLocked(GuildHall $hall, array $composition): void
    {
        $stock = $this->existingLocked($hall);
        GuildAtelierInventory::reserve($stock?->ships ?? [], $composition);
    }

    private function existingLocked(GuildHall $hall): ?GuildAtelierStock
    {
        if ($hall->id === null || $hall->placedAt === null || $hall->solarSystem === null || $hall->guild->archivedAt !== null) {
            throw new \DomainException('Le Hall de guilde n’est plus disponible.');
        }
        $stock = $this->repository->one(GuildAtelierStock::class, ['hall' => $hall]);
        if ($stock instanceof GuildAtelierStock) {
            $this->manager->lockCurrent($stock);

            return $stock;
        }

        return null;
    }
}
