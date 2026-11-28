<?php

namespace App\Service\Player;

use App\Entity\{Guild, LeaderboardSnapshot, PlayerUniverse, Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Queue\QueueService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class DailyLeaderboard
{
    public const TIMEZONE = 'Europe/Paris';
    public function __construct(
        private GameRepository $repo,
        private GameManager $manager,
        private EntityManagerInterface $em,
        private LeaderboardService $scores,
        private QueueService $queues,
        private ClockInterface $clock,
    )
    {
    }

    public function tick(): int
    {
        $now = $this->clock->now();
        // Une ancienne formule est recalculée une fois, même pendant le créneau courant.
        $universes = $this->em->createQueryBuilder()
            ->select('u')->from(Universe::class, 'u')
            ->leftJoin(LeaderboardSnapshot::class, 's', 'WITH', 's.universe = u')
            ->where('u.status = :active')
            ->andWhere('(u.startsAt IS NULL OR u.startsAt <= :now)')
            ->andWhere('(u.endsAt IS NULL OR u.endsAt > :now)')
            ->andWhere('(s.id IS NULL OR s.updatedAt < :periodStart OR s.scoreVersion != :scoreVersion)')
            ->andWhere('EXISTS (SELECT m.id FROM App\Entity\PlayerUniverse m WHERE m.universe = u AND m.player IS NOT NULL AND m.forgottenAt IS NULL)')
            ->setParameter('active', 'active')
            ->setParameter('now', $now)
            ->setParameter('periodStart', self::periodStart($now)->setTimezone(new \DateTimeZone('UTC')))
            ->setParameter('scoreVersion', LeaderboardService::SCORE_VERSION)
            ->getQuery()->getResult();
        foreach ($universes as $universe) {
            $this->refresh($universe);
        }
        return count($universes);
    }

    public function forUniverse(PlayerUniverse $viewer): array
    {
        $snapshot = $this->refresh($viewer->universe);
        $players = [];
        // Les identités et protections restent actuelles, seuls les scores et rangs sont figés.
        $live = [];
        foreach ($this->scores->forUniverse($viewer) as $row) {
            $live[$row['membership']->id] = $row;
        }
        foreach ($snapshot->players as $row) {
            $current = $live[$row['membership_id']] ?? null;
            if ($current !== null) {
                $players[] = $row + ['membership' => $current['membership'], 'protection' => $current['protection']];
            }
        }
        foreach (['buildings' => 'building_points', 'research' => 'research_points'] as $category => $field) {
            if (!array_filter($players, static fn(array $row): bool => !isset($row['ranks'][$category]))) {
                continue;
            }
            $sorted = $players;
            usort($sorted, static fn(array $a, array $b): int => $b[$field] <=> $a[$field] ?: strcasecmp($a['membership']->player->username, $b['membership']->player->username));
            $ranks = array_flip(array_column($sorted, 'membership_id'));
            foreach ($players as &$player) {
                $player['ranks'][$category] ??= $ranks[$player['membership_id']] + 1;
                $player['score_changes'][$category] ??= null;
                $player['rank_changes'][$category] ??= null;
            }
            unset($player);
        }
        $guilds = [];
        $currentGuilds = [];
        foreach ($this->repo->all(Guild::class, ['universe' => $viewer->universe, 'archivedAt' => null]) as $guild) {
            $currentGuilds[$guild->id] = $guild;
        }
        foreach ($snapshot->guilds as $row) {
            if (isset($currentGuilds[$row['guild_id']])) {
                $guilds[] = $row + ['guild' => $currentGuilds[$row['guild_id']]];
            }
        }
        return [
            'players' => $players,
            'guilds' => $guilds,
            'updated_at' => $snapshot->updatedAt,
            'comparison_at' => $snapshot->comparisonAt,
            'next_update' => self::periodStart($this->clock->now())->modify('+12 hours'),
        ];
    }

    /** Scores du profil : le recalcul quotidien reste commun, sans enrichissement de tout l’univers à chaud. */
    public function forPlayer(PlayerUniverse $viewer, PlayerUniverse $target): array
    {
        if (!$viewer->isPlayerControlled() || !$target->isPlayerControlled() || $target->universe->id !== $viewer->universe->id) {
            throw new \DomainException('Commandant inaccessible dans cet univers.');
        }
        $snapshot = $this->refresh($viewer->universe);
        foreach ($snapshot->players as $row) {
            if ($row['membership_id'] !== $target->id) {
                continue;
            }
            if (!isset($row['ranks']['buildings'], $row['ranks']['research'])) {
                // Les anciens snapshots complètent ces rangs avec le départage actuel par pseudo.
                foreach ($this->forUniverse($viewer)['players'] as $legacyRow) {
                    if ($legacyRow['membership_id'] === $target->id) {
                        return ['player' => $legacyRow, 'updated_at' => $snapshot->updatedAt];
                    }
                }
            }
            return ['player' => $row, 'updated_at' => $snapshot->updatedAt];
        }
        return ['player' => null, 'updated_at' => $snapshot->updatedAt];
    }

    private function refresh(Universe $universe): LeaderboardSnapshot
    {
        $periodStart = self::periodStart($this->clock->now());
        $snapshot = $this->repo->one(LeaderboardSnapshot::class, ['universe' => $universe]);
        if ($this->isCurrent($snapshot, $periodStart)) {
            return $snapshot;
        }
        // Rattraper les constructions terminées, même si le consommateur vient de redémarrer.
        foreach ($this->repo->all(PlayerUniverse::class, ['universe' => $universe]) as $membership) {
            if ($membership->isPlayerControlled()) {
                $this->queues->synchronize($membership);
            }
        }
        return $this->manager->transaction(function () use ($universe): LeaderboardSnapshot {
            // Le verrou de l'univers sérialise l'initialisation et les recalculs concurrents.
            $this->manager->lock($universe);
            $snapshot = $this->repo->one(LeaderboardSnapshot::class, ['universe' => $universe]);
            if ($snapshot !== null) {
                $this->manager->lock($snapshot);
            }
            $now = $this->clock->now();
            if ($this->isCurrent($snapshot, self::periodStart($now))) {
                return $snapshot;
            }
            // En Alpha, le premier recalcul pondéré se compare aussi au dernier relevé brut.
            $previousPlayers = $snapshot === null ? [] : array_column($snapshot->players, null, 'membership_id');
            $previousGuilds = $snapshot === null ? [] : array_column($snapshot->guilds, null, 'guild_id');
            $viewer = null;
            foreach ($this->repo->all(PlayerUniverse::class, ['universe' => $universe]) as $membership) {
                if ($membership->isPlayerControlled()) {
                    $viewer = $membership;
                    break;
                }
            }
            $rows = $viewer === null ? [] : $this->scores->forUniverse($viewer);
            $guildRows = $viewer === null ? [] : $this->scores->guildsForUniverse($viewer, $rows);
            $players = [];
            foreach ($rows as $row) {
                $id = $row['membership']->id;
                unset($row['membership'], $row['protection']);
                $old = $previousPlayers[$id] ?? null;
                foreach ([
                    'points' => 'total_points',
                    'buildings' => 'building_points',
                    'research' => 'research_points',
                    'fleet' => 'fleet_points',
                    'planets' => 'planets',
                ] as $sort => $field) {
                    $row['score_changes'][$sort] = $old === null ? null : $row[$field] - $old[$field];
                    $row['rank_changes'][$sort] = !isset($old['ranks'][$sort]) ? null : $old['ranks'][$sort] - $row['ranks'][$sort];
                }
                $players[] = $row + ['membership_id' => $id];
            }
            $guilds = [];
            foreach ($guildRows as $row) {
                $id = $row['guild']->id;
                unset($row['guild']);
                $old = $previousGuilds[$id] ?? null;
                $row['points_change'] = $old === null ? null : $row['total_points'] - $old['total_points'];
                $row['rank_change'] = $old === null ? null : $old['rank'] - $row['rank'];
                $guilds[] = $row + ['guild_id' => $id];
            }
            $snapshot ??= new LeaderboardSnapshot();
            $snapshot->universe = $universe;
            $snapshot->comparisonAt = isset($snapshot->updatedAt) ? $snapshot->updatedAt : null;
            $snapshot->scoreVersion = LeaderboardService::SCORE_VERSION;
            $snapshot->updatedAt = $now->setTimezone(new \DateTimeZone('UTC'));
            $snapshot->players = $players;
            $snapshot->guilds = $guilds;
            $this->manager->save($snapshot);
            return $snapshot;
        });
    }

    private function isCurrent(?LeaderboardSnapshot $snapshot, \DateTimeImmutable $periodStart): bool
    {
        return $snapshot !== null
            && $snapshot->scoreVersion === LeaderboardService::SCORE_VERSION
            && $snapshot->updatedAt >= $periodStart;
    }

    private static function periodStart(\DateTimeImmutable $at): \DateTimeImmutable
    {
        $local = $at->setTimezone(new \DateTimeZone(self::TIMEZONE));
        return $local->setTime((int) $local->format('G') >= 12 ? 12 : 0, 0);
    }
}
