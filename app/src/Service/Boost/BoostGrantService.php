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

    /** Le contrôleur des quotidiennes détient déjà la transaction et les verrous. */
    public function awardDailyLocked(PlayerUniverse $membership, int $accountId, string $date, string $effect): bool
    {
        TimedBoostEffects::definition($effect);

        return $this->grant($membership, 'daily_reward', 'daily-'.$accountId.'-'.$date, false, timedEffect: $effect);
    }

    /** Le worker tient déjà le verrou du bénéficiaire et du droit de livraison. */
    public function awardConvoyBoxLocked(PlayerUniverse $membership, int $deliveryId, array $resources): bool
    {
        return $this->grant($membership, 'pirate_convoy', 'pirate-convoy-'.$deliveryId, false,
            ['format' => 'convoy', 'resources' => $resources]);
    }

    /** Le dépôt en entrepôt et la boîte partagent la transaction du retour commercial. */
    public function grantMarketOverflowLocked(PlayerUniverse $membership, int $tradeId, string $resource, float $amount): void
    {
        $created = $this->grant($membership, 'market_overflow', 'market-overflow-'.$tradeId, false,
            ['format' => 'market_overflow', 'resources' => [$resource => $amount]]);
        if (!$created) {
            // Un retour déjà terminé est écarté par le jeton avant le dépôt.
            // Une boîte préexistante à ce stade impose d’annuler aussi le crédit en entrepôt.
            throw new \LogicException('Une boîte de surplus existe déjà pour ce convoi.');
        }
    }

    public function awardGuildProject(PlayerUniverse $membership,int $definitionId): bool
    {
        if($definitionId<1) throw new \DomainException('Modèle de projet invalide.');
        return $this->manager->transaction(fn(): bool=>$this->grant(
            $membership,'guild_project',$this->guildProjectKey($membership,$definitionId),false
        ));
    }

    public function hasGuildProjectReward(PlayerUniverse $membership,int $definitionId): bool
    {
        return $this->repo->one(PlayerBoost::class,['sourceKey'=>$this->guildProjectKey($membership,$definitionId)])!==null;
    }

    /** @param list<int> $definitionIds @return array<int, true> Modèles déjà récompensés pour ce compte. */
    public function guildProjectRewardedDefinitions(PlayerUniverse $membership, array $definitionIds): array
    {
        $keys = [];
        foreach ($definitionIds as $id) $keys[$id] = $this->guildProjectKey($membership, $id);
        // La clé historique porte le compte : changer d’empire ou utiliser le boost ne redonne aucun droit.
        $existing = array_fill_keys($this->repo->existingBoostSourceKeys(array_values($keys)), true);
        $rewarded = [];
        foreach ($keys as $id => $key) if (isset($existing[$key])) $rewarded[$id] = true;
        return $rewarded;
    }

    private function guildProjectKey(PlayerUniverse $membership,int $definitionId): string
    {
        $accountId=($membership->player??$membership->formerPlayer)?->id;
        if($accountId===null) throw new \DomainException('Compte du contributeur introuvable.');
        return 'guild-project-'.$definitionId.'-account-'.$accountId;
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

    private function grant(PlayerUniverse $membership, string $sourceType, string $sourceKey, bool $riskMission, ?array $boxPayload = null, ?string $timedEffect = null): bool
    {
        $this->manager->lock($membership);

        if ($this->repo->one(PlayerBoost::class, ['sourceKey' => $sourceKey])) {
            return false;
        }

        $now = $this->clock->now();
        if ($riskMission && $this->riskMissionDailyLimitReached($membership, $now)) {
            return false;
        }

        $this->refreshTimedInventoryLocked($membership, $now);

        $boost = new PlayerBoost();
        $boost->playerUniverse = $membership;
        $boost->sourceType = $sourceType;
        $boost->sourceKey = $sourceKey;
        $boost->kind = $timedEffect !== null ? TimedBoostEffects::KIND : ($boxPayload === null ? 'boost' : 'resource_box');
        $boost->payload = $timedEffect !== null ? ['effect' => $timedEffect] : ($boxPayload === null ? null : $this->boxes->validatePayload($boxPayload));
        $boost->earnedAt = $now;
        $boost->status = $this->repo->countBoosts($membership, 'available') < $this->inventoryCapacity()
            ? 'available'
            : 'pending';
        // Une promotion conserve la lecture initiale et ne signale jamais deux fois le même objet.
        $boost->giftUnread = $boxPayload !== null || $timedEffect !== null || $boost->status === 'pending';

        $this->manager->save($boost);
        $this->manager->flush();

        return true;
    }

    public function refreshTimedInventory(PlayerUniverse $membership): void
    {
        $this->manager->transaction(function () use ($membership): void {
            $this->manager->lock($membership);
            $this->refreshTimedInventoryLocked($membership, $this->clock->now());
        });
    }

    private function refreshTimedInventoryLocked(PlayerUniverse $membership, \DateTimeImmutable $now): void
    {
        $cutoff = $now->setTimestamp($now->getTimestamp() - TimedBoostEffects::SHELF_SECONDS);
        $expired = $this->repo->expiredTimedBoosts($membership, $cutoff);
        if ($expired === []) {
            return;
        }
        foreach ($expired as $boost) {
            // L’expiration ne touche jamais les fenêtres déjà activées : leur
            // historique reste nécessaire aux rattrapages économiques.
            $boost->status = 'expired';
        }
        $this->manager->flush();
        $free = $this->inventoryCapacity() - $this->repo->countBoosts($membership, 'available');
        for ($place = 0; $place < $free; ++$place) {
            if (!$this->promoteOldestPendingLocked($membership)) {
                break;
            }
            $this->manager->flush();
        }
    }

    /** La participation est déjà verrouillée dans la transaction de consommation. */
    public function promoteOldestPendingLocked(PlayerUniverse $membership): bool
    {
        if ($this->repo->countBoosts($membership, 'available') >= $this->inventoryCapacity()) {
            return false;
        }
        while ($pending = $this->repo->oldestBoost($membership, 'pending')) {
            $this->manager->lock($pending);
            if ($pending->kind === TimedBoostEffects::KIND && TimedBoostEffects::expiresAt($pending) <= $this->clock->now()) {
                $pending->status = 'expired';
                $this->manager->flush();
                continue;
            }
            $pending->status = 'available';

            return true;
        }

        return false;
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
