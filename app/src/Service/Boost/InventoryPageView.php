<?php

namespace App\Service\Boost;

use App\Entity\{Planet, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\VacationCalendar;
use App\Service\Queue\LiveQueueTiming;
use Psr\Clock\ClockInterface;

/** Prépare chaque destination de l’inventaire ; la consommation reste dans les services métier. */
final class InventoryPageView
{
    public function __construct(
        private GameRepository $repo,
        private BoostGrantService $grants,
        private \App\Service\Advent\AdventRewards $adventRewards,
        private GameCatalog $catalog,
        private VacationCalendar $vacations,
        private ClockInterface $clock,
    ) {
    }

    /** @return array<string,mixed> */
    public function prepare(PlayerUniverse $membership, string $page, int $number = 1): array
    {
        $counts = ['available' => 0, 'pending' => 0, 'boosts' => 0, 'boxes' => 0];
        foreach ($this->repo->inventoryCounts($membership) as $row) {
            $quantity = (int) $row['quantity'];
            $counts[$row['status']] += $quantity;
            if ($row['status'] === 'available' && $row['kind'] === 'boost') $counts['boosts'] += $quantity;
            if ($row['status'] === 'available' && $row['kind'] === 'resource_box') $counts['boxes'] += $quantity;
        }
        $data = [
            'membership' => $membership, 'inventory_page' => $page, 'inventory_counts' => $counts,
            'boost_count' => $counts['boosts'], 'pending_boost_count' => $counts['pending'],
            'inventory_capacity' => $this->grants->inventoryCapacity(),
        ];
        if ($page === 'pending') {
            $pages = max(1, (int) ceil($counts['pending'] / 25));
            $number = max(1, min($pages, $number));
            $reservations = $this->adventRewards->pending($membership);
            return $data + [
                'advent_reservations' => $reservations,
                'advent_colonies' => $reservations ? $this->repo->planets($membership->universe, $membership) : [],
                'advent_ships' => $reservations ? $this->catalog->all('ships') : [],
                'advent_resources' => \App\Service\Advent\AdventGifts::RESOURCES,
                'pending_items' => $this->repo->pendingInventoryItems($membership, $number), 'pending_page' => $number, 'pending_pages' => $pages];
        }
        $data['vacation'] = $this->vacations->currentPeriod($membership);
        if ($page === 'boxes') {
            $groups = [];
            foreach ($this->repo->availableResourceBoxes($membership) as $item) {
                // Une carte représente un contenu identique et ouvre toujours son plus ancien objet disponible.
                $key = hash('sha256', json_encode($item->payload));
                $groups[$key] ??= ['item' => $item, 'quantity' => 0];
                ++$groups[$key]['quantity'];
            }
            return $data + [
                'resource_boxes' => array_values($groups), 'box_resources' => $this->catalog->rules()['resources'],
                'available_colonies' => $this->repo->all(Planet::class, ['owner' => $membership], ['id' => 'ASC']),
            ];
        }
        $ratio = $this->grants->durationReductionRatio();
        $data += ['duration_reduction_percent' => $ratio * 100, 'active_queues' => []];
        if ($counts['boosts'] > 0 && !$data['vacation']) {
            $definitions = array_merge($this->catalog->all('buildings'), $this->catalog->all('technologies'), $this->catalog->all('ships'), $this->catalog->all('defenses'));
            // Les opérations gardent leur périmètre de tout l’empire, quelle que soit la colonie active.
            $now = $this->clock->now();
            foreach ($this->repo->activeQueueItemsFor($membership) as $item) {
                if ($item->startedAt !== null && $item->finishAt() > $now) {
                    $definition = $definitions[$item->itemSlug] ?? null;
                    // L’aperçu emploie le même arrondi que le boost réellement consommé.
                    // Il est daté de l’ouverture ; le service réévalue le temps au clic.
                    $timestamp = $now->getTimestamp();
                    $after = LiveQueueTiming::boostedRemainingSeconds($item, $timestamp, 1 / (1 - $ratio));
                    $data['active_queues'][] = [
                        'item' => $item,
                        'label' => $definition ? $this->catalog->withTier($definition, $item->tier)['label'] : $item->itemSlug,
                        'seconds_before' => max(0, $item->finishAt()->getTimestamp() - $timestamp),
                        'seconds_after' => $after,
                    ];
                }
            }
        }
        return $data;
    }
}
