<?php

namespace App\Service\Balance;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
/** Orchestre les mêmes politiques avec les prix actuels et leur variante temporaire. */
final class ProgressionEconomyStudy
{
    public const BASELINE_REVISION = '419282184c3c5911ca6723b73336a301b493da08';
    public function __construct(private GameCatalog $catalog, private CostDistributionCandidate $candidate, private FundedProgressionSimulation $simulation, private OperationCalculator $operations)
    {
    }

    /** @param list<'basic'|'intermediate'> $candidateTiers Rangs de la seule variante en mémoire. */
    public function universe(bool $candidate = false, array $candidateTiers = CostDistributionCandidate::DEFAULT_TIERS): Universe
    {
        $universe = new Universe();
        $universe->slug = 'progression-economy-x1';
        $universe->name = 'Étude de progression ×1';
        if ($candidate) {
            $universe->gameSettings['catalog_overrides'] = $this->candidate->compare($universe, $candidateTiers)['overrides'];
        }
        return $universe;
    }
    /**
     * @param list<'basic'|'intermediate'> $candidateTiers
     * @return array<string,mixed>
     */
    public function costs(array $candidateTiers = CostDistributionCandidate::DEFAULT_TIERS): array
    {
        $reference = $this->universe();
        $comparison = $this->candidate->compare($reference, $candidateTiers);
        $variant = $this->universe(true, $candidateTiers);
        $currentConfig = $this->catalog->forUniverse($reference);
        $variantConfig = $this->catalog->forUniverse($variant);
        $maxDifference = 0;
        $worst = null;
        $quotes = 0;
        foreach ($currentConfig['catalog'] as $group => $definitions) {
            foreach ($definitions as $slug => $definition) {
                foreach (GameCatalog::TIERS as $tier) {
                    $levels = in_array($group, ['buildings', 'technologies'], true) ? array_unique([1, min(5, $definition['max_level'] ?? 5), min(20, $definition['max_level'] ?? 20)]) : [1];
                    $quantities = in_array($group, ['ships', 'defenses'], true) ? [1, 10, 100] : [1];
                    foreach ($levels as $level) {
                        foreach ($quantities as $quantity) {
                            foreach (['none', 'maximum'] as $bonus) {
                                $technologies = $bonus === 'none' ? [] : array_fill_keys(['nanomaterial_synthesis', 'naval_engineering'], array_fill_keys(GameCatalog::TIERS, 25));
                                $before = $this->operations->calculate($this->catalog->withTier($definition, $tier), $group, $level, $quantity, [], $currentConfig, $technologies);
                                $after = $this->operations->calculate($this->catalog->getTierForUniverse($variant, $group, $slug, $tier), $group, $level, $quantity, [], $variantConfig, $technologies);
                                $difference = CostDistributionCandidate::value($after['costs']) - CostDistributionCandidate::value($before['costs']);
                                $quotes++;
                                if (abs($difference) > $maxDifference) {
                                    $maxDifference = abs($difference);
                                    $worst = [
                                        'group' => $group,
                                        'slug' => $slug,
                                        'tier' => $tier,
                                        'level' => $level,
                                        'quantity' => $quantity,
                                        'bonus' => $bonus,
                                        'difference' => $difference,
                                    ];
                                }
                                if ($before['duration'] !== $after['duration'] || ($before['costs']['electricity'] ?? 0) !== ($after['costs']['electricity'] ?? 0)) {
                                    throw new \LogicException('Le candidat a modifié durée ou taxe.');
                                }
                            }
                        }
                    }
                }
            }
        }
        unset($comparison['overrides']);
        return $comparison + ['effective_quotes_compared' => $quotes, 'maximum_rounded_value_difference' => $maxDifference, 'worst_rounded_quote' => $worst];
    }

