<?php
namespace App\Service\Player;

use App\Entity\{PlayerUniverse, VacationPeriod};
use Psr\Clock\ClockInterface;

final class VacationPolicy
{
    public const MINIMUM_SECONDS = 48 * 3600;
    public const MAXIMUM_SECONDS = 90 * 86400;
    public const COOLDOWN_SECONDS = 15 * 86400;

    public function __construct(private ClockInterface $clock) {}

    /**
     * Prépare une période non persistée ; ne met pas encore l'empire en vacances.
     * Le service d'activation doit charger la dernière période sous verrou.
     */
    public function plan(PlayerUniverse $membership, ?VacationPeriod $previous = null): VacationPeriod
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        if ($previous !== null) {
            if ($previous->membership !== $membership && ($membership->id === null || $previous->membership->id !== $membership->id)) {
                throw new \DomainException('La période précédente appartient à un autre empire.');
            }
            if ($previous->effectiveEndAt() > $now) throw new \DomainException('Une période de vacances est déjà prévue ou en cours.');
            if ($this->nextActivationAt($previous) > $now) throw new \DomainException('Attendez 15 jours après la fin des dernières vacances.');
        }
        $period = new VacationPeriod();
        $period->membership = $membership;
        $period->startedAt = $now;
        $period->scheduledEndAt = $now->modify('+' . self::MAXIMUM_SECONDS . ' seconds');
        return $period;
    }

    /** Modifie la période ; VacationEconomyService coordonne la reprise économique. */
    public function finish(VacationPeriod $period): void
    {
        if ($period->endedAt !== null) return;
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        if ($now < $period->startedAt->setTimezone(new \DateTimeZone('UTC'))->modify('+' . self::MINIMUM_SECONDS . ' seconds')) {
            throw new \DomainException('Le mode vacances doit durer au moins 48 heures.');
        }
        $period->endedAt = $now < $period->scheduledEndAt ? $now : $period->scheduledEndAt;
    }

    public function nextActivationAt(VacationPeriod $period): \DateTimeImmutable
    {
        return $period->effectiveEndAt()->setTimezone(new \DateTimeZone('UTC'))->modify('+' . self::COOLDOWN_SECONDS . ' seconds');
    }

    /** @param iterable<VacationPeriod> $periods */
    public function pausedSeconds(iterable $periods, \DateTimeImmutable $from, \DateTimeImmutable $until): int
    {
        if ($until <= $from) return 0;
        $seconds = 0;
        foreach ($this->mergedIntervals($periods, $from, $until) as [$start, $end]) $seconds += $end - $start;
        return $seconds;
    }

    /** @param iterable<VacationPeriod> $periods */
    public function deadlineAfterActiveSeconds(iterable $periods, \DateTimeImmutable $from, int $seconds): \DateTimeImmutable
    {
        $deadline = $from->getTimestamp() + max(0, $seconds);
        foreach ($this->mergedIntervals($periods, $from) as [$start, $end]) {
            if ($start > $deadline) break;
            $deadline += $end - $start;
        }
        return $from->setTimezone(new \DateTimeZone('UTC'))->setTimestamp($deadline);
    }

    /** @param iterable<VacationPeriod> $periods
     *  @return list<array{int,int}>
     */
    private function mergedIntervals(iterable $periods, \DateTimeImmutable $from, ?\DateTimeImmutable $until = null): array
    {
        $intervals = [];
        foreach ($periods as $period) {
            $start = max($from->getTimestamp(), $period->startedAt->getTimestamp());
            $end = min($until?->getTimestamp() ?? PHP_INT_MAX, $period->effectiveEndAt()->getTimestamp());
            if ($end > $start) $intervals[] = [$start, $end];
        }
        usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = array_key_last($merged);
            if ($last !== null && $start <= $merged[$last][1]) $merged[$last][1] = max($merged[$last][1], $end);
            else $merged[] = [$start, $end];
        }
        return $merged;
    }
}
