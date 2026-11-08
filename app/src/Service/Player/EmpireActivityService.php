<?php
namespace App\Service\Player;

use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use Psr\Clock\ClockInterface;

final class EmpireActivityService
{
    public const REQUEST_ATTRIBUTE = '_empire_activity_membership';
    public const INACTIVE_AFTER_SECONDS = 14 * 86400;
    public const CONVERSION_AFTER_SECONDS = 30 * 86400;

    public function __construct(private GameManager $manager, private ClockInterface $clock, private VacationCalendar $vacations, private VacationPolicy $vacationPolicy,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(bool:FORGOTTEN_EMPIRES_ENABLED)%')] private bool $enabled=false) {}

    /** Signale l'échéance ; le contrôle n'est retiré qu'au détachement effectif. */
    public function isAwaitingConversion(PlayerUniverse $membership): bool
    {
        return $this->enabled && $membership->isPlayerControlled() && $this->snapshot($membership)['conversion_due'];
    }

    public function assertCanControl(PlayerUniverse $membership): void
    {
        if(!$membership->isPlayerControlled()) throw new \DomainException('Cet empire oublié n’est plus contrôlable.');
    }

    public function recordVisit(PlayerUniverse $membership): void
    {
        if($membership->isForgotten()) return;
        $visitedAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        if ($membership->lastActivityAt !== null && $membership->lastActivityAt >= $visitedAt) return;

        $this->manager->transaction(function () use ($membership, $visitedAt): void {
            $this->manager->lock($membership);
            if($membership->isForgotten()) return;
            if ($this->vacations->isActive($membership, $visitedAt)) return;
            // Une requête plus ancienne ne doit pas effacer une visite concurrente.
            if ($membership->lastActivityAt === null || $membership->lastActivityAt < $visitedAt) {
                $membership->lastActivityAt = $visitedAt;
            }
        });
    }

    /** @return array{status: string, inactive_at: ?string, conversion_due_at: ?string, conversion_due: bool} */
    public function snapshot(PlayerUniverse $membership, ?\DateTimeImmutable $at = null): array
    {
        $now = $at ?? $this->clock->now();
        if($membership->isForgotten()) return ['status'=>'forgotten','inactive_at'=>null,'conversion_due_at'=>null,'conversion_due'=>false];
        $periods = $this->vacations->periods($membership);
        $onVacation = $this->vacations->activeAt($periods, $now);
        if ($membership->lastActivityAt === null) {
            return ['status' => $onVacation ? 'vacation' : 'untracked', 'inactive_at' => null, 'conversion_due_at' => null, 'conversion_due' => false];
        }

        $lastActivityAt = $membership->lastActivityAt->setTimezone(new \DateTimeZone('UTC'));
        $inactiveAt = $this->vacationPolicy->deadlineAfterActiveSeconds($periods, $lastActivityAt, self::INACTIVE_AFTER_SECONDS);
        $conversionAt = EmpireConversionSchedule::atOrAfter($this->vacationPolicy->deadlineAfterActiveSeconds($periods, $lastActivityAt, self::CONVERSION_AFTER_SECONDS));

        return [
            'status' => $onVacation ? 'vacation' : ($now >= $inactiveAt ? 'inactive' : 'active'),
            'inactive_at' => $inactiveAt->format(DATE_ATOM),
            'conversion_due_at' => $conversionAt->format(DATE_ATOM),
            'conversion_due' => !$onVacation && $now >= $conversionAt,
        ];
    }

    public function inactiveSeconds(PlayerUniverse $membership, ?\DateTimeImmutable $at = null): ?int
    {
        if ($membership->lastActivityAt === null) return null;
        $now = $at ?? $this->clock->now();
        return max(0, $now->getTimestamp() - $membership->lastActivityAt->getTimestamp()
            - $this->vacationPolicy->pausedSeconds($this->vacations->periods($membership), $membership->lastActivityAt, $now));
    }
}
