<?php

namespace App\Service\Balance;

use App\Entity\{Galaxy, Planet, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{CatalogueProductionContext, EconomicUnitCapacity, ResourceService};
use App\Service\Combat\CombatUnitFactory;
use App\Service\Fleet\MovementCalculator;
use App\Service\Queue\OperationCalculator;
use App\Service\Requirement\RequirementChecker;
/** Parcours déterministe hors base, avec paiement au départ de chaque opération et files indépendantes. */
final class FundedProgressionSimulation
{
    private Planet $planet;
    private array $config;
    private array $options;
    /** @var array<string,array<string,int>> */
    private array $buildings;
    /** @var array<string,array<string,int>> */
    private array $technologies;
    /** @var array<string,array<string,int>> */
    private array $units;
    /** @var array<string,array<string,int>> */
    private array $defenses;
    /** @var array<string,float> */
    private array $stocks;
    /** @var array<string,true> Accès permanents acquis par la grille ou fournis explicitement. */
    private array $access;
    private array $nodes;
    private array $labels;
    private array $queues;
    private array $waiting;
    private array $metrics;
    private array $milestones;
    private int $time;
    private int $sequence;
    private bool $accessDirty = true;
    public function __construct(private GameCatalog $catalog, private OperationCalculator $operations, private RequirementChecker $requirements, private ResourceService $resources, private EconomicUnitCapacity $economicUnits, private CombatUnitFactory $combatUnits, private MovementCalculator $movement)
    {
    }
    /**
     * Les infrastructures initiales éventuelles sont un état expérimental déclaré, pas un départ neuf.
     * Les listes d'objectifs sont une politique, sans recherche d'optimum ni achats anticipés en file.
     * @param list<array> $goals
     * @param array{
     *   initial?:array{buildings?:array,technologies?:array,units?:array,defenses?:array,stocks?:array,access?:list<string>},
     *   calendar?:list<array{from:int,until:int,multiplier?:float,vacation?:bool}>,
     *   position?:int,colony?:bool,trace?:bool,auto_storage?:bool,
     *   max_seconds?:int,minimum_seconds?:int,stock_multiplier?:float,
     *   session_interval?:positive-int,session_seconds?:int
     * } $options
     * @return array<string,mixed> Bilan, jalons et état final ; les dates sont des secondes relatives.
     */
    public function run(Universe $universe, array $goals, array $options = []): array
    {
        $this->initialize($universe, $options);
        foreach ($goals as $goal) {
            $this->addGoal($goal);
        }
        $limit = (int) ($options['max_seconds'] ?? 31536000);
        $minimum = (int) ($options['minimum_seconds'] ?? 0);
        if ($limit < 1 || $minimum < 0 || $minimum > $limit) {
            throw new \InvalidArgumentException('Horizon de scénario invalide.');
        }
        $interval = $options['session_interval'] ?? null;
        $sessionSeconds = $options['session_seconds'] ?? 0;
        if ($interval !== null && ($interval < 1 || $sessionSeconds < 0 || $sessionSeconds >= $interval)) {
            throw new \InvalidArgumentException('Cadence de connexion invalide.');
        }
        $status = 'complete';
        $iterations = 0;
        while (true) {
            if (++$iterations > 100000) {
                throw new \LogicException('Le parcours ne progresse plus dans sa limite de calcul.');
            }
            $this->completeDue();
            $this->synchronizeAccess();
            if ($this->pending() === 0 && $this->time >= $minimum) {
                break;
            }
            if ($this->time >= $limit) {
                $status = 'horizon';
                break;
            }
            $context = $this->context();
            if ($context->onVacation && $this->queues['fleet'] !== null) {
                // Le jeu exige le retour des flottes avant les vacances ; ne pas inventer un vol mis en pause.
                throw new \DomainException('Un calendrier de vacances ne peut pas interrompre une mission en vol.');
            }
            // Une absence décale uniquement les nouveaux achats et départs. Les échéances et
            // la production restent traitées à leur date, comme pendant une session.
            $sessionOffset = $interval === null ? 0 : $this->time % $interval;
            $outsideSession = $interval !== null && $sessionOffset > $sessionSeconds;
            $canIssueOrders = !$context->onVacation && !$outsideSession;
            if ($canIssueOrders && $this->startReady()) {
                continue;
            }
            if (!$canIssueOrders) {
                $this->waiting = [];
            }
            $point = $this->resources->project($this->planet, $this->buildings, $this->stocks, 0, $context);
            $step = $this->nextStep($point, $limit, $minimum, $context->onVacation);
            if ($interval !== null) {
                $sessionBoundary = $outsideSession ? $interval : $sessionSeconds + 1;
                $untilBoundary = min($limit - $this->time, $sessionBoundary - $sessionOffset);
                $step = min($step ?? $untilBoundary, $untilBoundary);
            }
            if ($step === null) {
                $status = 'blocked';
                break;
            }
            $this->advance($step, $context, $outsideSession);
        }
        $point = $this->resources->project($this->planet, $this->buildings, $this->stocks, 0, $this->context());
        $pending = [];
        foreach ($this->nodes as $node) {
            if ($node['status'] !== 'done') {
                $pending[] = ['group' => $node['group'], 'slug' => $node['slug'], 'tier' => $node['tier'], 'status' => $node['status']];
            }
        }
        $result = [
            'status' => $status,
            'seconds' => $this->time,
            'operations_completed' => count($this->nodes) - count($pending),
            'operations_pending' => count($pending),
            'pending_sample' => array_slice($pending, 0, 8),
            'milestones' => $this->milestones,
            'metrics' => $this->metrics,
            'end_stocks' => $this->stocks,
            'end_rates_per_hour' => array_map(static fn(float $rate): float => $rate * 3600, $point['rates']),
            'end_factory_efficiency' => $point['efficiency'],
            'civil_state' => $this->economicUnits->state($this->planet, $this->buildings, $this->inventory()),
            'checkpoint' => [
                'buildings' => $this->buildings,
                'technologies' => $this->technologies,
                'units' => $this->units,
                'defenses' => $this->defenses,
                'stocks' => $this->stocks,
                'access' => array_keys($this->access),
            ],
        ];
        if ($options['trace'] ?? false) {
            $result['operations'] = array_values($this->nodes);
        }
        return $result;
    }

    private function initialize(Universe $universe, array $options): void
    {
        $this->options = $options;
        $this->config = $this->catalog->forUniverse($universe);
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $system = new SolarSystem();
        $system->galaxy = $galaxy;
        $owner = new PlayerUniverse();
        $owner->universe = $universe;
        $this->planet = new Planet();
        $this->planet->solarSystem = $system;
        $this->planet->owner = $owner;
        $this->planet->position = (int) ($options['position'] ?? 5);
        if ($this->planet->position < 1 || $this->planet->position > 9) {
            throw new \InvalidArgumentException('Position de scénario invalide.');
        }
        $initial = $options['initial'] ?? [];
        $this->buildings = $initial['buildings'] ?? [];
        $this->technologies = $initial['technologies'] ?? [];
        $this->units = $initial['units'] ?? [];
        $this->defenses = $initial['defenses'] ?? [];
        $this->access = array_fill_keys($initial['access'] ?? [], true);
        $kit = $this->catalog->rules()[$options['colony'] ?? false ? 'colony_initial_stock' : 'initial_stock'];
        $this->stocks = [];
        foreach ($kit as $resource => $amount) {
            $this->stocks[$resource] = (float) ($initial['stocks'][$resource] ?? min($this->resources->capacity($this->planet, $resource, $this->buildings), $amount * ($options['stock_multiplier'] ?? 1)));
        }
        $this->nodes = $this->labels = $this->waiting = $this->milestones = [];
        $this->queues = array_fill_keys(['building', 'research', 'shipyard', 'fleet'], null);
        $this->time = $this->sequence = 0;
        $this->accessDirty = true;
        $zeros = array_fill_keys(array_keys($this->stocks), 0.0);
        $this->metrics = [
            'initial_stocks' => $this->stocks,
            'spent' => $zeros,
            'production' => $zeros,
            'consumption' => $zeros,
            'uncredited' => $zeros,
            'peak_stocks' => $this->stocks,
            'queue_busy_seconds' => array_fill_keys(array_keys($this->queues), 0),
            'queue_funding_wait_seconds' => array_fill_keys(array_keys($this->queues), 0),
            'queue_dependency_wait_seconds' => array_fill_keys(array_keys($this->queues), 0),
            'resource_wait_seconds' => $zeros,
            'costs_by_group' => [],
            'vacation_seconds' => 0,
            'fuel_charged' => 0.0,
            'missions_completed' => 0,
            'exported_resources' => $zeros,
        ];
        if (isset($options['session_interval'])) {
            $this->metrics['queue_session_wait_seconds'] = array_fill_keys(array_keys($this->queues), 0);
        }
    }

    private function addGoal(array $goal): void
    {
        $group = $goal['group'];
        $tier = $goal['tier'] ?? 'basic';
        $after = isset($goal['after_label']) ? $this->labels[$goal['after_label']] ?? throw new \InvalidArgumentException('Objectif précédent inconnu.') : null;
        if (in_array($group, ['buildings', 'technologies'], true)) {
            $id = $this->addLevel($group, $goal['slug'], $tier, (int) $goal['level']);
        } elseif (in_array($group, ['ships', 'defenses'], true)) {
            $remaining = (int) $goal['quantity'];
            $batch = (int) ($goal['batch_size'] ?? $remaining);
            $id = null;
            if ($remaining < 1 || $batch < 1) {
                throw new \InvalidArgumentException('Quantité de scénario invalide.');
            }
            while ($remaining > 0) {
                $quantity = min($remaining, $batch);
                $deps = $this->requirementNodes($group, $goal['slug'], $tier, []);
                if ($id !== null) {
                    $deps[] = $id;
                }
                if ($after !== null) {
                    $deps[] = $after;
                }
                $id = $this->storeNode($group, $goal['slug'], $tier, 1, $quantity, $deps);
                $remaining -= $quantity;
            }
        } elseif ($group === 'missions') {
            $id = $this->storeNode($group, $goal['mission'], $tier, 1, 1, $after === null ? [] : [$after]);
            $this->nodes[$id]['mission'] = $goal;
        } else {
            throw new \InvalidArgumentException('Groupe de scénario inconnu.');
        }
        if ($id !== null) {
            if ($after !== null && !in_array($after, $this->nodes[$id]['dependencies'], true)) {
                $this->nodes[$id]['dependencies'][] = $after;
            }
            if (isset($goal['label'])) {
                $this->labels[$goal['label']] = $id;
                $this->nodes[$id]['label'] = $goal['label'];
            }
        }
    }

    private function addLevel(string $group, string $slug, string $tier, int $level, array $visited = []): ?string
    {
        $levels = $group === 'buildings' ? $this->buildings : $this->technologies;
        if (($levels[$slug][$tier] ?? 0) >= $level) {
            return null;
        }
        $key = $group . ':' . $slug . ':' . $tier . ':' . $level;
        if (isset($this->nodes[$key])) {
            return $key;
        }
        if (in_array($key, $visited, true)) {
            throw new \LogicException('Cycle dans le parcours : ' . $key);
        }
        $definition = $this->catalog->getTierForUniverse($this->planet->solarSystem->galaxy->universe, $group, $slug, $tier);
        if ($level < 1 || $level > ($definition['max_level'] ?? PHP_INT_MAX)) {
            throw new \DomainException('Niveau de scénario hors plafond : ' . $key);
        }
        $deps = [];
        if ($level > 1) {
            $previous = $this->addLevel($group, $slug, $tier, $level - 1, [...$visited, $key]);
            if ($previous !== null) {
                $deps[] = $previous;
            }
        }
        $deps = [...$deps, ...$this->requirementNodes($group, $slug, $tier, [...$visited, $key])];
        return $this->storeNode($group, $slug, $tier, $level, 1, $deps, $key);
    }

    private function requirementNodes(string $group, string $slug, string $tier, array $visited): array
    {
        if (isset($this->access[$group . ':' . $slug . ':' . $tier])) {
            return [];
        }
        $definition = $this->config['catalog'][$group][$slug] ?? throw new \DomainException('Élément de scénario inconnu.');
        $requirements = $this->requirements->forTier($definition, $tier);
        $deps = [];
        foreach (['buildings', 'technologies'] as $requiredGroup) {
            foreach ($requirements[$requiredGroup] ?? [] as $requirement) {
                $id = $this->addLevel($requiredGroup, $requirement['slug'], $requirement['tier'] ?? 'basic', (int) $requirement['level'], $visited);
                if ($id !== null) {
                    $deps[] = $id;
                }
            }
        }
        foreach ($requirements['ships'] ?? [] as $reference) {
            $ship = is_array($reference) ? $reference : ['slug' => $reference, 'tier' => 'basic'];
            $key = 'ship-reference:' . $ship['slug'] . ':' . ($ship['tier'] ?? 'basic');
            if (in_array($key, $visited, true)) {
                throw new \LogicException('Cycle de référence de vaisseau.');
            }
            $deps = [...$deps, ...$this->requirementNodes('ships', $ship['slug'], $ship['tier'] ?? 'basic', [...$visited, $key])];
        }
        return array_values(array_unique($deps));
    }

    private function storeNode(string $group, string $slug, string $tier, int $level, int $quantity, array $dependencies, ?string $key = null): string
    {
        $order = ++$this->sequence;
        $key ??= 'operation:' . $order;
        $this->nodes[$key] = [
            'id' => $key,
            'group' => $group,
            'slug' => $slug,
            'tier' => $tier,
            'level' => $level,
            'quantity' => $quantity,
            'dependencies' => array_values(array_unique($dependencies)),
            'priority' => $order,
            'status' => 'planned',
        ];
        return $key;
    }

    private function queue(string $group): string
    {
        return match ($group) {
            'buildings' => 'building',
            'technologies' => 'research',
            'missions' => 'fleet',
            default => 'shipyard',
        };
    }

    private function ready(array $node): bool
    {
        if ($node['status'] !== 'planned') {
            return false;
        }
        foreach ($node['dependencies'] as $id) {
            if ($this->nodes[$id]['status'] !== 'done') {
                return false;
            }
        }
        if ($node['group'] === 'missions') {
            foreach ($node['mission']['composition'] as $slug => $tiers) {
                foreach ($tiers as $tier => $quantity) {
                    if (($this->units[$slug][$tier] ?? 0) < $quantity) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    private function earliest(array $node): int
    {
        if ($node['group'] !== 'missions') {
            return 0;
        }
        $goal = $node['mission'];
        $time = (int) ($goal['not_before'] ?? 0);
        if (isset($goal['spacing_after_label'])) {
            $previous = $this->nodes[$this->labels[$goal['spacing_after_label']]];
            $time = max($time, ($previous['started_at'] ?? 0) + (int) $goal['interval_seconds']);
        }
        return $time;
    }

    private function quote(array $node): array
    {
        if ($node['group'] === 'missions') {
            return $this->missionQuote($node['mission']);
        }
        $definition = $this->catalog->withTier($this->config['catalog'][$node['group']][$node['slug']], $node['tier']);
        return $this->operations->calculate($definition, $node['group'], $node['level'], $node['quantity'], $this->buildings, $this->config, $this->technologies);
    }

    private function missionQuote(array $goal): array
    {
        $kind = $goal['mission'];
        if (!in_array($kind, ['spy', 'transport', 'orbit', 'colonize'], true)) {
            throw new \InvalidArgumentException('Mission de parcours non prise en charge.');
        }
        if ($kind === 'colonize') {
            $effect = $this->config['catalog']['technologies']['colonial_expansion']['effect'];
            $limit = GameCatalog::colonyLimit($this->technologies['colonial_expansion'] ?? [], $effect, $this->config['limits']['max_planets']);
            // Le scénario suit une seule colonie d'origine et consomme l'unique colonisateur à l'arrivée.
            if ($limit < 2 || array_sum($goal['composition']['colony_ship'] ?? []) !== 1 || count($goal['composition']) !== 1) {
                throw new \DomainException('Le scénario exige un emplacement libre et un colonisateur seul.');
            }
        }
        $ships = $this->combatUnits->ships($this->planet->solarSystem->galaxy->universe, $goal['composition'], $this->technologies);
        foreach ($ships as $ship) {
            if (isset($ship['allowed_missions']) && !in_array($kind, $ship['allowed_missions'], true)) {
                throw new \DomainException('Mission interdite pour cette unité.');
            }
            if ($kind === 'spy' && $ship['slug'] !== 'probe') {
                throw new \DomainException('L’espionnage exige des sondes.');
            }
        }
        $quote = $this->movement->quoteDistances($goal['normal_distance'] ?? 100, $goal['hyper_distance'] ?? 100, $goal['same_system'] ?? false, $ships, $goal['speed_ratio'] ?? 1, $this->config['multipliers']['travel_time'], $this->catalog->rules()['fuel_ratio_policy'], $goal['orbit_hours'] ?? 0);
        if ($quote['total_fuel'] > $quote['capacity'] + 1.0E-6) {
            throw new \DomainException('Carburant de scénario hors capacité.');
        }
        $cargo = $goal['cargo'] ?? [];
        if (array_sum($cargo) > $quote['freight_capacity'] + 1.0E-6) {
            throw new \DomainException('Fret de scénario hors capacité.');
        }
        $costs = $cargo;
        $costs['hydrogen'] = ($costs['hydrogen'] ?? 0) + $quote['total_fuel'];
        // Le vol de colonisation aboutit à l'arrivée ; le carburant aller-retour est réservé au départ.
        $seconds = in_array($kind, ['spy', 'colonize'], true)
            ? $quote['seconds']
            : 2 * $quote['seconds'] + (int) round(($goal['orbit_hours'] ?? 0) * 3600);
        return ['duration' => $seconds, 'costs' => $costs, 'navigation' => $quote];
    }

    private function startReady(): bool
    {
        $changed = false;
        $this->waiting = [];
        $ordered = array_keys($this->nodes);
        usort($ordered, fn(string $a, string $b): int => $this->nodes[$a]['priority'] <=> $this->nodes[$b]['priority']);
        foreach (array_keys($this->queues) as $queue) {
            if ($this->queues[$queue] !== null) {
                continue;
            }
            foreach ($ordered as $id) {
                $node = $this->nodes[$id];
                if ($this->queue($node['group']) !== $queue || !$this->ready($node) || $this->earliest($node) > $this->time) {
                    continue;
                }
                if ($node['group'] !== 'missions') {
                    $this->captureAccess($node);
                }
                $plan = $this->quote($node);
                $capacityBlocked = false;
                foreach ($plan['costs'] as $resource => $cost) {
                    if ($cost > $this->resources->capacity($this->planet, $resource, $this->buildings) + 1.0E-6) {
                        $capacityBlocked = true;
                        if ($this->options['auto_storage'] ?? true) {
                            $changed = $this->ensureStorage($resource) || $changed;
                        }
                    }
                }
                if ($capacityBlocked) {
                    continue;
                }
                if ($node['group'] === 'ships') {
                    $definition = $this->config['catalog']['ships'][$node['slug']];
                    if (isset($definition['economic_unit']) && ($definition['mobile'] ?? true) === false) {
                        $state = $this->economicUnits->state($this->planet, $this->buildings, $this->inventory())[$node['slug']][$node['tier']];
                        if ($node['quantity'] > $state['available']) {
                            $needed = (int) ceil(($state['present'] + $node['quantity']) / $definition['economic_unit']['slots_per_level']);
                            $before = count($this->nodes);
                            $this->addLevel('buildings', $definition['economic_unit']['capacity_building'], $node['tier'], $needed);
                            $changed = count($this->nodes) > $before || $changed;
                            continue;
                        }
                    }
                }
                $this->waiting[$queue][] = ['id' => $id, 'costs' => $plan['costs']];
                if (!$this->affordable($plan['costs'])) {
                    continue;
                }
                foreach ($plan['costs'] as $resource => $cost) {
                    $this->stocks[$resource] = max(0.0, $this->stocks[$resource] - $cost);
                    $this->metrics['spent'][$resource] += $cost;
                    $this->metrics['costs_by_group'][$node['group']][$resource] = ($this->metrics['costs_by_group'][$node['group']][$resource] ?? 0) + $cost;
                }
                $this->nodes[$id]['status'] = 'running';
                $this->nodes[$id]['started_at'] = $this->time;
                $this->nodes[$id]['finishes_at'] = $this->time + $plan['duration'];
                $this->nodes[$id]['plan'] = $plan;
                $this->queues[$queue] = $id;
                $changed = true;
                $this->milestones['first_start:' . $node['group'] . ':' . $node['slug'] . ':' . $node['tier']] ??= $this->time;
                if ($node['group'] === 'missions') {
                    foreach ($node['mission']['composition'] as $slug => $tiers) {
                        foreach ($tiers as $tier => $quantity) {
                            $this->units[$slug][$tier] -= $quantity;
                        }
                    }
                    $this->metrics['fuel_charged'] += $plan['navigation']['total_fuel'];
                    foreach ($node['mission']['cargo'] ?? [] as $resource => $amount) {
                        $this->metrics['exported_resources'][$resource] += $amount;
                    }
                }
                break;
            }
        }
        return $changed;
    }

    private function synchronizeAccess(): void
    {
        if (!$this->accessDirty) {
            return;
        }
        // L'accès dépend des conditions acquises, pas du financement ni de la possession d'une unité.
        foreach ($this->config['catalog'] as $group => $definitions) {
            foreach ($definitions as $slug => $definition) {
                foreach (GameCatalog::TIERS as $tier) {
                    $key = $group . ':' . $slug . ':' . $tier;
                    if (isset($this->access[$key])) {
                        continue;
                    }
                    if ($this->requirements->check($this->requirements->forTier($definition, $tier), $this->buildings, $this->technologies, definitions: $this->config['catalog']) !== []) {
                        continue;
                    }
                    $this->access[$key] = true;
                    $this->milestones['access:' . $key] = $this->time;
                    $this->milestones['first_access:' . $tier] ??= $this->time;
                }
            }
        }
        $this->accessDirty = false;
    }

    private function captureAccess(array $node): void
    {
        $key = $node['group'] . ':' . $node['slug'] . ':' . $node['tier'];
        if (isset($this->access[$key])) {
            return;
        }
        $definition = $this->config['catalog'][$node['group']][$node['slug']];
        $failures = $this->requirements->check($this->requirements->forTier($definition, $node['tier']), $this->buildings, $this->technologies, definitions: $this->config['catalog']);
        if ($failures !== []) {
            throw new \LogicException('Le graphe du parcours a omis un prérequis : ' . implode(', ', $failures));
        }
        $this->access[$key] = true;
        $this->milestones['access:' . $key] = $this->time;
        $this->milestones['first_access:' . $node['tier']] ??= $this->time;
    }

    private function ensureStorage(string $resource): bool
    {
        $slug = 'storage_' . $resource;
        $before = count($this->nodes);
        $id = $this->addLevel('buildings', $slug, 'basic', ($this->buildings[$slug]['basic'] ?? 0) + 1);
        if ($id !== null && $this->nodes[$id]['priority'] > 0) {
            $this->nodes[$id]['priority'] -= 100000;
        }
        return count($this->nodes) > $before;
    }

    private function affordable(array $costs): bool
    {
        foreach ($costs as $resource => $amount) {
            if (($this->stocks[$resource] ?? 0) + 1.0E-6 < $amount) {
                return false;
            }
        }
        return true;
    }

    private function completeDue(): void
    {
        foreach ($this->queues as $queue => $id) {
            if ($id === null || $this->nodes[$id]['finishes_at'] > $this->time) {
                continue;
            }
            $node = $this->nodes[$id];
            $group = $node['group'];
            $slug = $node['slug'];
            $tier = $node['tier'];
            if ($group === 'buildings') {
                $this->buildings[$slug][$tier] = $node['level'];
            } elseif ($group === 'technologies') {
                $this->technologies[$slug][$tier] = $node['level'];
            } elseif ($group === 'ships') {
                $this->units[$slug][$tier] = ($this->units[$slug][$tier] ?? 0) + $node['quantity'];
            } elseif ($group === 'defenses') {
                $this->defenses[$slug][$tier] = ($this->defenses[$slug][$tier] ?? 0) + $node['quantity'];
            } else {
                if (!in_array($node['mission']['mission'], ['spy', 'colonize'], true)) {
                    foreach ($node['mission']['composition'] as $ship => $tiers) {
                        foreach ($tiers as $rank => $quantity) {
                            $this->units[$ship][$rank] += $quantity;
                        }
                    }
                }
                $this->metrics['missions_completed']++;
            }
            if (in_array($group, ['buildings', 'technologies'], true)) {
                $this->accessDirty = true;
            }
            $this->nodes[$id]['status'] = 'done';
            $this->nodes[$id]['completed_at'] = $this->time;
            $this->queues[$queue] = null;
            $this->milestones['first_finish:' . $group . ':' . $slug . ':' . $tier] ??= $this->time;
            if (isset($node['label'])) {
                $this->milestones[$node['label']] = $this->time;
            }
        }
    }

    private function inventory(): array
    {
        $construction = [];
        if (($id = $this->queues['shipyard']) !== null) {
            $node = $this->nodes[$id];
            $definition = $this->config['catalog']['ships'][$node['slug']] ?? [];
            if ($node['group'] === 'ships' && isset($definition['economic_unit']) && ($definition['mobile'] ?? true) === false) {
                $construction[$node['slug']][$node['tier']] = $node['quantity'];
            }
        }
        return ['present' => $this->units, 'incoming' => [], 'construction' => $construction];
    }

    private function context(): CatalogueProductionContext
    {
        $multiplier = 1.0;
        $vacation = false;
        foreach ($this->options['calendar'] ?? [] as $period) {
            if ($period['from'] <= $this->time && $period['until'] > $this->time) {
                $multiplier *= $period['multiplier'] ?? 1;
                $vacation = $vacation || ($period['vacation'] ?? false);
            }
        }
        return new CatalogueProductionContext($this->planet, $vacation, $multiplier, $this->inventory());
    }

    private function pending(): int
    {
        return count(array_filter($this->nodes, static fn(array $node): bool => $node['status'] !== 'done'));
    }

    private function nextStep(array $point, int $limit, int $minimum, bool $vacation): ?int
    {
        $next = INF;
        foreach ($this->options['calendar'] ?? [] as $period) {
            foreach ([$period['from'], $period['until']] as $boundary) {
                if ($boundary > $this->time) {
                    $next = min($next, $boundary - $this->time);
                }
            }
        }
        if ($minimum > $this->time && $this->pending() === 0) {
            $next = min($next, $minimum - $this->time);
        }
        if (!$vacation) {
            foreach ($this->queues as $id) {
                if ($id !== null) {
                    $next = min($next, $this->nodes[$id]['finishes_at'] - $this->time);
                }
            }
            foreach ($point['rates'] as $resource => $rate) {
                $stock = $this->stocks[$resource];
                $cap = $this->resources->capacity($this->planet, $resource, $this->buildings);
                if ($rate > 1.0E-12 && $stock < $cap - 1.0E-7) {
                    $next = min($next, max(1, (int) ceil(($cap - $stock) / $rate)));
                } elseif ($rate < -1.0E-12 && $stock > 1.0E-7) {
                    $next = min($next, max(1, (int) ceil(($stock - ($stock > $cap + 1.0E-7 ? $cap : 0)) / -$rate)));
                }
            }
            foreach ($this->nodes as $node) {
                if ($this->ready($node) && $this->earliest($node) > $this->time) {
                    $next = min($next, $this->earliest($node) - $this->time);
                }
            }
            foreach ($this->waiting as $plans) {
                foreach ($plans as $plan) {
                    $delay = 0.0;
                    foreach ($plan['costs'] as $resource => $cost) {
                        if ($cost > $this->stocks[$resource] + 1.0E-6) {
                            $rate = $point['rates'][$resource] ?? 0;
                            if ($rate <= 1.0E-12) {
                                $delay = INF;
                                break;
                            }
                            $delay = max($delay, ($cost - $this->stocks[$resource]) / $rate);
                        }
                    }
                    if ($delay > 0 && is_finite($delay)) {
                        $next = min($next, max(1, (int) ceil($delay)));
                    }
                }
            }
        }
        return is_finite($next) ? max(1, min($limit - $this->time, (int) $next)) : null;
    }

    private function advance(int $seconds, CatalogueProductionContext $context, bool $outsideSession): void
    {
        foreach ($this->queues as $queue => $id) {
            if ($context->onVacation) {
                if ($id !== null) {
                    $this->nodes[$id]['finishes_at'] += $seconds;
                }
                continue;
            }
            if ($id !== null) {
                $this->metrics['queue_busy_seconds'][$queue] += $seconds;
            } elseif (($plans = $this->waiting[$queue] ?? []) !== []) {
                $this->metrics['queue_funding_wait_seconds'][$queue] += $seconds;
                foreach ($plans[0]['costs'] as $resource => $cost) {
                    if ($cost > $this->stocks[$resource] + 1.0E-6) {
                        $this->metrics['resource_wait_seconds'][$resource] += $seconds;
                    }
                }
            } elseif (array_filter($this->nodes, fn(array $node): bool => $node['status'] === 'planned' && $this->queue($node['group']) === $queue) !== []) {
                $metric = $outsideSession ? 'queue_session_wait_seconds' : 'queue_dependency_wait_seconds';
                $this->metrics[$metric][$queue] += $seconds;
            }
        }
        if ($context->onVacation) {
            $this->metrics['vacation_seconds'] += $seconds;
        }
        $result = $this->resources->project($this->planet, $this->buildings, $this->stocks, $seconds, $context);
        foreach ($result['totals'] as $field => $amounts) {
            foreach ($amounts as $resource => $amount) {
                $this->metrics[$field][$resource] += $amount;
            }
        }
        $this->stocks = $result['stocks'];
        $this->time += $seconds;
        foreach ($this->stocks as $resource => $stock) {
            $this->metrics['peak_stocks'][$resource] = max($this->metrics['peak_stocks'][$resource], $stock);
        }
    }
}
