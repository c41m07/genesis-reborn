<?php

declare(strict_types=1);

namespace App\Service\Market;

use App\Entity\{AdminAuditLog, MarketMerchantDelivery, Player, ResourceMarket, Universe};
use App\Manager\GameManager;
use App\Repository\{GameRepository, ResourceMarketRepository};
use Psr\Clock\ClockInterface;

final class WeeklyMerchantDelivery
{
    private int $nextScan = 0;

    public function __construct(
        private readonly WeeklyMerchantRestock $calculator,
        private readonly ResourceMarketRepository $markets,
        private readonly GameRepository $repo,
        private readonly GameManager $manager,
        private readonly ClockInterface $clock,
    ) {
    }

    public function tick(): void
    {
        $now = $this->clock->now();
        if ($now->getTimestamp() < $this->nextScan) {
            return;
        }
        $this->nextScan = $now->getTimestamp() + 30;
        $current = $this->calculator->window($now);
        foreach ($this->markets->dueUniverses($current['occurrence']) as $id) {
            $universe = $this->repo->find(Universe::class, $id);
            if (!$universe || !$universe->isAccessible($now)) {
                continue;
            }
            $this->deliverNext($universe, $now);
        }
    }

    /**
     * Le verrou d’univers protège aussi les échanges à la station. Consommer une
     * occurrence et augmenter le stock forment une seule écriture atomique.
     */
    public function deliverNext(Universe $universe, \DateTimeImmutable $now): void
    {
        $current = $this->calculator->window($now);
        $this->manager->transaction(function () use ($universe, $now, $current): void {
            $this->manager->lockCurrent($universe);
            $market = $this->markets->find($universe);
            if (!$market || !$universe->isAccessible($now)) {
                return;
            }
            $this->manager->lockCurrent($market);
            if (!$market->enabled || $market->lastOccurrence >= $current['occurrence']) {
                return;
            }

            // Avancer d’un vendredi à la fois, y compris si le relais a été
            // indisponible plusieurs semaines. Le calcul civil conserve 9 h à Paris.
            $after = new \DateTimeImmutable($market->lastOccurrence.' 09:00:00', new \DateTimeZone('Europe/Paris'));
            $window = $this->calculator->window($after->modify('+7 days'));
            $sold = $this->markets->soldBetween($universe, $window['from'], $window['until']);
            $delivery = new MarketMerchantDelivery();
            $delivery->universe = $universe;
            $delivery->occurrence = $window['occurrence'];
            $delivery->appliedAt = $now->setTimezone(new \DateTimeZone('UTC'));
            $delivery->quantities = $this->calculator->quantities($market->stock, $market->targets, $sold);
            foreach ($delivery->quantities as $resource => $amount) {
                $market->stock[$resource] = ($market->stock[$resource] ?? 0) + $amount;
            }
            $market->lastOccurrence = $delivery->occurrence;
            $this->manager->save($delivery);
        });
    }

    /** Première ouverture : matérialiser la réserve administrée, une seule fois. */
    public function configure(Player $admin, Universe $universe, bool $enabled, array $targets): void
    {
        foreach (array_keys(MarketPricing::WEIGHTS) as $resource) {
            if (!isset($targets[$resource]) || !is_int($targets[$resource]) || $targets[$resource] < 0 || $targets[$resource] > MarketPricing::MAX_STOCK) {
                throw new \DomainException('Indiquez une cible entre 0 et '.MarketPricing::MAX_STOCK.' pour chaque matériau.');
            }
        }
        if ($enabled && array_sum($targets) === 0) {
            throw new \DomainException('Définissez un stock cible non nul avant l’ouverture.');
        }
        $targets = array_intersect_key($targets, MarketPricing::WEIGHTS);

        $this->manager->transaction(function () use ($admin, $universe, $enabled, $targets): void {
            $now = $this->clock->now();
            $this->manager->lockCurrent($universe);
            if ($enabled && !$universe->isAccessible($now)) {
                throw new \DomainException('L’univers doit être ouvert pour activer la station.');
            }
            $market = $this->markets->find($universe);
            if (!$market) {
                $market = new ResourceMarket();
                $market->universe = $universe;
                $market->stock = $enabled ? $targets : array_fill_keys(array_keys($targets), 0);
                $this->manager->save($market);
            } else {
                $this->manager->lockCurrent($market);
            }
            // Un brouillon fermé ne reçoit pas de ressources. À la toute première
            // ouverture, et seulement à celle-ci, le stock initial est provisionné.
            if ($enabled && $market->lastOccurrence === '') {
                $market->stock = $targets;
            }
            if ($enabled && !$market->enabled) {
                $market->lastOccurrence = $this->calculator->window($now)['occurrence'];
            }
            $market->enabled = $enabled;
            $market->targets = $targets;

            $log = new AdminAuditLog();
            $log->actor = $admin;
            $log->action = 'market_settings';
            $log->targetType = 'universe';
            $log->targetId = $universe->id;
            $log->details = ['enabled' => $enabled, 'targets' => $targets];
            $log->createdAt = $now->setTimezone(new \DateTimeZone('UTC'));
            $this->manager->save($log);
        });
    }
}
