<?php

declare(strict_types=1);

namespace App\Service\Queue;

use App\Entity\QueueItem;
use App\Service\Catalog\GameCatalog;

/** Projection et checkpoints du travail ; seul QueueService reprogramme le worker. */
final class LiveQueueTiming
{
    public static function fullDuration(array $timing, array $levels): int
    {
        $discount = GameCatalog::diminishingBonus($levels, $timing['cap'], $timing['max']);

        return max(1, (int) ceil($timing['base'] * ((100 - $discount) / 100)));
    }

    public static function waitingDuration(array $timing, array $levels): int
    {
        // Ce lot applique les gains de vitesse, pas un ralentissement administratif.
        return min($timing['full'], self::fullDuration($timing, $levels));
    }

    public static function remainingWork(array $timing, int $at): float
    {
        if ($timing['checkpoint'] === null) {
            return $timing['work'];
        }

        $elapsed = max(0, $at - $timing['checkpoint']);

        return max(0.0, $timing['work'] - $elapsed / $timing['full']);
    }

    public static function checkpoint(QueueItem $item, int $at, int $full, float $divisor = 1.0): void
    {
        if ($item->startedAt === null || $item->liveTiming === null) {
            throw new \LogicException('Le recalcul exige une commande suivie et en cours.');
        }
        $work = self::remainingWork($item->liveTiming, $at) / $divisor;
        $item->liveTiming['checkpoint'] = $at;
        $item->liveTiming['work'] = $work;
        $item->liveTiming['full'] = $full;
        // L’arrondi ne modifie que l’échéance et non le travail sauvé au checkpoint.
        $remaining = self::seconds($work, $full);
        $item->durationSeconds = max(1, $at + $remaining - $item->startedAt->getTimestamp());
    }

    public static function boostedRemainingSeconds(QueueItem $item, int $at, float $divisor): int
    {
        if ($item->liveTiming !== null) {
            return self::seconds(self::remainingWork($item->liveTiming, $at) / $divisor, $item->liveTiming['full']);
        }
        $elapsed = max(0, $at - $item->startedAt->getTimestamp());

        return max(0, QueueTiming::boostedDuration($item->durationSeconds, $elapsed, $divisor) - $elapsed);
    }

    /** @return array{start: ?\DateTimeImmutable, percent: float} */
    public static function progress(QueueItem $item): array
    {
        if ($item->liveTiming === null || $item->liveTiming['checkpoint'] === null) {
            return ['start' => $item->startedAt, 'percent' => 0.0];
        }

        return [
            'start' => $item->startedAt->setTimestamp($item->liveTiming['checkpoint']),
            'percent' => 100 * (1 - $item->liveTiming['work']),
        ];
    }

    private static function seconds(float $work, int $full): int
    {
        // Annuler uniquement le bruit binaire près d’une seconde entière.
        return $work <= 0.0 ? 0 : max(1, (int) ceil($work * $full - 1e-7));
    }
}
