<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Service\Catalog\GameCatalog;

/**
 * Proposition d’édition approuvée. Ce barème n’est ni créé ni activé
 * automatiquement. L’administration doit figer les choix de l’édition.
 */
final readonly class AdventSeasonPlan
{
    public const MULTIPLIERS = ['beginner' => 1, 'intermediate' => 6, 'veteran' => 24];
    public const FIRST_WALLET = ['metal' => 2000, 'nano' => 1000];
    public const FIRST_WALLET_SECONDS = 48 * 3600;

    private const MILESTONE_BUDGET_METAL = [6 => 6000, 12 => 7500, 18 => 15200, 24 => 16000];
    private const BRACKET_TIERS = ['B' => 'basic', 'I' => 'intermediate', 'S' => 'superior'];

    /** Les triples compacts détaillent explicitement coque, rang et nombre. */
    private const PACKS = [
        6 => [
            'beginner' => [
                [['probe', 'B', 12]],
            ],
            'intermediate' => [
                [['transport', 'I', 2], ['probe', 'I', 7], ['probe', 'B', 3]],
                [['colony_ship', 'B', 1], ['transport', 'I', 1], ['probe', 'B', 1]],
                [['transport', 'I', 1], ['probe', 'I', 31], ['probe', 'B', 3]],
            ],
            'veteran' => [
                [['transport', 'S', 7], ['probe', 'S', 16]],
                [['colony_ship', 'S', 2], ['transport', 'S', 4], ['probe', 'S', 5], ['probe', 'B', 5]],
                [['transport', 'S', 5], ['probe', 'S', 60], ['probe', 'B', 6]],
            ],
        ],
        18 => [
            'beginner' => [
                [['fighter', 'B', 5]],
                [['interceptor', 'B', 4]],
                [['stealth_fighter', 'B', 1], ['fighter', 'B', 1]],
            ],
            'intermediate' => [
                [['fighter', 'I', 24]],
                [['interceptor', 'I', 19], ['fighter', 'I', 1]],
                [['stealth_fighter', 'I', 4], ['fighter', 'I', 8]],
            ],
            'veteran' => [
                [['fighter', 'S', 71], ['interceptor', 'S', 5], ['fighter', 'B', 1]],
                [['interceptor', 'S', 58], ['fighter', 'S', 6], ['fighter', 'B', 1]],
                [['stealth_fighter', 'S', 17], ['fighter', 'S', 8], ['fighter', 'B', 1]],
            ],
        ],
        24 => [
            'intermediate' => [
                [['cruiser', 'B', 1], ['frigate', 'B', 1], ['bomber', 'B', 1]],
                [['destroyer', 'I', 1], ['frigate', 'I', 1], ['heavy_fighter', 'B', 2]],
                [['destroyer', 'B', 2], ['bomber', 'B', 1]],
            ],
            'veteran' => [
                [['battleship', 'S', 2], ['heavy_fighter', 'B', 2], ['frigate', 'B', 1]],
                [['cruiser', 'S', 3], ['destroyer', 'S', 1], ['heavy_fighter', 'B', 1], ['frigate', 'B', 1]],
                [['capital', 'S', 1], ['cruiser', 'B', 1], ['heavy_fighter', 'B', 3]],
            ],
        ],
    ];

    public function __construct(private GameCatalog $catalog) {}

    /**
     * @return array<int, array{options: array<string, list<array<string, mixed>>>, cosmetics: list<string>}>
     */
    public function doors(): array
    {
        $doors = [];
        for ($door = 1; $door <= AdventCalendar::DOORS; ++$door) {
            $options = [];
            foreach (self::MULTIPLIERS as $bracket => $multiplier) {
                $options[$bracket] = $this->choices($door, $bracket, $multiplier);
            }
            $doors[$door] = ['options' => $options, 'cosmetics' => $door === 24 ? [
                'guardian_of_stars', 'polar_night',
            ] : []];
        }

        return $doors;
    }

    /** @return list<array<string, mixed>> */
    private function choices(int $door, string $bracket, int $multiplier): array
    {
        $budget = (self::MILESTONE_BUDGET_METAL[$door] ?? 0) * $multiplier;
        $packs = self::PACKS[$door][$bracket] ?? null;
        if ($packs !== null) {
            $result = array_map(fn (array $pack): array => $this->pack($pack, $budget), $packs);
            return $door === 6 && $bracket === 'beginner'
                ? [...$result, $this->voucher('transport', $budget), $this->voucher('colony_ship', $budget)]
                : $result;
        }
        if ($door === 12) {
            return [['kind' => 'research_discount', 'percent' => 50, 'budget_metal' => $budget]];
        }
        if ($door === 24) {
            return array_map(fn (string $slug): array => $this->voucher($slug, $budget), ['cruiser', 'battleship', 'capital']);
        }

        $metal = match (true) {
            $door <= 5 => 600,
            $door <= 11 => 750,
            $door <= 17 => 1520,
            default => 1600,
        } * $multiplier;

        return [['kind' => 'resources', 'resources' => ['metal' => $metal, 'nano' => intdiv($metal, 2)]]];
    }

    /**
     * @param list<array{string, string, int}> $pack
     * @return array{kind: string, units: list<array{slug:string,tier:string,quantity:int}>, budget_metal:int, value_twice_metal:int}
     */
    private function pack(array $pack, int $budget): array
    {
        $units = [];
        $rawValue = 0;
        foreach ($pack as [$slug, $letter, $quantity]) {
            $tier = self::BRACKET_TIERS[$letter];
            // Le catalogue de référence, sans surcharge d’univers ni remise
            // technologique. Travailler en demi-métal évite les flottants.
            $definition = $this->catalog->withTier($this->catalog->get('ships', $slug), $tier);
            $rawValue += self::twiceMetal($this->catalog->costs($definition, quantity: $quantity, operationGroup: 'ships'));
            $units[] = ['slug' => $slug, 'tier' => $tier, 'quantity' => $quantity];
        }

        return ['kind' => 'ship_pack', 'units' => $units, 'budget_metal' => $budget, 'value_twice_metal' => $rawValue];
    }

    /** @return array{kind:string,slug:string,tier:string,quantity:int,budget_metal:int} */
    private function voucher(string $slug, int $budget): array
    {
        return ['kind' => 'ship_voucher', 'slug' => $slug, 'tier' => 'basic', 'quantity' => 1, 'budget_metal' => $budget];
    }

    /** @param array<string,int> $resources */
    public static function twiceMetal(array $resources): int
    {
        return 2 * ($resources['metal'] ?? 0) + 4 * ($resources['nano'] ?? 0)
            + 8 * ($resources['antimatter'] ?? 0) + ($resources['hydrogen'] ?? 0);
    }
}
