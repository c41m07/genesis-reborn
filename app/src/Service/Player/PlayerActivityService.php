<?php
namespace App\Service\Player;

use App\Entity\{PlayerActivity, PlayerUniverse};
use App\Manager\GameManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class PlayerActivityService
{
    public const IDLE_SECONDS = 300;

    public function __construct(private EntityManagerInterface $em, private GameManager $manager, private ClockInterface $clock) {}

    public function record(PlayerUniverse $membership, int $idleSeconds = 0): void
    {
        if ($idleSeconds < 0 || $idleSeconds >= self::IDLE_SECONDS) return;
        $this->manager->transaction(function () use ($membership, $idleSeconds): void {
            $this->manager->lock($membership);
            if (!$membership->isPlayerControlled() || $membership->player->lockedAt !== null || !$membership->universe->isAccessible($this->clock->now())) return;
            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $seen = $now->modify('-'.$idleSeconds.' seconds');
            $activity = $this->em->find(PlayerActivity::class, $membership->id);
            if (!$activity) {
                $activity = new PlayerActivity();
                $activity->membership = $membership;
                $activity->trackedSince = $activity->sampledAt = $activity->lastSeenAt = $now;
                $this->em->persist($activity);
                return;
            }
            $this->manager->lock($activity);
            if ($now <= $activity->sampledAt) return;
            $elapsed = $now->getTimestamp() - $activity->sampledAt->getTimestamp();
            // Pas de temps reconstruit pour une interruption de plus de cinq minutes.
            if ($elapsed <= self::IDLE_SECONDS) {
                $start = $activity->sampledAt;
                while ($start < $now) {
                    $end = min($now, $start->modify('tomorrow')->setTime(0, 0));
                    $seconds = $end->getTimestamp() - $start->getTimestamp();
                    $day = $start->format('Y-m-d');
                    $activity->dailySeconds[$day] = ($activity->dailySeconds[$day] ?? 0) + $seconds;
                    $activity->totalSeconds += $seconds;
                    $start = $end;
                }
            }
            $activity->sampledAt = $now;
            $activity->lastSeenAt = max($activity->lastSeenAt, $seen);
            $cutoff = $now->modify('-6 days')->format('Y-m-d');
            $activity->dailySeconds = array_filter($activity->dailySeconds, static fn (string $day): bool => $day >= $cutoff, ARRAY_FILTER_USE_KEY);
        });
    }

    public function snapshot(?PlayerActivity $activity): array
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $cutoff = $now->modify('-6 days')->format('Y-m-d');
        return [
            'online'=>$activity && $activity->membership->isPlayerControlled() && $activity->membership->player->lockedAt === null && $activity->membership->universe->isAccessible($now) && $activity->lastSeenAt <= $now && $activity->lastSeenAt > $now->modify('-5 minutes'),
            'last_seen'=>$activity?->lastSeenAt, 'tracked_since'=>$activity?->trackedSince,
            'today'=>(int) ($activity?->dailySeconds[$now->format('Y-m-d')] ?? 0),
            'week'=>$activity ? (int) array_sum(array_filter($activity->dailySeconds, static fn (string $day): bool => $day >= $cutoff && $day <= $now->format('Y-m-d'), ARRAY_FILTER_USE_KEY)) : 0,
            'total'=>$activity?->totalSeconds ?? 0,
        ];
    }

    public function forPlayers(array $players): array
    {
        if ($players === []) return [];
        $activities = $this->em->createQueryBuilder()->select('a', 'm', 'u', 'p')->from(PlayerActivity::class, 'a')
            ->join('a.membership', 'm')->join('m.universe', 'u')->join('m.player', 'p')
            ->where('p IN (:players)')->setParameter('players', $players)->getQuery()->getResult();
        $result = [];
        foreach ($activities as $activity) {
            $id = $activity->membership->player->id;
            $snapshot = $this->snapshot($activity);
            $result[$id]['online'] = ($result[$id]['online'] ?? false) || $snapshot['online'];
            $result[$id]['last_seen'] = max($result[$id]['last_seen'] ?? $snapshot['last_seen'], $snapshot['last_seen']);
        }
        return $result;
    }
}
