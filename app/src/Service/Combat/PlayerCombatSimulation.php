<?php

namespace App\Service\Combat;

use App\Entity\{Planet, PlanetBuilding, PlanetDefense, PlayerTechnology, PlayerUniverse, Fleet, FleetShip};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\EspionageReportReader;
use App\Service\PirateCampaign\PirateBattleScenario;

final class PlayerCombatSimulation
{
    private const INLINE_UNITS = 500;
    private const INLINE_WORK = 5_000_000;
    // Les défenses ne font pas partie de la composition d’une flotte.
    public const MAX_DEFENSE_QUANTITY = 2_147_483_647;
    public const TRIALS = 10;
    public function __construct(
        private CombatSimulator $simulator,
        private CombatUnitFactory $units,
        private GameCatalog $catalog,
        private GameRepository $repo,
        private EspionageReportReader $reports,
    )
    {
    }

    public function choices(PlayerUniverse $owner): array
    {
        $catalog = $this->catalog->forUniverse($owner->universe)['catalog'];
        return ['ships' => $catalog['ships'], 'defenses' => $catalog['defenses'], 'research' => array_filter($catalog['technologies'], static fn(array $entry) => in_array($entry['effect']['type'] ?? '', ['stat_bonus', 'extra_shot_chance'], true))];
    }

    public function fleetLimit(PlayerUniverse $owner): int
    {
        return (int) $this->catalog->forUniverse($owner->universe)['limits']['max_fleet_size'];
    }

