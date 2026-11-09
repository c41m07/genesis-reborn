<?php
namespace App\Service\Boost;

use App\Entity\Fleet;
use App\Entity\PlayerBoost;
use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;

final class BoostGrantService
{
    private const RISK_MISSIONS = ['attack', 'defend', 'conquer', 'explore', 'spy'];

    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private GameCatalog $catalog,
        private ClockInterface $clock,
        private ResourceBoxPolicy $boxes,
    ) {}

    public function awardRiskMission(PlayerUniverse $membership, Fleet $fleet): bool
    {
        if (!in_array($fleet->mission, self::RISK_MISSIONS, true)) {
            throw new \DomainException('Cette mission ne peut pas attribuer un boost de risque.');
        }

        return $this->manager->transaction(fn (): bool => $this->grant(
            $membership,
            'risk_mission',
            'risk-fleet-' . $fleet->id,
            true,
        ));
    }

    public function awardEvent(PlayerUniverse $membership, string $eventKey, ?array $boxPayload = null): bool
    {
        $eventKey = trim($eventKey);
        if ($eventKey === '' || strlen($eventKey) > 80) {
            throw new \DomainException('Identifiant d’événement invalide.');
        }

        return $this->manager->transaction(fn (): bool => $this->grant(
            $membership,
            'event',
            'event-' . $eventKey . '-player-' . $membership->id,
            false,
            $boxPayload,
        ));
    }

    public function inventory(PlayerUniverse $membership): array
    {
        return array_values(array_filter(
            $this->repo->boostsFor($membership),
            static fn (PlayerBoost $boost): bool => $boost->status === 'available',
        ));
    }

    public function pendingCount(PlayerUniverse $membership): int
    {
        return $this->repo->countBoosts($membership, 'pending');
    }

    public function inventoryCapacity(): int
    {
        return max(1, min(100, (int) ($this->catalog->all('boosts')['inventory_capacity'] ?? 100)));
    }

    public function durationReductionRatio(): float
    {
        $ratio = (float) ($this->catalog->all('boosts')['duration_reduction_ratio'] ?? 0.25);
        if (!is_finite($ratio) || $ratio <= 0 || $ratio >= 1) {
            throw new \LogicException('Ratio de réduction des boosts invalide.');
        }

        return $ratio;
    }

    private function grant(PlayerUniverse $membership, string $sourceType, string $sourceKey, bool $riskMission, ?array $boxPayload = null): bool
    {
        $this->manager->lock($membership);

        if ($this->repo->one(PlayerBoost::class, ['sourceKey' => $sourceKey])) {
            return false;
        }

        $now = $this->clock->now();
        if ($riskMission && $this->riskMissionDailyLimitReached($membership, $now)) {
            return false;
        }

        $boost = new PlayerBoost();
        $boost->playerUniverse = $membership;
        $boost->sourceType = $sourceType;
        $boost->sourceKey = $sourceKey;
        $boost->kind = $boxPayload === null ? 'boost' : 'resource_box';
        $boost->payload = $boxPayload === null ? null : $this->boxes->validatePayload($boxPayload);
        $boost->earnedAt = $now;
        $boost->status = $this->repo->countBoosts($membership, 'available') < $this->inventoryCapacity()
            ? 'available'
            : 'pending';

        $this->manager->save($boost);
        $this->manager->flush();

        return true;
    }

    /** La participation est déjà verrouillée dans la transaction de consommation. */
    public function promoteOldestPendingLocked(PlayerUniverse $membership): void
    {
        if ($this->repo->countBoosts($membership,'available') >= $this->inventoryCapacity()) return;
        $pending=$this->repo->oldestBoost($membership,'pending');
        if ($pending) {
            $this->manager->lock($pending);
            if ($pending->status==='pending') $pending->status='available';
        }
    }

    private function riskMissionDailyLimitReached(PlayerUniverse $membership, \DateTimeImmutable $now): bool
    {
        $settings = $this->catalog->all('boosts');
        $timezone = new \DateTimeZone((string) ($settings['risk_mission_timezone'] ?? 'UTC'));
        $localNow = $now->setTimezone($timezone);
        $dayStart = $localNow->setTime(0, 0)->setTimezone(new \DateTimeZone('UTC'));
        $dayEnd = $dayStart->modify('+1 day');
        $limit = max(0, min(10, (int) ($settings['risk_mission_daily_limit'] ?? 1)));

        return $limit === 0 || $this->repo->riskBoostCountSince($membership, $dayStart, $dayEnd) >= $limit;
    }
}
