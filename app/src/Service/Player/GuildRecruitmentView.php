<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\{Guild, GuildMember, GuildRecruitment, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class GuildRecruitmentView
{
    public function __construct(
        private GameRepository $repository,
        private DiplomacyAccessPolicy $access,
        private GameCatalog $catalog,
        private EntityManagerInterface $entities,
    ) {
    }

    /** @return array{applications: list<GuildRecruitment>, invitations: list<GuildRecruitment>} */
    public function inbox(PlayerUniverse $viewer): array
    {
        if (!$this->enabled($viewer)) {
            return ['applications' => [], 'invitations' => []];
        }
        $member = $this->member($viewer);
        if ($this->access->allows($member, DiplomacyAccessPolicy::RECRUIT)) {
            return [
                'applications' => $this->openQuery($viewer, $member)
                    ->addSelect('candidate', 'player')->leftJoin('candidate.player', 'player')
                    ->orderBy('r.id', 'ASC')->setMaxResults(100)->getQuery()->getResult(),
                'invitations' => [],
            ];
        }
        return [
            'applications' => [],
            'invitations' => $member === null
                ? $this->openQuery($viewer, null)->addSelect('guild')->orderBy('r.id', 'ASC')->setMaxResults(100)->getQuery()->getResult()
                : [],
        ];
    }

    public function unread(PlayerUniverse $viewer, ?GuildMember $member): int
    {
        // Aucun comptage ni trace d’une candidature pour les membres sans autorisation.
        if (!$this->enabled($viewer) || ($member !== null && !$this->access->allows($member, DiplomacyAccessPolicy::RECRUIT))) {
            return 0;
        }
        return (int) $this->openQuery($viewer, $member)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
    }

    public function onGuildProfile(PlayerUniverse $viewer, Guild $guild): array
    {
        $eligible = $this->enabled($viewer) && $this->member($viewer) === null;
        return [
            'eligible' => $eligible,
            'pending' => $eligible ? $this->repository->one(GuildRecruitment::class, ['guild' => $guild, 'candidate' => $viewer]) : null,
        ];
    }

    public function canInvite(PlayerUniverse $viewer, int $targetId): bool
    {
        if (!$this->enabled($viewer) || !$this->access->allows($member = $this->member($viewer), DiplomacyAccessPolicy::RECRUIT)) {
            return false;
        }
        $target = $this->repository->find(PlayerUniverse::class, $targetId);
        return $target !== null && $this->access->canRelateToPlayer($viewer, $target)
            && $this->member($target) === null
            && $this->repository->one(GuildRecruitment::class, ['guild' => $member->guild, 'candidate' => $target]) === null;
    }

    private function openQuery(PlayerUniverse $viewer, ?GuildMember $member): QueryBuilder
    {
        $query = $this->entities->createQueryBuilder()->select('r')->from(GuildRecruitment::class, 'r')
            ->join('r.guild', 'guild')->join('r.candidate', 'candidate')
            ->andWhere('guild.archivedAt IS NULL AND guild.universe = :universe AND candidate.universe = :universe')
            ->andWhere('candidate.player IS NOT NULL AND candidate.forgottenAt IS NULL')
            ->setParameter('universe', $viewer->universe);
        if ($member !== null) {
            return $query->andWhere('r.guild = :guild AND r.kind = :kind')
                ->setParameter('guild', $member->guild)->setParameter('kind', GuildRecruitment::APPLICATION);
        }
        return $query->andWhere('r.candidate = :viewer AND r.kind = :kind')
            ->setParameter('viewer', $viewer)->setParameter('kind', GuildRecruitment::INVITATION);
    }

    private function member(PlayerUniverse $viewer): ?GuildMember
    {
        return $this->repository->one(GuildMember::class, ['playerUniverse' => $viewer]);
    }

    private function enabled(PlayerUniverse $viewer): bool
    {
        return $viewer->isPlayerControlled() && (bool) $this->catalog->forUniverse($viewer->universe)['features']['guilds_enabled'];
    }
}
