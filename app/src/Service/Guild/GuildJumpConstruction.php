<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildJumpPoint, GuildMember, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Player\DiplomacyAccessPolicy;
use Psr\Clock\ClockInterface;

/** Un chantier individuel payé par la guilde ; il devient utilisable à son échéance. */
final readonly class GuildJumpConstruction
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private GuildHallAccess $access,
        private GuildHallScheduleProvider $schedules,
        private GuildHallStockStore $stocks,
        private DiplomacyAccessPolicy $diplomacy,
        private ClockInterface $clock,
    ) {
    }

    /** @return array{cost:array<string,int>,duration:int,maxLevel:int,instability:int}|null */
    public function quote(): ?array
    {
        $schedule = $this->schedules->schedule();

        return $schedule?->maximumLevel(GuildJumpPoint::SCHEDULE_SLUG) === 1
            ? $schedule->quote(GuildJumpPoint::SCHEDULE_SLUG, 0) + ['instability' => $schedule->jumpInstabilitySeconds] : null;
    }

    /** @param array{cost:array<string,int>,duration:int,maxLevel:int,instability:int} $quote */
    public static function fingerprint(array $quote): string
    {
        $cost = $quote['cost'];
        ksort($cost);

        return hash('sha256', json_encode([$quote['duration'], $quote['instability'], $cost], JSON_THROW_ON_ERROR));
    }

    public function start(PlayerUniverse $actor, int $guildId, int $hallId, int $colonyId, string $reviewed): GuildJumpPoint
    {
        return $this->manager->transaction(function () use ($actor, $guildId, $hallId, $colonyId, $reviewed): GuildJumpPoint {
            $hall = $this->access->lockPlaced($actor, $guildId, $hallId);
            if (!$this->diplomacy->canCommand($actor, $hall->guild)) {
                throw new \DomainException('Seul le commandant actuel peut installer un Jump Point.');
            }
            $quote = $this->quote();
            if ($quote === null || !hash_equals(self::fingerprint($quote), $reviewed)) {
                throw new \DomainException('Le devis d’installation n’est plus disponible. Rouvrez le Hall.');
            }

            $colony = $this->repository->find(Planet::class, $colonyId);
            if (!$colony instanceof Planet || $colony->owner === null || $colony->npcPlanet
                || $colony->solarSystem->galaxy->universe->id !== $actor->universe->id) {
                throw new \DomainException('Sélectionnez une colonie d’un membre actuel.');
            }
            $this->manager->lockCurrent($colony);
            $owner = $colony->owner;
            if ($owner === null || !$owner->isPlayerControlled() || $colony->npcPlanet) {
                throw new \DomainException('Cette colonie n’appartient plus à un membre actif.');
            }
            // Les mutations d’adhésion et de propriétaire passent par le verrou d’univers
            // que lockPlaced a déjà pris. Relire le lien avant de toucher au stock commun.
            $member = $this->repository->one(GuildMember::class, ['guild' => $hall->guild, 'playerUniverse' => $owner]);
            if (!$member instanceof GuildMember) {
                throw new \DomainException('Cette colonie n’appartient plus à la guilde.');
            }

            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $point = $this->repository->one(GuildJumpPoint::class, ['planet' => $colony]);
            if ($point instanceof GuildJumpPoint) {
                $this->manager->lockCurrent($point);
                if ($point->isUnstableAt($now)) {
                    throw new \DomainException('Attendez la disparition du Jump Point instable avant d’en installer un autre.');
                }
                if ($point->hall->id === $hall->id && $point->member?->id === $member->id
                    && $point->acquisitionAt?->getTimestamp() === $colony->acquiredAt?->getTimestamp()) {
                    throw new \DomainException('Cette colonie a déjà un Jump Point ou un chantier en cours.');
                }
            } else {
                $point = new GuildJumpPoint();
                $point->planet = $colony;
            }

            $this->stocks->consumeLocked($hall, $quote['cost']);
            $point->hall = $hall;
            $point->member = $member;
            $point->originalOwnerId = $owner->id;
            $point->instabilitySeconds = $quote['instability'];
            $point->unstableUntil = null;
            $point->acquisitionAt = $colony->acquiredAt;
            // Aucun effet à publier à l’échéance : l’éligibilité relit la date et l’adhésion.
            $point->readyAt = $now->modify('+'.$quote['duration'].' seconds');
            $this->manager->save($point);

            return $point;
        });
    }
}
