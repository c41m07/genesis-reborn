<?php

namespace App\Service\Balance;

use App\Entity\{Galaxy, Planet, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{CatalogueProductionContext, EconomicUnitCapacity, ResourceService};
use App\Service\Combat\{CombatCapacityExceeded, CombatSimulator, CombatUnitFactory};
use App\Service\Fleet\MovementCalculator;
use App\Service\Queue\OperationCalculator;
/** Expériences militaires sur un état annoncé, sans mission réelle, butin ni modification d'un compte. */
final class MilitaryEconomyStudy
{
    public function __construct(private GameCatalog $catalog, private OperationCalculator $operations, private ResourceService $resources, private EconomicUnitCapacity $economicUnits, private CombatUnitFactory $units, private CombatSimulator $combats, private MovementCalculator $movement, private FundedProgressionSimulation $funding, private ProgressionEconomyStudy $progression)
    {
    }

    /**
     * @param list<'basic'|'intermediate'|'superior'> $candidateTiers Rangs de la seule variante en mémoire.
     * @param array<string,array<string,int|float>> $profiles Pourcentages explicites facultatifs par rang.
     */
    public function run(
        ?array $checkpoint = null,
        ?callable $progress = null,
        array $candidateTiers = CostDistributionCandidate::DEFAULT_TIERS,
        array $profiles = [],
    ): array
    {
        $reference = $this->progression->universe();
        $checkpoint ??= $this->funding->run($reference, $this->progression->mixedGoals())['checkpoint'];
        $result = [
            'reference_checkpoint' => $checkpoint,
            'hypotheses' => [
                'Infrastructures et recherches issues du parcours mixte actuel, identiques pour les deux prix.',
                'Les formations et reconstructions repartent de cet état industriel, pas d’un empire neuf.',
                'Les expériences de combat donnent des forces ; leur prix et leur masse sont annoncés, sans probabilité de victoire extrapolée.',
                'Les tirs sont simulés par le moteur actuel avec les graines 7, 19, 42, 73 et 101.',
                'Les pertes sont appliquées à la clôture ; pas de pillage dans les expériences de reconstruction explicitement imposées.',
            ],
        ];
        $result['navigation'] = $this->navigation($reference, $checkpoint);
        $result['collector_transfers'] = $this->collectorTransfers($reference, $checkpoint);
        $result['marginal_investments'] = $this->marginalInvestments($reference, $checkpoint);
        $result['combat'] = [];
        foreach ([0, 20, 200, 2000] as $civil) {
            if ($progress !== null) {
                $progress('combat/civil_' . $civil);
            }
            $result['combat']['civil_' . $civil] = $this->combatScenario($reference, $checkpoint, $civil, 'basic', 'basic');
        }
        foreach ([['basic', 'intermediate'], ['basic', 'superior'], ['intermediate', 'basic'], ['superior', 'basic']] as [$attack, $defend]) {
            $name = 'rank_' . $attack . '_against_' . $defend;
            if ($progress !== null) {
                $progress('combat/' . $name);
            }
            $result['combat'][$name] = $this->combatScenario($reference, $checkpoint, 200, $attack, $defend);
        }
        $result['signature_first_targets'] = $this->signatureSelection($reference, $checkpoint);
        $result['engine_volume_check'] = $this->volumeLimit($reference, $checkpoint);
        $result['equal_budget'] = $this->equalBudget($reference, $checkpoint);
        foreach ([false, true] as $candidate) {
            $universe = $this->progression->universe($candidate, $candidateTiers, $profiles);
            $variant = $candidate ? 'candidate' : 'current';
            $initial = $checkpoint;
            $initial['units'] = array_intersect_key($checkpoint['units'], ['hydrogen_collector' => true, 'solar_satellite' => true]);
            $initial['defenses'] = [];
            foreach ([
                'small' => [['fighter', 20], ['transport', 2]],
                'large' => [['fighter', 500], ['frigate', 50], ['battleship', 10], ['capital', 2]],
            ] as $name => $composition) {
                foreach (GameCatalog::TIERS as $tier) {
                    $key = 'formation_' . $name . '_' . $tier;
                    if ($progress !== null) {
                        $progress($variant . '/' . $key);
                    }
                    $goals = [];
                    $fleet = [];
                    foreach ($composition as [$slug, $quantity]) {
                        $goals[] = [
                            'group' => 'ships',
                            'slug' => $slug,
                            'tier' => $tier,
                            'quantity' => $quantity,
                            'batch_size' => min(10, $quantity),
                        ];
                        $fleet[$slug][$tier] = $quantity;
                    }
                    $formed = $this->funding->run($universe, $goals, ['initial' => $initial]);
                    $formed['navigation_after_formation'] = $this->movement->quoteDistances(100, 1000, false, $this->units->ships($universe, $fleet, $formed['checkpoint']['technologies']), 1, 1, $this->catalog->rules()['fuel_ratio_policy']);
                    $formed['civil_production_available'] = true;
                    $result['funded'][$variant][$key] = $formed;
                    if ($name === 'large' && in_array($tier, ['basic', 'superior'], true)) {
                        foreach ([true, false] as $civilAvailable) {
                            $sortie = $this->fastWingSorties($universe, $formed['checkpoint'], $tier, $civilAvailable);
                            $result['funded'][$variant]['fast_wing_sorties_' . $tier . '_' . ($civilAvailable ? 'with_civil' : 'without_civil')] = $sortie;
                        }
                    }
                    if ($name === 'large' && $tier === 'basic') {
                        $without = $initial;
                        $without['units'] = [];
                        $withoutResult = $this->funding->run($universe, $goals, ['initial' => $without]);
                        $withoutResult['civil_production_available'] = false;
                        $result['funded'][$variant][$key . '_without_civil'] = $withoutResult;
                    }
                }
            }
            if ($progress !== null) {
                $progress($variant . '/civil_reconstruction');
            }
            $result['funded'][$variant]['civil_reconstruction'] = $this->civilReconstruction($universe, $checkpoint);
            $result['funded'][$variant]['civil_reconstruction_empty_stocks'] = $this->civilReconstruction($universe, $checkpoint, true);
            $result['funded'][$variant]['severe_deficit_wait'] = $this->severeRecovery($universe, $checkpoint, false);
            $result['funded'][$variant]['severe_deficit_power_first'] = $this->severeRecovery($universe, $checkpoint, true);
            if ($progress !== null) {
                $progress($variant . '/defense_reconstruction');
            }
            $result['funded'][$variant]['defense_reconstruction'] = $this->defenseReconstruction($universe, $checkpoint, false);
            $result['funded'][$variant]['defense_reconstruction_repair5'] = $this->defenseReconstruction($universe, $checkpoint, true);
            // Même combat et mêmes pertes physiques pour les deux grilles ; seul le financement est rejoué.
            $experiment = $result['combat']['civil_2000'];
            $battle = $experiment['first_seed_losses'];
            if ($experiment['results'][0]['outcome'] !== 'defender_wins') {
                throw new \LogicException('Cette reconstruction suppose une défense victorieuse sans pillage.');
            }
            $closing = $experiment['results'][0]['rounds'] * 60;
            foreach (['attacker', 'defender'] as $side) {
                $state = $checkpoint;
                $state['units'] = $experiment[$side];
                $state['defenses'] = $side === 'defender' ? $experiment['static_defenses'] : [];
                $goals = [];
                $automatic = [];
                // La garnison produit encore pendant tous les rounds ; les pertes deviennent effectives à la clôture.
                $before = $this->projection($universe, $state['buildings'], $side === 'defender' ? $state['units'] : [], $state['stocks'], $closing);
                $state['stocks'] = $before['stocks'];
                foreach ($battle[$side] as $group => $types) {
                    foreach ($types as $slug => $tiers) {
                        foreach ($tiers as $tier => $quantity) {
                            $restored = $group === 'defenses' ? (int) floor($quantity * $this->repairPercent($state['technologies']) / 100) : 0;
                            $state[$group === 'ships' ? 'units' : 'defenses'][$slug][$tier] = max(0, ($state[$group === 'ships' ? 'units' : 'defenses'][$slug][$tier] ?? 0) - $quantity + $restored);
                            if ($restored > 0) {
                                $automatic[$slug][$tier] = $restored;
                            }
                            if ($quantity > $restored) {
                                $goals[] = [
                                    'group' => $group,
                                    'slug' => $slug,
                                    'tier' => $tier,
                                    'quantity' => $quantity - $restored,
                                    'batch_size' => min(10, $quantity - $restored),
                                ];
                            }
                        }
                    }
                }
                $funded = $this->funding->run($universe, $goals, ['initial' => $state]);
                $funded['combat_closing_seconds'] = $closing;
                $funded['automatically_restored'] = $automatic;
                $funded['loss_hypothesis'] = 'Pertes du premier combat de masse, défense victorieuse, sans butin ; retour des survivants non chronométré.';
                $result['funded'][$variant]['combat_losses_' . $side] = $funded;
            }
        }
        return $result;
    }

    public function navigation(Universe $universe, array $checkpoint): array
    {
        $fleets = [
            'small' => ['fighter' => ['basic' => 20], 'transport' => ['basic' => 2]],
            'fast_wing' => ['fighter' => ['basic' => 500]],
            'large' => ['fighter' => ['basic' => 500], 'frigate' => ['basic' => 50], 'battleship' => ['basic' => 10], 'capital' => ['basic' => 2]],
        ];
        $ranked = [];
        foreach ($fleets as $name => $composition) {
            foreach (GameCatalog::TIERS as $tier) {
                $mapped = [];
                foreach ($composition as $slug => $tiers) {
                    $mapped[$slug][$tier] = array_sum($tiers);
                }
                $ranked[] = ['name' => $name, 'tier' => $tier, 'composition' => $mapped];
            }
        }
        $legacy = json_decode(file_get_contents(dirname(__DIR__, 3) . '/config/legacy/progression-operations-2026-11-15.json'), true, 512, JSON_THROW_ON_ERROR);
        $rows = [];
        $config = $this->catalog->forUniverse($universe);
        foreach ($ranked as $item) {
            foreach ([1, 0.5] as $ratio) {
                foreach ([0, 2] as $orbit) {
                    $name = $item['name'];
                    $composition = $item['composition'];
                    $ships = $this->units->ships($universe, $composition, $checkpoint['technologies']);
                    $oldShips = [];
                    foreach ($ships as $ship) {
                        $oldShips[] = array_replace($ship, $legacy['fuel_profiles'][$ship['slug']]);
                    }
                    $current = $this->movement->quoteDistances(100, 1000, false, $ships, $ratio, $config['multipliers']['travel_time'], $this->catalog->rules()['fuel_ratio_policy'], $orbit);
                    $old = $this->movement->quoteDistances(100, 1000, false, $oldShips, $ratio, $config['multipliers']['travel_time'], $this->catalog->rules()['fuel_ratio_policy'], $orbit);
                    $quotes = ['current' => [], 'legacy_durations' => []];
                    foreach ($composition as $slug => $tiers) {
                        foreach ($tiers as $tier => $quantity) {
                            $d = $this->catalog->getTierForUniverse($universe, 'ships', $slug, $tier);
                            $oldDefinition = $d;
                            $oldDefinition['build_time_base'] = $legacy['durations']['ships'][$slug];
                            $quotes['current'][] = $this->operations->calculate($d, 'ships', 1, $quantity, $checkpoint['buildings'], $config, $checkpoint['technologies']);
                            $quotes['legacy_durations'][] = $this->operations->calculate($oldDefinition, 'ships', 1, $quantity, $checkpoint['buildings'], $config, $checkpoint['technologies']);
                        }
                    }
                    $rows[] = [
                        'fleet' => $name,
                        'tier' => $item['tier'],
                        'composition' => $composition,
                        'speed_ratio' => $ratio,
                        'orbit_hours' => $orbit,
                        'current' => $current,
                        'current_fuel_fits_capacity' => $current['total_fuel'] <= $current['capacity'],
                        'legacy_fuel' => $old['total_fuel'],
                        'legacy_fuel_fits_capacity' => $old['total_fuel'] <= $old['capacity'],
                        'round_trip_seconds' => 2 * $current['seconds'] + $orbit * 3600,
                        'maximum_cycles_per_day' => 86400 / (2 * $current['seconds'] + $orbit * 3600),
                        'formation_seconds' => array_sum(array_column($quotes['current'], 'duration')),
                        'legacy_formation_seconds' => array_sum(array_column($quotes['legacy_durations'], 'duration')),
                        'formation_electricity' => array_sum(array_map(static fn(array $q): int => $q['costs']['electricity'], $quotes['current'])),
                        'legacy_formation_electricity' => array_sum(array_map(static fn(array $q): int => $q['costs']['electricity'], $quotes['legacy_durations'])),
                    ];
                }
            }
        }
        return $rows;
    }

    private function fastWingSorties(Universe $universe, array $checkpoint, string $tier, bool $civilAvailable): array
    {
        if (!$civilAvailable) {
            $checkpoint['units']['hydrogen_collector'] = $checkpoint['units']['solar_satellite'] = [];
        }
        $goals = [];
        $previous = null;
        for ($i = 0; $i < 12; $i++) {
            $goal = [
                'group' => 'missions',
                'mission' => 'orbit',
                'composition' => ['fighter' => [$tier => 500]],
                'normal_distance' => 100,
                'hyper_distance' => 1000,
                'speed_ratio' => 1,
                'orbit_hours' => 2,
                'label' => 'sortie_' . $i,
            ];
            if ($previous !== null) {
                $goal['after_label'] = $previous;
            }
            $goals[] = $goal;
            $previous = 'sortie_' . $i;
        }
        $result = $this->funding->run($universe, $goals, ['initial' => $checkpoint]);
        $result['civil_production_available'] = $civilAvailable;
        $result['mission_hypothesis'] = 'Douze sorties de 500 chasseurs, distance normale 100 + hyperespace 1 000, vitesse 100 %, deux heures d’orbite autorisée, sans nouveau combat ; flotte réutilisée après chaque retour.';
        return $result;
    }

    public function volumeLimit(Universe $universe, array $checkpoint): array
    {
        try {
            $probe = $this->combatScenario($universe, $checkpoint, 5000, 'basic', 'basic', [7]);
            return [
                'requested_units' => 5060,
                'status' => 'accepted',
                'resolved_units' => $probe['results'][0]['resolved_units'],
            ];
        } catch (CombatCapacityExceeded $exception) {
            // N’annoncer un refus que si le moteur courant l’a effectivement renvoyé.
            return ['requested_units' => 5060, 'status' => 'refused', 'reason' => $exception->getMessage()];
        }
    }

    private function projection(Universe $universe, array $buildings, array $units, array $stocks, int $elapsed, int $position = 5, array $incoming = []): array
    {
        $g = new Galaxy();
        $g->universe = $universe;
        $s = new SolarSystem();
        $s->galaxy = $g;
        $owner = new PlayerUniverse();
        $owner->universe = $universe;
        $planet = new Planet();
        $planet->solarSystem = $s;
        $planet->owner = $owner;
        $planet->position = $position;
        return $this->resources->project($planet, $buildings, $stocks, $elapsed, new CatalogueProductionContext($planet, false, 1, ['present' => $units, 'incoming' => $incoming, 'construction' => []]));
    }

    public function collectorTransfers(Universe $universe, array $checkpoint): array
    {
        $rows = [];
        $quantity = 5;
        $source = $checkpoint['units'];
        $source['hydrogen_collector']['basic'] -= $quantity;
        $composition = ['hydrogen_collector' => ['basic' => $quantity]];
        $ships = $this->units->ships($universe, $composition, $checkpoint['technologies']);
        $buildings = [
            'mine_hydrogen' => ['basic' => 5],
            'power_plant' => ['basic' => 5],
            'mine_metal' => ['basic' => 2],
            'mine_nano' => ['basic' => 2],
        ];
        foreach ([1, 5, 9] as $position) {
            $quote = $this->movement->quote([
                1,
                1,
                0,
                0,
                5,
            ], [
                2,
                1,
                1,
                0,
                $position,
            ], $ships, 1, 1, $this->catalog->rules()['fuel_ratio_policy']);
            if ($quote['total_fuel'] > $quote['capacity'] || $quote['total_fuel'] > $checkpoint['stocks']['hydrogen']) {
                throw new \LogicException('Transfert expérimental non finançable.');
            }
            $sourceStock = $checkpoint['stocks'];
            $sourceStock['hydrogen'] -= $quote['total_fuel'];
            $before = $this->projection($universe, $checkpoint['buildings'], $checkpoint['units'], $checkpoint['stocks'], 0);
            $flight = $this->projection($universe, $checkpoint['buildings'], $source, $sourceStock, $quote['seconds']);
            $destination = $this->projection($universe, $buildings, [], $this->catalog->rules()['colony_initial_stock'], $quote['seconds'], $position, $composition);
            $arrival = $this->projection($universe, $buildings, $composition, $destination['stocks'], 0, $position);
            $rows[] = [
                'destination_position' => $position,
                'quantity' => $quantity,
                'navigation' => $quote,
                'source_civil_hydrogen_per_hour_before' => 3600 * ($before['sources']['hydrogen']['hydrogen_collector'] ?? 0),
                'source_civil_hydrogen_per_hour_in_flight' => 3600 * ($flight['sources']['hydrogen']['hydrogen_collector'] ?? 0),
                'destination_civil_hydrogen_in_flight' => $destination['sources']['hydrogen']['hydrogen_collector'] ?? 0,
                'destination_civil_hydrogen_per_hour_after' => 3600 * ($arrival['sources']['hydrogen']['hydrogen_collector'] ?? 0),
                'fuel_kept_onboard_at_destination' => $quote['total_fuel'] - $quote['one_way_fuel'],
                'source_end_stocks' => $flight['stocks'],
                'destination_end_stocks' => $arrival['stocks'],
            ];
        }
        return $rows;
    }

    private function forceComposition(array $checkpoint, int $civil, string $tier): array
    {
        // Les satellites restent dans les places données ; les récolteurs excédentaires sont légalement conservés inactifs.
        $solar = min((int) floor($civil / 2), 2 * ($checkpoint['buildings']['power_plant'][$tier] ?? 0));
        return ['fighter' => [$tier => 10], 'hydrogen_collector' => [$tier => $civil - $solar], 'solar_satellite' => [$tier => $solar]];
    }

    public function combatScenario(Universe $universe, array $checkpoint, int $civil, string $attackTier, string $defendTier, array $seeds = [
        7,
        19,
        42,
        73,
        101,
    ], ?array $defenderComposition = null): array
    {
        if ($civil < 0 || $seeds === []) {
            throw new \InvalidArgumentException('Expérience de combat invalide.');
        }
        $attacker = ['fighter' => [$attackTier => 40]];
        $defender = $defenderComposition ?? $this->forceComposition($checkpoint, $civil, $defendTier);
        $rows = [];
        $firstLosses = null;
        $solverSeconds = 0.0;
        $started = hrtime(true);
        $memory = memory_get_usage(true);
        memory_reset_peak_usage();
        foreach ($seeds as $seed) {
            $participants = [
                [
                    'id' => 'A',
                    'role' => 'attacker',
                    'camp' => 'attackers',
                    'arrival_round' => 1,
                    'units' => $this->units->ships($universe, $attacker, $checkpoint['technologies']),
                ],
                [
                    'id' => 'D',
                    'role' => 'defender',
                    'camp' => 'defenders',
                    'arrival_round' => 1,
                    'units' => $this->units->ships($universe, $defender, $checkpoint['technologies']),
                ],
            ];
            $defense = $this->units->defense($universe, 'ground_turret_anti_light', $defendTier, $checkpoint['technologies'], $checkpoint['buildings']['fortress'] ?? []) + ['id' => 1, 'camp' => 'defenders', 'quantity' => 10];
            $solverStarted = hrtime(true);
            $result = $this->combats->simulate($participants, [$defense], $this->catalog->all('combat_modifiers')['modifiers'], (int) $this->catalog->rules()['max_combat_rounds'], $seed);
            $solverSeconds += (hrtime(true) - $solverStarted) / 1000000000.0;
            $civilTargets = $civilShots = $shots = 0;
            $targets = ['hydrogen_collector' => 0, 'solar_satellite' => 0];
            foreach ($result['rounds'] as $round) {
                if (isset($round['groups'])) {
                    // Le moteur compact compte chaque tir reçu comme effectué, sans inventer de détail unitaire.
                    foreach ($round['groups'] as $unit) {
                        $shots += $unit['shots'];
                        $slug = $unit['slug'];
                        if (array_key_exists($slug, $targets)) {
                            $targets[$slug] += $unit['targeted'];
                            $civilTargets += $unit['targeted'];
                            $civilShots += $unit['shots'];
                        }
                    }
                    continue;
                }
                foreach ($round['shots'] as $shot) {
                    $shots++;
                    foreach (array_keys($targets) as $slug) {
                        if (str_contains($shot['target'], ':' . $slug . ':')) {
                            $civilTargets++;
                            $targets[$slug]++;
                        }
                        if (str_contains($shot['attacker'], ':' . $slug . ':')) {
                            $civilShots++;
                        }
                    }
                }
            }
            $losses = $this->losses($result['losses']);
            $firstLosses ??= $losses;
            $rows[] = [
                'seed' => $seed,
                'outcome' => $result['outcome'],
                'rounds' => $result['rounds_played'],
                'shots' => $shots,
                'shots_targeting_civil' => $civilTargets,
                'civil_shots' => $civilShots,
                'civil_targets_by_family' => $targets,
                'losses' => $losses,
                'resolved_units' => array_sum($result['losses']) + array_sum(array_map(
                    static fn (array $survivor): int => $survivor['quantity'] ?? 1,
                    $result['survivors'],
                )),
            ];
            unset($result);
        }
        $civilUnits = $defender;
        $civilUnits['fighter'] = [];
        $civilState = $this->civilState($universe, $checkpoint['buildings'], $civilUnits);
        return [
            'attacker_tier' => $attackTier,
            'defender_tier' => $defendTier,
            'attacker' => $attacker,
            'defender' => $defender,
            'static_defenses' => ['ground_turret_anti_light' => [$defendTier => 10]],
            'civil_state' => $civilState,
            'attacker_quote' => $this->compositionQuote($universe, $checkpoint, $attacker),
            'defender_quote' => $this->compositionQuote($universe, $checkpoint, $defender, ['ground_turret_anti_light' => [$defendTier => 10]]),
            'seeds' => $seeds,
            'results' => $rows,
            'first_seed_losses' => $firstLosses,
            'measurement' => [
                'solver_seconds_total' => $solverSeconds,
                'case_seconds' => (hrtime(true) - $started) / 1000000000.0,
                'peak_memory_increment_bytes' => max(0, memory_get_peak_usage(true) - $memory),
                'php' => PHP_VERSION,
            ],
        ];
    }

    private function civilState(Universe $universe, array $buildings, array $units): array
    {
        $g = new Galaxy();
        $g->universe = $universe;
        $s = new SolarSystem();
        $s->galaxy = $g;
        $p = new Planet();
        $p->solarSystem = $s;
        return $this->economicUnits->state($p, $buildings, ['present' => $units, 'incoming' => [], 'construction' => []]);
    }

    private function losses(array $losses): array
    {
        $result = ['attacker' => ['ships' => [], 'defenses' => []], 'defender' => ['ships' => [], 'defenses' => []]];
        foreach ($losses as $key => $quantity) {
            $parts = explode(':', $key);
            $side = $parts[0] === 'A' ? 'attacker' : 'defender';
            $group = $parts[0] === 'planet' ? 'defenses' : 'ships';
            $offset = $group === 'defenses' ? 2 : 1;
            $slug = $parts[$offset];
            $tier = $parts[$offset + 1] ?? 'basic';
            $result[$side][$group][$slug][$tier] = $quantity;
        }
        return $result;
    }

    public function compositionQuote(Universe $universe, array $checkpoint, array $ships, array $defenses = []): array
    {
        $costs = array_fill_keys(array_keys($this->catalog->rules()['resources']), 0);
        $seconds = 0;
        $config = $this->catalog->forUniverse($universe);
        foreach (['ships' => $ships, 'defenses' => $defenses] as $group => $composition) {
            foreach ($composition as $slug => $tiers) {
                foreach ($tiers as $tier => $quantity) {
                    if ($quantity > 0) {
                        $q = $this->operations->calculate($this->catalog->getTierForUniverse($universe, $group, $slug, $tier), $group, 1, $quantity, $checkpoint['buildings'], $config, $checkpoint['technologies']);
                        $seconds += $q['duration'];
                        foreach ($q['costs'] as $resource => $amount) {
                            $costs[$resource] += $amount;
                        }
                    }
                }
            }
        }
        return ['costs' => $costs, 'value' => CostDistributionCandidate::value($costs), 'shipyard_seconds' => $seconds];
    }

    private function signatureSelection(Universe $universe, array $checkpoint): array
    {
        $targets = ['hydrogen_collector' => 0, 'solar_satellite' => 0];
        for ($seed = 0; $seed < 200; $seed++) {
            $result = $this->combats->simulate([
                [
                    'id' => 'A',
                    'role' => 'attacker',
                    'camp' => 'attackers',
                    'arrival_round' => 1,
                    'units' => $this->units->ships($universe, ['fighter' => ['basic' => 1]], $checkpoint['technologies']),
                ],
                [
                    'id' => 'D',
                    'role' => 'defender',
                    'camp' => 'defenders',
                    'arrival_round' => 1,
                    'units' => $this->units->ships($universe, ['hydrogen_collector' => ['basic' => 10], 'solar_satellite' => ['basic' => 10]], $checkpoint['technologies']),
                ],
            ], [], $this->catalog->all('combat_modifiers')['modifiers'], (int) $this->catalog->rules()['max_combat_rounds'], $seed, 1);
            $shot = $result['rounds'][0]['shots'][0];
            foreach (array_keys($targets) as $slug) {
                if (str_contains($shot['target'], ':' . $slug . ':')) {
                    $targets[$slug]++;
                }
            }
        }
        return [
            'seeds' => '0..199',
            'units_per_family' => 10,
            'first_targets' => $targets,
            'signatures' => [
                'hydrogen_collector' => $this->catalog->getForUniverse($universe, 'ships', 'hydrogen_collector')['signature'],
                'solar_satellite' => $this->catalog->getForUniverse($universe, 'ships', 'solar_satellite')['signature'],
            ],
        ];
    }

    private function equalBudget(Universe $universe, array $checkpoint): array
    {
        $defenses = ['ground_turret_anti_light' => ['basic' => 10]];
        $baseline = ['fighter' => ['basic' => 10]];
        $budget = $this->compositionQuote($universe, $checkpoint, $baseline, $defenses)['value'];
        $militaryBudget = $this->compositionQuote($universe, $checkpoint, $baseline)['value'];
        $pairValue = $this->compositionQuote($universe, $checkpoint, ['hydrogen_collector' => ['basic' => 1], 'solar_satellite' => ['basic' => 1]])['value'];
        $civilCount = 2 * max(1, min(10, (int) floor(0.4 * $militaryBudget / $pairValue)));
        $civil = $this->forceComposition($checkpoint, $civilCount, 'basic');
        $civil['fighter']['basic'] = 0;
        $best = $civil;
        for ($fighters = 0; $fighters <= 10; $fighters++) {
            $composition = $civil;
            $composition['fighter']['basic'] = $fighters;
            if ($this->compositionQuote($universe, $checkpoint, $composition, $defenses)['value'] <= $budget) {
                $best = $composition;
            }
        }
        $actual = $this->compositionQuote($universe, $checkpoint, $best, $defenses)['value'];
        return [
            'reference_value' => $budget,
            'substitution_value' => $actual,
            'unspent_value' => $budget - $actual,
            'substitution_civil_units' => $civilCount,
            'reference' => $this->combatScenario($universe, $checkpoint, 0, 'basic', 'basic', [
                7,
                19,
                42,
                73,
                101,
            ], $baseline),
            'substitution' => $this->combatScenario($universe, $checkpoint, $civilCount, 'basic', 'basic', [
                7,
                19,
                42,
                73,
                101,
            ], $best),
        ];
    }

    private function civilReconstruction(Universe $universe, array $checkpoint, bool $emptyStocks = false): array
    {
        $before = $this->projection($universe, $checkpoint['buildings'], $checkpoint['units'], $checkpoint['stocks'], 600);
        $state = $checkpoint;
        $state['stocks'] = $emptyStocks ? array_fill_keys(array_keys($before['stocks']), 0.0) : $before['stocks'];
        $goals = [];
        foreach (['hydrogen_collector', 'solar_satellite'] as $slug) {
            foreach ($checkpoint['units'][$slug] ?? [] as $tier => $quantity) {
                if ($quantity > 0) {
                    $goals[] = [
                        'group' => 'ships',
                        'slug' => $slug,
                        'tier' => $tier,
                        'quantity' => $quantity,
                        'batch_size' => 5,
                    ];
                }
            }
            $state['units'][$slug] = [];
        }
        $after = $this->projection($universe, $state['buildings'], $state['units'], $state['stocks'], 0);
        $result = $this->funding->run($universe, $goals, ['initial' => $state]);
        $result['loss_hypothesis'] = $emptyStocks ? 'Perte totale civile et stocks imposés à zéro à +600 s ; usines intactes.' : 'Toutes les unités économiques de référence perdues à +600 s ; usines intactes, sans butin.';
        $result['production_before_closing'] = $before['totals']['production'];
        $result['sources_after_closing'] = $after['sources'];
        return $result;
    }

    private function severeRecovery(Universe $universe, array $checkpoint, bool $powerFirst): array
    {
        $state = $checkpoint;
        $state['units']['hydrogen_collector'] = $state['units']['solar_satellite'] = [];
        $state['stocks'] = array_fill_keys(array_keys($state['stocks']), 0.0);
        $state['buildings']['power_plant'] = ['basic' => 1];
        $initial = $this->projection($universe, $state['buildings'], $state['units'], $state['stocks'], 0);
        $fullStocks = array_fill_keys(array_keys($state['stocks']), 1000.0);
        $fullStocks['electricity'] = 1000000;
        $full = $this->projection($universe, $state['buildings'], $state['units'], $fullStocks, 0);
        $one = $this->projection($universe, ['power_plant' => ['basic' => 1]], [], $state['stocks'], 0)['production']['electricity'];
        $target = (int) floor($full['consumption']['electricity'] / $one) + 1;
        $goals = [];
        if ($powerFirst) {
            $goals[] = ['group' => 'buildings', 'slug' => 'power_plant', 'level' => $target, 'label' => 'power_restored'];
        }
        foreach ([['solar_satellite', 2], ['hydrogen_collector', 1]] as [$slug, $quantity]) {
            $goal = ['group' => 'ships', 'slug' => $slug, 'quantity' => $quantity, 'batch_size' => 1];
            if ($powerFirst) {
                $goal['after_label'] = 'power_restored';
            }
            $goals[] = $goal;
        }
        $result = $this->funding->run($universe, $goals, ['initial' => $state]);
        $result['initial_factory_efficiency'] = $initial['efficiency'];
        $result['initial_rates_per_hour'] = array_map(static fn(float $rate): float => $rate * 3600, $initial['rates']);
        $result['power_target'] = $powerFirst ? $target : null;
        $result['loss_hypothesis'] = 'État de stress donné : stocks nuls, aucune unité civile, seule centrale B1, autres usines et accès de référence. Objectif : deux satellites et un récolteur Basiques.';
        $result['policy'] = $powerFirst ? 'Financer d’abord une centrale Basique fournissant plus que la consommation nominale mesurée.' : 'Attendre le financement avec les usines existantes et leurs arrêts aux plafonds.';
        return $result;
    }

    private function marginalInvestments(Universe $universe, array $checkpoint): array
    {
        $rows = [];
        $stocks = array_fill_keys(array_keys($checkpoint['stocks']), 1000.0);
        $stocks['electricity'] = 1000000;
        $before = $this->projection($universe, $checkpoint['buildings'], $checkpoint['units'], $stocks, 0);
        foreach (['hydrogen_collector' => 'mine_hydrogen', 'solar_satellite' => 'power_plant'] as $slug => $building) {
            foreach (GameCatalog::TIERS as $tier) {
                $definition = $this->catalog->getForUniverse($universe, 'ships', $slug);
                $quantity = $definition['economic_unit']['slots_per_level'];
                $resource = $definition['economic_unit']['resource'];
                $levels = $checkpoint['buildings'];
                $level = ($levels[$building][$tier] ?? 0) + 1;
                $levels[$building][$tier] = $level;
                $ground = $this->projection($universe, $levels, $checkpoint['units'], $stocks, 0);
                $units = $checkpoint['units'];
                $units[$slug][$tier] = ($units[$slug][$tier] ?? 0) + $quantity;
                $combined = $this->projection($universe, $levels, $units, $stocks, 0);
                $config = $this->catalog->forUniverse($universe);
                $groundQuote = $this->operations->calculate($this->catalog->getTierForUniverse($universe, 'buildings', $building, $tier), 'buildings', $level, 1, $checkpoint['buildings'], $config, $checkpoint['technologies']);
                $civilQuote = $this->operations->calculate($this->catalog->getTierForUniverse($universe, 'ships', $slug, $tier), 'ships', 1, $quantity, $levels, $config, $checkpoint['technologies']);
                $rows[] = [
                    'building' => $building,
                    'tier' => $tier,
                    'next_level' => $level,
                    'civil_slug' => $slug,
                    'civil_quantity' => $quantity,
                    'ground_quote' => $groundQuote,
                    'civil_quote' => $civilQuote,
                    'ground_nominal_gain_per_hour' => 3600 * ($ground['production'][$resource] - $before['production'][$resource]),
                    'civil_nominal_gain_per_hour' => 3600 * ($combined['production'][$resource] - $ground['production'][$resource]),
                    'resource' => $resource,
                ];
            }
        }
        return $rows;
    }

    private function repairPercent(array $technologies): float
    {
        $definition = $this->catalog->get('technologies', 'automated_defense_repair');
        return $this->catalog->researchBonus($technologies['automated_defense_repair'] ?? [], (float) $this->catalog->rules()['defense_rebuild_max_percent'], (int) $definition['max_level']);
    }

    private function defenseReconstruction(Universe $universe, array $checkpoint, bool $researchRepair): array
    {
        $state = $checkpoint;
        $state['buildings']['fortress']['basic'] = 8;
        $setup = null;
        if ($researchRepair) {
            $setup = $this->funding->run($universe, [['group' => 'technologies', 'slug' => 'automated_defense_repair', 'level' => 5]], ['initial' => $state]);
            $state = $setup['checkpoint'];
        }
        $percent = $this->repairPercent($state['technologies']);
        $state['defenses'] = [];
        $losses = [
            'ground_turret_anti_light' => 100,
            'ground_turret_anti_escort' => 20,
            'ground_turret_anti_heavy' => 10,
            'ground_turret_anti_capital' => 5,
        ];
        $goals = [];
        $restored = [];
        foreach ($losses as $slug => $quantity) {
            $restored[$slug] = (int) floor($quantity * $percent / 100);
            $state['defenses'][$slug]['basic'] = $restored[$slug];
            if ($quantity > $restored[$slug]) {
                $goals[] = ['group' => 'defenses', 'slug' => $slug, 'quantity' => $quantity - $restored[$slug], 'batch_size' => 10];
            }
        }
        $result = $this->funding->run($universe, $goals, ['initial' => $state]);
        $result['loss_hypothesis'] = 'Pertes imposées 100 / 20 / 10 / 5 défenses Basiques, forteresse B8 donnée, sans butin.';
        $result['repair_percent'] = $percent;
        $result['automatically_restored'] = $restored;
        if ($setup !== null) {
            $result['repair_research_setup'] = ['seconds' => $setup['seconds'], 'spent' => $setup['metrics']['spent']];
        }
        return $result;
    }
}
