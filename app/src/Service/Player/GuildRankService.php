<?php
namespace App\Service\Player;

use App\Entity\{GuildMember,GuildRank,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;

final class GuildRankService
{
    public function __construct(
        private GameRepository $repo,
        private GameManager $manager,
        private DiplomacyAccessPolicy $access,
        private EmpireActivityService $activity,
        private GameCatalog $catalog,
        private ClockInterface $clock,
    ) {}

    private function lockActor(PlayerUniverse $actor): GuildMember
    {
        $this->manager->lock($actor);
        $this->manager->lock($actor->universe);
        $this->activity->assertCanControl($actor);
        if (!$actor->universe->isAccessible($this->clock->now())
            || !$this->catalog->forUniverse($actor->universe)['features']['guilds_enabled']) {
            throw new \DomainException('Les grades sont indisponibles dans cet univers.');
        }
        $member = $this->repo->one(GuildMember::class, ['playerUniverse' => $actor]);
        if ($member === null) throw new \DomainException('Rejoignez une guilde pour gérer les grades.');
        $this->manager->lock($member);
        $this->manager->lock($member->guild);
        if ($member->guild->archivedAt !== null || $member->guild->universe->id !== $actor->universe->id) {
            throw new \DomainException('Guilde inaccessible.');
        }
        if ($member->rank !== null) $this->manager->lock($member->rank);

        return $member;
    }

    public function save(PlayerUniverse $actor, int $rankId, string $name, int $position, array $permissions, int $version): void
    {
        $this->manager->transaction(function () use ($actor, $rankId, $name, $position, $permissions, $version): void {
            $member = $this->lockActor($actor);
            if (!$this->access->canCommand($actor, $member->guild)) {
                throw new \DomainException('Seul le dirigeant peut créer ou modifier les grades.');
            }
            $name = trim($name);
            if (mb_strlen($name) < 1 || mb_strlen($name) > 60 || $position < 1 || $position > 1000) {
                throw new \DomainException('Indiquez un nom de 1 à 60 caractères et une priorité de 1 à 1000.');
            }
            foreach ($permissions as $permission) {
                if (!is_string($permission) || !array_key_exists($permission, DiplomacyAccessPolicy::PERMISSIONS)) {
                    throw new \DomainException('Permission de grade inconnue.');
                }
            }
            $rank = $rankId === 0 ? new GuildRank() : $this->repo->find(GuildRank::class, $rankId);
            if ($rank === null || ($rankId !== 0 && $rank->guild->id !== $member->guild->id)) {
                throw new \DomainException('Grade inaccessible.');
            }
            if ($rankId !== 0) {
                $this->manager->lock($rank);
                if ($rank->version !== $version) throw new \DomainException('Ce grade a changé. Rechargez la page.');
            }
            $duplicate = $this->repo->one(GuildRank::class, ['guild' => $member->guild, 'name' => $name]);
            if ($duplicate !== null && ($rankId === 0 || $duplicate->id !== $rankId)) {
                throw new \DomainException('Ce nom de grade existe déjà.');
            }
            $rank->guild = $member->guild;
            $rank->name = $name;
            $rank->position = $position;
            $rank->permissions = array_values(array_unique($permissions));
            $this->manager->save($rank);
        });
    }

    public function assign(PlayerUniverse $actor, int $targetId, int $rankId, int $expectedRankId): void
    {
        $this->manager->transaction(function () use ($actor, $targetId, $rankId, $expectedRankId): void {
            $member = $this->lockActor($actor);
            $target = $this->repo->find(GuildMember::class, $targetId);
            $rank = $rankId === 0 ? null : $this->repo->find(GuildRank::class, $rankId);
            if ($target === null || ($rankId !== 0 && $rank === null)) {
                throw new \DomainException('Membre ou grade inaccessible.');
            }
            $this->manager->lock($target);
            if ($target->rank !== null) $this->manager->lock($target->rank);
            if ($rank !== null) $this->manager->lock($rank);
            if (($target->rank?->id ?? 0) !== $expectedRankId) {
                throw new \DomainException('Le grade de ce membre a changé. Rechargez la page.');
            }
            if (!$this->access->canAssignRank($member, $target, $rank)) {
                throw new \DomainException('Vous ne pouvez pas attribuer ce grade à ce membre.');
            }
            $target->rank = $rank;
        });
    }
}
