<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, GuildCommandConsent, GuildCommandPlan, GuildHall, GuildHallConvoy, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ActionGuard;
use App\Service\Player\RelationResolver;
use Psr\Clock\ClockInterface;

/** Planifier ne dépense ni carburant ni vaisseaux. Le départ doit revérifier les accords. */
final readonly class GuildCommandPlanning
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private GuildHallAccess $access,
        private ActionGuard $guard,
        private RelationResolver $relations,
        private ClockInterface $clock,
    ) {
    }

    public function open(PlayerUniverse $actor, int $guildId, int $hallId, int $targetId, string $key): ?GuildCommandPlan
    {
        return $this->manager->transaction(function () use ($actor, $guildId, $hallId, $targetId, $key): ?GuildCommandPlan {
            $hall = $this->access->lockCommandSortie($actor, $guildId, $hallId);
            $this->assertBuilt($hall);
            if (!$this->guard->receipt($actor, $key)) {
                return null;
            }

            [$target, $enemyGuildId] = $this->lockEnemyTarget($hall, $targetId);
            $plan = new GuildCommandPlan(
                $actor->universe->id,
                $hallId,
                $guildId,
                $enemyGuildId,
                $targetId,
                $target->owner->id,
                $actor->id,
                $this->clock->now(),
            );
            $this->manager->save($plan);

            return $plan;
        });
    }

    public function consent(PlayerUniverse $actor, int $guildId, int $hallId, int $planId, int $fleetId, string $key): bool
    {
        return $this->manager->transaction(function () use ($actor, $guildId, $hallId, $planId, $fleetId, $key): bool {
            $hall = $this->access->lockPlaced($actor, $guildId, $hallId);
            $this->assertBuilt($hall);
            if (!$this->guard->receipt($actor, $key)) {
                return false;
            }
            $plan = $this->lockOpenPlan($hall, $planId);
            [$target, $enemyGuildId] = $this->lockEnemyTarget($hall, $plan->targetColonyId);
            if ($plan->enemyGuildId !== $enemyGuildId || $plan->targetOwnerId !== $target->owner->id) {
                throw new \DomainException('La cible du plan a changé. Un nouveau plan est nécessaire.');
            }

            $fleet = $this->lockPrivateBerth($actor, $hall, $fleetId);
            $existing = $this->repository->one(GuildCommandConsent::class, ['plan' => $plan, 'fleetId' => $fleetId]);
            if ($existing instanceof GuildCommandConsent) {
                if ($existing->matchesBerth($fleet)) {
                    return false;
                }
                throw new \DomainException('Cet accord a été retiré ou est périmé. Un nouveau plan est nécessaire.');
            }
            $this->manager->save(new GuildCommandConsent(
                $plan, $fleetId, $actor->id, $fleet->homePlanet->id, $fleet->dispatchToken, $this->clock->now(),
            ));

            return true;
        });
    }

    public function withdraw(PlayerUniverse $actor, int $guildId, int $hallId, int $planId, int $fleetId, string $key): bool
    {
        return $this->manager->transaction(function () use ($actor, $guildId, $hallId, $planId, $fleetId, $key): bool {
            $hall = $this->access->lockPlaced($actor, $guildId, $hallId);
            if (!$this->guard->receipt($actor, $key)) {
                return false;
            }
            // Le propriétaire doit pouvoir retirer son accord même si la cible n’est plus hostile.
            $plan = $this->lockOpenPlan($hall, $planId);
            $consent = $this->repository->one(GuildCommandConsent::class, ['plan' => $plan, 'fleetId' => $fleetId, 'ownerId' => $actor->id]);
            if (!$consent instanceof GuildCommandConsent) {
                throw new \DomainException('Accord de flotte inaccessible.');
            }
            $this->manager->lockCurrent($consent);

            return $consent->withdraw($actor->id, $this->clock->now());
        });
    }

    private function assertBuilt(GuildHall $hall): void
    {
        if (!GuildCommandBerth::built($this->repository, $hall)) {
            throw new \DomainException('Le Centre de commandement doit être construit.');
        }
    }

    private function lockOpenPlan(GuildHall $hall, int $id): GuildCommandPlan
    {
        $plan = $this->repository->find(GuildCommandPlan::class, $id);
        if (!$plan instanceof GuildCommandPlan) {
            throw new \DomainException('Plan du Centre inaccessible.');
        }
        if ($plan->hallId !== $hall->id || $plan->guildId !== $hall->guild->id || $plan->universeId !== $hall->guild->universe->id) {
            throw new \DomainException('Plan du Centre inaccessible.');
        }
        $this->manager->lockCurrent($plan);
        if (!$plan->acceptsConsent()) {
            throw new \DomainException('Le plan du Centre est déjà lancé ou fermé.');
        }

        return $plan;
    }

    /** @return array{Planet,int} La guilde doit être en guerre officielle, une hostilité personnelle ne suffit pas. */
    private function lockEnemyTarget(GuildHall $hall, int $id): array
    {
        $target = $this->repository->find(Planet::class, $id);
        if (!$target instanceof Planet || $target->solarSystem->galaxy->universe->id !== $hall->guild->universe->id || $target->npcPlanet) {
            throw new \DomainException('Une guerre active avec la guilde qui contrôle cette colonie est nécessaire.');
        }
        $this->manager->lockCurrent($target);
        $owner = $target->owner;
        $enemy = $owner !== null && $owner->universe->id === $hall->guild->universe->id ? $this->relations->guild($owner) : null;
        if ($owner?->id === null || $enemy?->id === null || $this->relations->guildStatus($hall->guild, $enemy) !== 'enemies') {
            throw new \DomainException('Une guerre active avec la guilde qui contrôle cette colonie est nécessaire.');
        }

        return [$target, $enemy->id];
    }

    private function lockPrivateBerth(PlayerUniverse $actor, GuildHall $hall, int $id): Fleet
    {
        $fleet = $this->repository->find(Fleet::class, $id);
        if (!$fleet instanceof Fleet || $fleet->playerUniverse->id !== $actor->id) {
            throw new \DomainException('Flotte du Centre inaccessible.');
        }
        $this->manager->lockCurrent($fleet);
        $receipt = $this->repository->one(GuildHallConvoy::class, ['fleet' => $fleet]);
        if ($fleet->playerUniverse->id !== $actor->id || $fleet->homePlanet->id === null
            || $fleet->status !== GuildCommandBerth::DOCKED || $fleet->mission !== GuildHallConvoy::MISSION
            || $fleet->destinationSystem?->id !== $hall->solarSystem?->id || $fleet->dispatchToken === ''
            || !$receipt instanceof GuildHallConvoy || !$receipt->commandStation || $receipt->shipDonation
            || $receipt->hallId !== $hall->id || $receipt->guildId !== $hall->guild->id || $receipt->settledAt === null) {
            throw new \DomainException('Seul le propriétaire d’une flotte privée à quai dans ce Hall peut l’engager.');
        }

        return $fleet;
    }
}
