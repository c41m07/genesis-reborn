<?php

declare(strict_types=1);

namespace App\Service\Market;

use App\Entity\ResourceMarket;
use App\Repository\ResourceMarketRepository;
use Psr\Clock\ClockInterface;

final readonly class MarketPresentation
{
    public const LABELS = ['metal' => 'Métal', 'nano' => 'Nano', 'hydrogen' => 'Hydrogène', 'antimatter' => 'Antimatière'];

    public function __construct(private ResourceMarketRepository $repo, private ClockInterface $clock)
    {
    }

    public function rows(ResourceMarket $market): array
    {
        $reserved = $this->repo->reserved($market->universe)['outgoing'];
        $past = $this->repo->pressureSince($market->universe, $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify('-24 hours'));
        $scarcity = MarketPricing::scarcity($market->targets, $market->stock);
        $rows = [];
        foreach (self::LABELS as $resource => $label) {
            $demand = $scarcity[$resource];
            $target = $market->targets[$resource] ?? 0;
            $index = MarketPricing::index($resource, $demand, $target);
            $previous = MarketPricing::index($resource, $demand - ($past[$resource] ?? 0), $target);
            $rows[$resource] = [
                'label' => $label, 'index' => $index / MarketPricing::SCALE,
                'bid' => MarketPricing::creditPrice($resource, 1, $demand, $target, false),
                'ask' => MarketPricing::creditPrice($resource, 1, $demand, $target, true),
                'trend' => 100 * ($index - $previous) / $previous,
                'stock' => $market->stock[$resource] ?? 0, 'target' => $target,
                'available' => ($market->stock[$resource] ?? 0) - ($reserved[$resource] ?? 0),
            ];
        }
        return $rows;
    }
}
