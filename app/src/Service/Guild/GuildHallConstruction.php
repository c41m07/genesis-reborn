<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHall, GuildHallBuilding, OutboxMessage};
use App\Manager\GameManager;
use App\Message\GuildHallConstructionDue;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/** Chantier du Hall, indépendant des files personnelles de la colonie. */
final readonly class GuildHallConstruction
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private GuildHallStockStore $stock,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Le service appelant contrôle droits et Hall installé sous verrou, puis choisit
     * niveau maximal, coût et durée depuis le barème serveur dans la même transaction.
     *
     * @param array<string, int> $cost
     */
    public function startLocked(
        GuildHall $hall,
        string $slug,
        int $expectedLevel,
        int $maxLevel,
        array $cost,
        int $durationSeconds,
    ): GuildHallBuilding {
        if (!in_array($slug, [GuildHallBuilding::WAREHOUSE, GuildHallBuilding::DOCK, GuildHallBuilding::ATELIER, GuildHallBuilding::RELAY], true)
            || $durationSeconds < 1 || $durationSeconds > 2147483647 || array_sum($cost) <= 0) {
            throw new \DomainException('Le barème de ce chantier est indisponible.');
        }
        $building = $this->findLocked($hall, $slug);
        $level = $building?->level ?? 0;
        // En cas de doublon pendant le chantier, ou après la montée de rang, ne pas débiter.
        if ($building?->finishesAt !== null || $expectedLevel !== $level || $level >= $maxLevel) {
            throw new \DomainException('Ce chantier a changé. Consultez son état avant de confirmer.');
        }

        $this->stock->consumeLocked($hall, $cost);
        if ($building === null) {
            $building = new GuildHallBuilding();
            $building->hall = $hall;
            $building->slug = $slug;
            $this->manager->save($building);
        }
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $building->finishesAt = $now->modify('+'.$durationSeconds.' seconds');
        $building->dispatchToken = Uuid::v4()->toRfc4122();

        // Le paiement et la programmation doivent échouer ou réussir ensemble.
        $this->manager->flush();
        $message = new OutboxMessage();
        $message->kind = GuildHallConstructionDue::KIND;
        $message->targetId = $building->id;
        $message->token = $building->dispatchToken;
        $message->dueAt = $building->finishesAt;
        $this->manager->save($message);

        return $building;
    }

    public function complete(int $buildingId, string $token): bool
    {
        return $this->manager->transaction(function () use ($buildingId, $token): bool {
            $building = $this->repository->find(GuildHallBuilding::class, $buildingId);
            if (!$building instanceof GuildHallBuilding) {
                return false;
            }
            $hall = $building->hall;
            $guild = $hall->guild;
            // Le worker ne prend pas de verrou de joueur. L’ordre commun univers → guilde → Hall
            // sérialise le chantier avec la dissolution, même quand un message a déjà été publié.
            $this->manager->lockCurrent($guild->universe);
            $this->manager->lockCurrent($guild);
            if ($guild->archivedAt !== null) {
                return false;
            }
            $this->manager->lockCurrent($hall);
            $this->manager->lockCurrent($building);
            $now = $this->clock->now();
            if ($hall->placedAt === null || $hall->solarSystem === null || !$guild->universe->isAccessible($now)
                || $building->dispatchToken !== $token || $building->finishesAt === null || $building->finishesAt > $now) {
                return false;
            }
            ++$building->level;
            $building->finishesAt = null;
            $building->dispatchToken = null;

            return true;
        });
    }

    private function findLocked(GuildHall $hall, string $slug): ?GuildHallBuilding
    {
        if ($hall->id === null || $hall->placedAt === null || $hall->solarSystem === null || $hall->guild->archivedAt !== null) {
            throw new \DomainException('Le Hall de guilde n’est plus disponible.');
        }

        $building = $this->repository->one(GuildHallBuilding::class, ['hall' => $hall, 'slug' => $slug]);
        if ($building instanceof GuildHallBuilding) {
            $this->manager->lockCurrent($building);

            return $building;
        }

        return null;
    }
}