    public function own(PlayerUniverse $owner, int $planetId, bool $includeGarrison = false): array
    {
        $planet = $this->repo->find(Planet::class, $planetId);
        if (!$planet || $planet->owner?->id !== $owner->id || $planet->solarSystem->galaxy->universe->id !== $owner->universe->id) {
            throw new \DomainException('Colonie inaccessible.');
        }
        $ships = [];
        $definitions = $this->choices($owner)['ships'];
        $fleets = $this->repo->all(Fleet::class, ['playerUniverse' => $owner, 'homePlanet' => $planet, 'status' => 'idle']);
        foreach ($fleets === [] ? [] : $this->repo->all(FleetShip::class, ['fleet' => $fleets]) as $ship) {
            if ($ship->quantity <= 0 || (!$includeGarrison && isset($definitions[$ship->slug]['economic_unit']))) {
                continue;
            }
            $ships[$ship->slug][$ship->tier] = ($ships[$ship->slug][$ship->tier] ?? 0) + $ship->quantity;
        }
        $forces = ['ships' => $ships, 'research' => $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $owner])];
        if ($includeGarrison) {
            foreach ($this->repo->all(PlanetDefense::class, ['planet' => $planet]) as $defense) {
                $forces['defenses'][$defense->slug][$defense->tier] = $defense->quantity;
            }
            foreach ($this->repo->all(PlanetBuilding::class, ['planet' => $planet, 'slug' => 'fortress']) as $building) {
                $forces['fortress'][$building->tier] = $building->level;
            }
        }
        return $forces;
    }

    public function intel(PlayerUniverse $owner, int $reportId): array
    {
        $report = $this->reports->findVisible($owner, $reportId);
        if (!$report || $report->sender->id !== $owner->id || $report->sender->universe->id !== $owner->universe->id) {
            throw new \DomainException('Rapport inaccessible dans cet univers.');
        }
        // Une catégorie absente reste inconnue ; une catégorie présente et vide confirme l’absence de forces.
        $forces = [];
        foreach (['fleets' => 'ships', 'defenses' => 'defenses', 'research' => 'research'] as $category => $target) {
            if (!array_key_exists($category, $report->snapshot)) {
                continue;
            }
            $forces[$target] = [];
            foreach ($report->snapshot[$category] as $slug => $entry) {
                // Les anciens rapports sans rang restent lisibles à côté du format actuel par rang.
                if (isset($entry['quantity']) || isset($entry['level'])) {
                    [$type, $tier] = array_pad(explode(':', $slug, 2), 2, 'basic');
                    $forces[$target][$type][$tier] = (int) ($entry[$target === 'research' ? 'level' : 'quantity'] ?? 0);
                } else {
                    foreach ($entry as $tier => $values) {
                        $forces[$target][$slug][$tier] = (int) ($values[$target === 'research' ? 'level' : 'quantity'] ?? 0);
                    }
                }
            }
        }
        if (array_key_exists('buildings', $report->snapshot)) {
            $forces['fortress'] = [];
        }
        if (isset($report->snapshot['buildings']['fortress'])) {
            $fortress = $report->snapshot['buildings']['fortress'];
            if (isset($fortress['level'])) {
                $forces['fortress']['basic'] = (int) $fortress['level'];
            } else {
                foreach ($fortress as $tier => $entry) {
                    $forces['fortress'][$tier] = (int) ($entry['level'] ?? 0);
                }
            }
        }
        return [
            'forces' => $forces,
            'date' => $report->createdAt,
            'targetName' => $report->target->name,
            'id' => $report->id,
            'known' => array_keys($forces),
        ];
    }

    public function provenance(PlayerUniverse $owner, array $forces, array $intel, string $camp = 'defender'): array
    {
        $result = [];
        $choices = $this->choices($owner);
        foreach ([
            'ships',
            'research',
            'defenses',
            'fortress',
        ] as $group) {
            if (!array_key_exists($group, $intel['forces'])) {
                $result[$group] = 'Inconnu dans le rapport · hypothèse de saisie';
                continue;
            }
            $before = $intel['forces'][$group];
            $after = $forces[$camp][$group] ?? [];
            if ($group === 'research') {
                $before = array_intersect_key($before, $choices['research']);
            }
            $result[$group] = $this->canonical($before) === $this->canonical(is_array($after) ? $after : []) ? 'Rapport daté · valeurs reprises' : 'Hypothèse modifiée depuis le rapport';
        }
        return $result;
    }

    /**
     * Projette les champs non révélés laissés vides en zéros pour cet essai,
     * uniquement après confirmation. Les valeurs invalides restent au moteur.
     */
    public function withConfirmedUnknownAssumptions(array $forces, ?array $intel, array $choices, string $camp = 'defender'): array
    {
        if ($intel === null || !is_array($forces[$camp] ?? null)) {
            return $forces;
        }

        foreach (['ships', 'defenses', 'research', 'fortress'] as $group) {
            if (array_key_exists($group, $intel['forces']) || !is_array($forces[$camp][$group] ?? null)) {
                continue;
            }
            if ($group === 'fortress') {
                foreach (GameCatalog::TIERS as $tier) {
                    if (($forces[$camp][$group][$tier] ?? null) === '') {
                        $forces[$camp][$group][$tier] = 0;
                    }
                }
                continue;
            }
            foreach ($forces[$camp][$group] as $slug => $tiers) {
                if (!isset($choices[$group][$slug]) || !is_array($tiers)) {
                    continue;
                }
                foreach (GameCatalog::TIERS as $tier) {
                    if (($tiers[$tier] ?? null) === '') {
                        $forces[$camp][$group][$slug][$tier] = 0;
                    }
                }
            }
        }

        return $forces;
    }

    private function canonical(array $values): array
    {
        // Normaliser les zéros et l’ordre des clés évite une fausse modification due au seul format de saisie.
        $result = [];
        foreach ($values as $key => $value) {
            $normalized = is_array($value) ? $this->canonical($value) : (is_numeric($value) ? (int) $value : $value);
            if ($normalized !== [] && $normalized !== 0 && $normalized !== '0' && $normalized !== '') {
                $result[$key] = $normalized;
            }
        }
        ksort($result);
        return $result;
    }

    private function values(mixed $input, array $definitions, ?int $quantityLimit = null): array
    {
        if (!is_array($input)) {
            throw new \DomainException('Forces invalides.');
        }
        $result = [];
        foreach ($input as $slug => $tiers) {
            if (!isset($definitions[$slug]) || !is_array($tiers)) {
                throw new \DomainException('Type ou recherche inconnu.');
            }
            foreach ($tiers as $tier => $value) {
                $n = filter_var($value, FILTER_VALIDATE_INT);
                $max = $quantityLimit ?? (int) ($definitions[$slug]['max_level'] ?? 25);
                if (!in_array($tier, GameCatalog::TIERS, true) || $n === false || $n < 0 || $n > $max) {
                    throw new \DomainException('Rang, niveau ou quantité invalide.');
                }
                if ($n) {
                    $result[$slug][$tier] = $n;
                }
            }
        }
        return $result;
    }

    public function runBatch(PlayerUniverse $owner, array $forces, int $seed): array
    {
        return $this->runPreparedInline($this->prepare($owner, $forces, $seed), $seed);
    }

    public function runPreparedInline(array $scenario, int $seed): array
    {
        if (!$scenario['inline']) {
            throw new \DomainException('Ce scénario doit être calculé en arrière-plan.');
        }
        $trials = [];
        for ($index = 0; $index < self::TRIALS; ++$index) {
            $trials[] = $this->runTrial($scenario, $seed, $index);
        }

        return $this->batch($trials);
    }

    /** La série et son ordre restent les mêmes, que les essais soient calculés en ligne ou en file. */
    public function runTrial(array $scenario, int $seed, int $index): array
    {
        if ($index < 0 || $index >= self::TRIALS) {
            throw new \InvalidArgumentException('Indice de simulation invalide.');
        }
        $trial = $this->simulate($scenario, ($seed + $index) % 0x100000000);
        if (isset($trial['rounds'][0]['total_shots'])) {
            $trial['grouped_survivors'] = true;
        }
        $trial['rounds'] = array_map(static function (array $round): array {
            if (isset($round['total_shots'])) {
                // Le moteur compact fournit des décomptes exacts sans stocker un objet par tir.
                return [
                    'number' => $round['number'],
                    'shots' => $round['total_shots'],
                    'hits' => $round['hits'],
                    'destroyed' => $round['destroyed'],
                ];
            }

            return [
                'number' => $round['number'],
                'shots' => count($round['shots']),
                'hits' => count(array_filter($round['shots'], static fn(array $shot): bool => $shot['hit'])),
                'destroyed' => count(array_filter($round['shots'], static fn(array $shot): bool => $shot['destroyed'])),
            ];
        }, $trial['rounds']);

        return $trial;
    }

    public function batch(array $trials): array
    {
        if (count($trials) !== self::TRIALS) {
            throw new \LogicException('La simulation doit contenir dix essais terminés.');
        }
        $outcomes = ['attacker_wins' => 0, 'defender_wins' => 0, 'draw' => 0, 'ongoing' => 0];
        foreach ($trials as $trial) {
            ++$outcomes[$trial['outcome']];
        }

        return [
            'count' => self::TRIALS,
            'outcomes' => $outcomes,
            'win_percent' => intdiv($outcomes['attacker_wins'] * 100, self::TRIALS),
            'trials' => $trials,
        ];
    }

    public function run(PlayerUniverse $owner, array $forces, int $seed): array
    {
        return $this->simulate($this->prepare($owner, $forces, $seed), $seed);
    }

    private function simulate(array $scenario, int $seed): array
    {
        return $this->simulator->simulate(
            $scenario['participants'],
            $scenario['defenses'],
            $scenario['modifiers'],
            $scenario['rounds'],
            $seed,
            null,
            $scenario['recharge'],
            $scenario['restore_shields'],
        );
    }

    private function estimatedWeight(array $unit): int
    {
        return $unit['quantity'] * (($unit['shots_max'] ?? 1) + ($unit['secondary_shots_max'] ?? 0))
            * (1 + count($unit['extra_shot_chances'] ?? []));
    }

    /**
     * Fige les statistiques avant l’envoi : une modification de catalogue ou de recherche
     * entre deux essais ne doit pas changer la série. Aucun identifiant réel de flotte n’est requis.
     * @return array{participants: list<array>, defenses: list<array>, modifiers: array, rounds: int, recharge: float, restore_shields: bool, inline: bool, grouped_survivors: bool, forces: array}
     */
    public function prepare(PlayerUniverse $owner, array $forces, int $seed, ?PirateBattleScenario $pirate = null): array
    {
        if (!$owner->isPlayerControlled() || $seed < 0 || $seed > 0xffffffff) {
            throw new \DomainException('Paramètres de simulation invalides.');
        }
        if (array_diff_key($forces, ['attacker' => true, 'defender' => true]) !== []) {
            throw new \DomainException('Une simulation oppose une flotte attaquante à une flotte défensive.');
        }
        $choices = $this->choices($owner);
        $fleetLimit = $this->fleetLimit($owner);
        $rules = $this->catalog->rules();
        $pirateSnapshot = $pirate?->snapshot();
        $rounds = $pirateSnapshot['rounds'] ?? (int) $rules['max_combat_rounds'];
        $participants = [];
        $defenses = [];
        $normalizedForces = [];
        $total = 0;
        $weight = 0;
        foreach (['attacker', 'defender'] as $camp) {
            $input = $forces[$camp] ?? [];
            if ($camp === 'defender' && $pirateSnapshot !== null) {
                // Le formulaire n'est jamais la source du camp pirate. Même si le catalogue a changé,
                // conserver les profils, effets, ordre et réglages utilisés par le vrai combat.
                $ships = $pirateSnapshot['units'];
                $composition = [];
                foreach ($ships as $ship) {
                    $composition[$ship['slug']][$ship['tier']] = $ship['quantity'];
                }
                $participants[] = ['id' => 'defender', 'camp' => 'defender', 'role' => 'defender', 'arrival_round' => 1, 'units' => $ships];
                $normalizedForces['defender'] = ['ships' => $composition, 'research' => [], 'arrival' => 1, 'fortress' => [], 'defenses' => []];
                foreach ($ships as $unit) {
                    $total += $unit['quantity'];
                    $weight += $this->estimatedWeight($unit);
                }
                continue;
            }
            if (!is_array($input) || array_diff_key($input, ['ships' => true, 'research' => true, 'arrival' => true, 'defenses' => true, 'fortress' => true]) !== []) {
                throw new \DomainException('Forces invalides.');
            }
            $research = $this->values($input['research'] ?? [], $choices['research']);
            $composition = $this->values($input['ships'] ?? [], $choices['ships'], $fleetLimit);
            if (array_sum(array_map('array_sum', $composition)) > $fleetLimit) {
                throw new \DomainException('Chaque flotte doit respecter la limite de cet univers. Aucune quantité n’a été tronquée.');
            }
            if ($camp === 'attacker') {
                if (!empty($input['defenses']) || !empty($input['fortress'])) {
                    throw new \DomainException('Les défenses fixes s’appliquent uniquement à la colonie défendue.');
                }
                foreach ($composition as $slug => $tiers) {
                    if (isset($choices['ships'][$slug]['economic_unit'])) {
                        throw new \DomainException('Les unités économiques participent uniquement à la défense de leur colonie.');
                    }
                }
            }
            $ships = $this->units->ships($owner->universe, $composition, $research);
            if ($pirate !== null) {
                if ($ships === []) {
                    throw new \DomainException('Ajoutez au moins un vaisseau à l’escorte.');
                }
                // L'attaque réelle ne connaît ni renfort ni arrivée différée.
                $pirate->assertNativeUnitLimit($ships);
                // La tentative PvE fige aussi l’ordre des unités de l’escorte avant le calcul.
                $ships = PirateBattleScenario::freezeUnits($ships);
            }
            $arrival = $pirate !== null ? 1 : filter_var($input['arrival'] ?? 1, FILTER_VALIDATE_INT);
            if ($arrival === false || $arrival < 1 || $arrival > $rounds) {
                throw new \DomainException('Round d’arrivée invalide.');
            }
            $normalizedForces[$camp] = ['ships' => $composition, 'research' => $research, 'arrival' => $arrival];
            if ($ships !== []) {
                $participants[] = [
                    'id' => $camp,
                    'camp' => $camp,
                    'role' => $camp,
                    'arrival_round' => $arrival,
                    'units' => $ships,
                ];
            }
            $all = $ships;
            if ($camp === 'defender') {
                $fortress = $this->values(
                    ['fortress' => $input['fortress'] ?? []],
                    ['fortress' => $this->catalog->getForUniverse($owner->universe, 'buildings', 'fortress')],
                )['fortress'] ?? [];
                $composition = $this->values($input['defenses'] ?? [], $choices['defenses'], self::MAX_DEFENSE_QUANTITY);
                $normalizedForces[$camp]['fortress'] = $fortress;
                $normalizedForces[$camp]['defenses'] = $composition;
                foreach ($composition as $slug => $tiers) {
                    foreach ($tiers as $tier => $count) {
                        $definition = $this->units->defense($owner->universe, $slug, $tier, $research, $fortress) + [
                            'quantity' => $count,
                            'tier' => $tier,
                            'camp' => 'defender',
                            'id' => $slug.'-'.$tier,
                        ];
                        $defenses[] = $definition;
                        $all[] = $definition;
                    }
                }
            }
            foreach ($all as $unit) {
                $total += $unit['quantity'];
                $weight += $this->estimatedWeight($unit);
            }
        }
        if ($total > 0xffffffff) {
            throw new \DomainException('Les effectifs cumulés dépassent le format du moteur. Aucune quantité n’a été tronquée.');
        }
        if ($total < 1) {
            throw new \DomainException('Ajoutez au moins une unité à la simulation.');
        }

        return [
            'participants' => $participants,
            'defenses' => $defenses,
            'modifiers' => $pirateSnapshot['modifiers'] ?? $this->catalog->all('combat_modifiers')['modifiers'] ?? [],
            'rounds' => $rounds,
            'recharge' => $pirateSnapshot['recharge'] ?? (float) ($rules['shield_recharge_percent_per_round'] ?? 5),
            'restore_shields' => $pirate !== null || (bool) ($rules['restore_survivor_shields_after_combat'] ?? true),
            'inline' => $total <= self::INLINE_UNITS && $total * $weight * $rounds <= self::INLINE_WORK,
            'grouped_survivors' => $total > self::INLINE_UNITS,
            'forces' => $normalizedForces,
        ];
    }
}