    /** @param list<'basic'|'intermediate'> $candidateTiers Rangs de la seule variante en mémoire. */
    public function economy(
        ?callable $progress = null,
        bool $trace = false,
        array $candidateTiers = CostDistributionCandidate::DEFAULT_TIERS,
    ): array
    {
        $cases = [];
        foreach ([false, true] as $variant) {
            $universe = $this->universe($variant, $candidateTiers);
            $name = $variant ? 'candidate' : 'current';
            foreach ([
                'economy_without_civil',
                'economy_with_civil',
                'science_military',
                'frequent_spying',
                'transports',
            ] as $policy) {
                $goals = match ($policy) {
                    'economy_without_civil' => $this->economyGoals(false),
                    'science_military' => $this->mixedGoals(),
                    'frequent_spying' => $this->spyingGoals(),
                    'transports' => $this->transportGoals(),
                    default => $this->economyGoals(true),
                };
                if ($progress !== null) {
                    $progress($name . '/' . $policy);
                }
                $result = $this->simulation->run($universe, $goals, ['trace' => true]);
                if ($policy === 'science_military') {
                    $result['colonization_operations'] = array_values(array_filter($result['operations'], static fn(array $node): bool => ($node['label'] ?? '') === 'colonization' || ($node['label'] ?? '') === 'research_after_colonization'));
                }
                if (!$trace) {
                    unset($result['operations']);
                }
                $cases[$name][$policy] = $result;
            }
            // Sensibilités du kit réellement fourni et de la position ; mêmes objectifs Basiques.
            foreach ([1, 5, 9] as $position) {
                $key = 'new_colony_position_' . $position;
                if ($progress !== null) {
                    $progress($name . '/' . $key);
                }
                $cases[$name][$key] = $this->simulation->run($universe, $this->economyGoals(true, ['basic' => 10]), ['colony' => true, 'position' => $position]);
            }
        }
        return $cases;
    }
    /** Politique : niveaux entrelacés ; priorité centrale, métal, nano, hydrogène, laboratoire et piège. */
    public function economyGoals(bool $civil, array $targets = ['basic' => 20, 'intermediate' => 20, 'superior' => 5]): array
    {
        $goals = [];
        foreach ($targets as $tier => $maximum) {
            for ($level = 1; $level <= $maximum; $level++) {
                foreach ([
                    'power_plant',
                    'mine_metal',
                    'mine_nano',
                    'mine_hydrogen',
                    'lab',
                ] as $slug) {
                    $goals[] = [
                        'group' => 'buildings',
                        'slug' => $slug,
                        'tier' => $tier,
                        'level' => $level,
                        'label' => $slug . '_' . $tier . '_' . $level,
                    ];
                }
                if ($level <= max(1, (int) ceil($maximum / 2))) {
                    $goals[] = ['group' => 'buildings', 'slug' => 'mine_antimatter', 'tier' => $tier, 'level' => $level];
                }
                if ($level <= 2) {
                    $goals[] = [
                        'group' => 'buildings',
                        'slug' => 'shipyard',
                        'tier' => $tier,
                        'level' => $level,
                        'label' => 'shipyard_' . $tier . '_' . $level,
                    ];
                }
                if ($civil && in_array($level, [5, 10, 20], true)) {
                    $increment = $level === 5 ? 5 : ($level === 10 ? 5 : 10);
                    $goals[] = [
                        'group' => 'ships',
                        'slug' => 'solar_satellite',
                        'tier' => $tier,
                        'quantity' => 2 * $increment,
                        'batch_size' => 5,
                        'label' => 'satellites_' . $tier . '_' . $level,
                    ];
                    $goals[] = [
                        'group' => 'ships',
                        'slug' => 'hydrogen_collector',
                        'tier' => $tier,
                        'quantity' => $increment,
                        'batch_size' => 5,
                        'label' => 'collectors_' . $tier . '_' . $level,
                    ];
                }
            }
        }
        return $goals;
    }

    public function mixedGoals(): array
    {
        $goals = $this->economyGoals(true);
        // Insérer explicitement la recherche longue ; celle qui la suit reste dépendante de son achèvement.
        $insert = [
            ['group' => 'technologies', 'slug' => 'colonial_expansion', 'level' => 1, 'label' => 'colonization'],
            [
                'group' => 'technologies',
                'slug' => 'tactical_intelligence',
                'level' => 1,
                'after_label' => 'colonization',
                'label' => 'research_after_colonization',
            ],
            [
                'group' => 'ships',
                'slug' => 'fighter',
                'quantity' => 20,
                'batch_size' => 10,
                'label' => 'first_twenty_fighters',
            ],
        ];
        $offset = 0;
        foreach ($goals as $i => $goal) {
            if (($goal['label'] ?? null) === 'lab_basic_3') {
                $offset = $i + 1;
                break;
            }
        }
        array_splice($goals, $offset, 0, $insert);
        foreach ([['frigate', 5], ['battleship', 2], ['capital', 1]] as [$slug, $quantity]) {
            $goals[] = [
                'group' => 'ships',
                'slug' => $slug,
                'quantity' => $quantity,
                'batch_size' => 1,
                'label' => $slug . '_fleet',
            ];
        }
        $goals[] = ['group' => 'defenses', 'slug' => 'ground_turret_anti_light', 'quantity' => 20, 'batch_size' => 10];
        $goals[] = [
            'group' => 'defenses',
            'slug' => 'ground_turret_anti_escort',
            'tier' => 'intermediate',
            'quantity' => 5,
            'batch_size' => 1,
        ];
        return $goals;
    }

    public function spyingGoals(): array
    {
        $goals = $this->economyGoals(true);
        $previous = null;
        for ($i = 0; $i < 120; $i++) {
            $order = ['group' => 'ships', 'slug' => 'probe', 'quantity' => 1, 'label' => 'probe_' . $i];
            if ($previous !== null) {
                $order['after_label'] = $previous;
            }
            $goals[] = $order;
            $mission = [
                'group' => 'missions',
                'mission' => 'spy',
                'composition' => ['probe' => ['basic' => 1]],
                'normal_distance' => 50,
                'hyper_distance' => 100,
                'speed_ratio' => 1,
                'after_label' => 'probe_' . $i,
                'label' => 'spy_' . $i,
            ];
            if ($previous !== null) {
                $mission['spacing_after_label'] = $previous;
                $mission['interval_seconds'] = 21600;
            }
            $goals[] = $mission;
            $previous = 'spy_' . $i;
        }
        return $goals;
    }

    public function transportGoals(): array
    {
        $goals = $this->economyGoals(true);
        $goals[] = ['group' => 'ships', 'slug' => 'transport', 'quantity' => 2, 'label' => 'cargo_fleet'];
        $previous = null;
        for ($i = 0; $i < 60; $i++) {
            $mission = [
                'group' => 'missions',
                'mission' => 'transport',
                'composition' => ['transport' => ['basic' => 2]],
                'normal_distance' => 100,
                'hyper_distance' => 100,
                'speed_ratio' => 0.5,
                'cargo' => ['metal' => 1000, 'nano' => 500],
                'after_label' => $previous ?? 'cargo_fleet',
                'label' => 'transport_' . $i,
            ];
            if ($previous !== null) {
                $mission['spacing_after_label'] = $previous;
                $mission['interval_seconds'] = 43200;
            }
            $goals[] = $mission;
            $previous = 'transport_' . $i;
        }
        return $goals;
    }
}
