<?php
namespace App\Service\Player;

use App\Entity\{PlayerUniverse, VacationPeriod};
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;

final class VacationCalendar
{
    public function __construct(private GameRepository $repo, private ClockInterface $clock) {}

    /** @return list<VacationPeriod> */
    public function periods(PlayerUniverse $membership): array
    {
        return $membership->id === null ? [] : $this->repo->all(VacationPeriod::class, ['membership' => $membership], ['startedAt' => 'ASC', 'id' => 'ASC']);
    }

    /** @param iterable<VacationPeriod> $periods */
    public function activeAt(iterable $periods, \DateTimeImmutable $at): bool
    {
        foreach ($periods as $period) if ($period->isActiveAt($at)) return true;
        return false;
    }

    public function isActive(PlayerUniverse $membership, ?\DateTimeImmutable $at = null): bool
    {
        return $this->currentPeriod($membership, $at) !== null;
    }

    public function currentPeriod(PlayerUniverse $membership, ?\DateTimeImmutable $at = null): ?VacationPeriod
    {
        $at ??= $this->clock->now();
        foreach ($this->periods($membership) as $period) if ($period->isActiveAt($at)) return $period;
        return null;
    }

    public function assertCanSendFleet(PlayerUniverse $membership): void
    {
        if(!$membership->isPlayerControlled()) throw new \DomainException('Cet empire oublié ne peut plus lancer de mission de joueur.');
        if ($this->isActive($membership)) throw new \DomainException('Les missions et leurs accélérations sont indisponibles pendant les vacances.');
    }

    public function assertCanManageQueues(PlayerUniverse $membership): void
    {
        if(!$membership->isPlayerControlled()) throw new \DomainException('Cet empire oublié ne peut plus recevoir de commande de joueur.');
        if ($this->isActive($membership)) throw new \DomainException('Les files sont suspendues pendant le mode vacances.');
    }
}
