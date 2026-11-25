<?php

namespace App\Service\Combat;

use App\Entity\{Planet, PlayerTechnology, PlayerUniverse, Fleet, FleetShip};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\EspionageReportReader;

final class PlayerCombatSimulation
{
    public const MAX_UNITS = CombatSimulator::MAX_UNITS;

    // Chaque série contient dix combats : le plafond borne le coût des tirs, pas seulement le nombre d’unités.
    private const MAX_POTENTIAL_SHOTS_PER_TRIAL = 60000;
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

    public function own(PlayerUniverse $owner, int $planetId): array
    {
        $planet = $this->repo->find(Planet::class, $planetId);
        if (!$planet || $planet->owner?->id !== $owner->id || $planet->solarSystem->galaxy->universe->id !== $owner->universe->id) {
            throw new \DomainException('Colonie inaccessible.');
        }
        $ships = [];
        $definitions = $this->choices($owner)['ships'];
        $fleets = $this->repo->all(Fleet::class, ['playerUniverse' => $owner, 'homePlanet' => $planet, 'status' => 'idle']);
        foreach ($fleets === [] ? [] : $this->repo->all(FleetShip::class, ['fleet' => $fleets]) as $ship) {
            if ($ship->quantity <= 0 || isset($definitions[$ship->slug]['economic_unit'])) {
                continue;
            }
            $ships[$ship->slug][$ship->tier] = ($ships[$ship->slug][$ship->tier] ?? 0) + $ship->quantity;
        }
        return ['ships' => $ships, 'research' => $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $owner])];
    }

    public function intel(PlayerUniverse $owner, int $reportId): array
    {
        $report = $this->reports->findVisible($owner, $reportId);
        if (!$report || $report->sender->universe->id !== $owner->universe->id) {
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
            'id' => $report->id,
            'known' => array_keys($forces),
        ];
    }

    public function provenance(PlayerUniverse $owner, array $forces, array $intel): array
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
            $after = $forces['defender'][$group] ?? [];
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
    public function withConfirmedUnknownAssumptions(array $forces, ?array $intel, array $choices): array
    {
        if ($intel === null || !is_array($forces['defender'] ?? null)) {
            return $forces;
        }

        foreach (['ships', 'defenses', 'research', 'fortress'] as $group) {
            if (array_key_exists($group, $intel['forces']) || !is_array($forces['defender'][$group] ?? null)) {
                continue;
            }
            if ($group === 'fortress') {
                foreach (GameCatalog::TIERS as $tier) {
                    if (($forces['defender'][$group][$tier] ?? null) === '') {
                        $forces['defender'][$group][$tier] = 0;
                    }
                }
                continue;
            }
            foreach ($forces['defender'][$group] as $slug => $tiers) {
                if (!isset($choices[$group][$slug]) || !is_array($tiers)) {
                    continue;
                }
                foreach (GameCatalog::TIERS as $tier) {
                    if (($tiers[$tier] ?? null) === '') {
                        $forces['defender'][$group][$slug][$tier] = 0;
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

    private function values(mixed $input, array $definitions, bool $levels = false): array
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
                $max = $levels ? (int) ($definitions[$slug]['max_level'] ?? 25) : self::MAX_UNITS;
                if (!in_array($tier, GameCatalog::TIERS, true) || $n === false || $n < 0 || $n > $max) {
                    throw new \DomainException($levels
                        ? 'Rang ou niveau invalide.'
                        : sprintf('Rang ou quantité invalide. La simulation accepte au plus %d unités au total.', self::MAX_UNITS));
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
        $scenario = $this->prepareScenario($owner, $forces, $seed);
        $trials = [];
        $trialSeed = $seed;
        $outcomes = [
            'attacker_wins' => 0,
            'defender_wins' => 0,
            'draw' => 0,
            'ongoing' => 0,
        ];
        // Dériver les dix graines de celle saisie permet de rejouer la même série de simulations.
        for ($index = 0; $index < self::TRIALS; $index++) {
            $trial = $this->resolveScenario($scenario, $trialSeed, true);
            $trialSeed = ($trialSeed + 1) % 0x100000000;
            $outcomes[$trial['outcome']] = ($outcomes[$trial['outcome']] ?? 0) + 1;
            $trials[] = $trial;
        }
        return [
            'count' => self::TRIALS,
            'outcomes' => $outcomes,
            'win_percent' => intdiv($outcomes['attacker_wins'] * 100, self::TRIALS),
            'trials' => $trials,
        ];
    }

    /** Calcul de référence à un essai, avec le journal détaillé du moteur. */
    public function run(PlayerUniverse $owner, array $forces, int $seed): array
    {
        return $this->resolveScenario($this->prepareScenario($owner, $forces, $seed), $seed, false);
    }

    /** Les forces, bonus et limites ne changent pas entre les dix graines de la série. */
    private function prepareScenario(PlayerUniverse $owner, array $forces, int $seed): array
    {
        if (!$owner->isPlayerControlled() || $seed < 0 || $seed > 0xffffffff) {
            throw new \DomainException('Paramètres de simulation invalides.');
        }
        $choices = $this->choices($owner);
        $participants = [];
        $defenses = [];
        $total = 0;
        $potentialShots = 0;
        $rules = $this->catalog->rules();
        $rounds = (int) $rules['max_combat_rounds'];
        foreach (['attacker', 'defender'] as $camp) {
            $input = $forces[$camp] ?? [];
            if (!is_array($input)) {
                throw new \DomainException('Forces invalides.');
            }
            $research = $this->values($input['research'] ?? [], $choices['research'], true);
            $composition = $this->values($input['ships'] ?? [], $choices['ships']);
            if ($camp === 'attacker') {
                foreach ($composition as $slug=>$tiers) if (isset($choices['ships'][$slug]['economic_unit'])) {
                    throw new \DomainException('Les unités économiques participent uniquement à la défense de leur colonie.');
                }
            }
            $ships = $this->units->ships($owner->universe, $composition, $research);
            $arrival = filter_var($input['arrival'] ?? 1, FILTER_VALIDATE_INT);
            if ($arrival === false || $arrival < 1 || $arrival > $rounds) {
                throw new \DomainException('Round d’arrivée invalide.');
            }
            if ($ships) {
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
                $fortress = $this->values(['fortress' => $input['fortress'] ?? []], ['fortress' => $this->catalog->getForUniverse($owner->universe, 'buildings', 'fortress')], true)['fortress'] ?? [];
                foreach ($this->values($input['defenses'] ?? [], $choices['defenses']) as $slug => $tiers) {
                    foreach ($tiers as $tier => $count) {
                        $definition = $this->units->defense($owner->universe, $slug, $tier, $research, $fortress) + [
                            'quantity' => $count,
                            'tier' => $tier,
                            'camp' => 'defender',
                            'id' => $slug . '-' . $tier,
                        ];
                        $defenses[] = $definition;
                        $all[] = $definition;
                    }
                }
            }
            foreach ($all as $unit) {
                $total += $unit['quantity'];
                // Les unités sans arme principale ne jouent pas dans le moteur. Au-delà, compter
                // par prudence tous les tirs possibles de chaque arme et chaque tir supplémentaire.
                if (($unit['base_attack'] ?? 0) > 0) {
                    $potentialShots += $unit['quantity']
                        * (($unit['shots_max'] ?? 1) + ($unit['secondary_shots_max'] ?? 0))
                        * (1 + count($unit['extra_shot_chances'] ?? []));
                }
            }
        }
        if ($total < 1) {
            throw new \DomainException('Ajoutez au moins un vaisseau ou une défense pour simuler le combat.');
        }
        if ($total > self::MAX_UNITS) {
            throw new \DomainException(sprintf(
                'Scénario trop volumineux : %d unités saisies, pour un maximum de %d (vaisseaux et défenses cumulés). Aucune quantité n’a été tronquée.',
                $total,
                self::MAX_UNITS
            ));
        }
        if ($potentialShots * $rounds > self::MAX_POTENTIAL_SHOTS_PER_TRIAL) {
            throw new \DomainException('Ce scénario dépasse le budget de calcul à cause du nombre de tirs possibles, même si le total d’unités est accepté. Réduisez les forces ou les tirs supplémentaires. Aucune quantité n’a été tronquée.');
        }

        return [
            'participants' => $participants,
            'defenses' => $defenses,
            'modifiers' => $this->catalog->all('combat_modifiers')['modifiers'] ?? [],
            'rounds' => $rounds,
            'recharge' => (float) ($rules['shield_recharge_percent_per_round'] ?? 5),
            'restore_shields' => (bool) ($rules['restore_survivor_shields_after_combat'] ?? true),
        ];
    }

    private function resolveScenario(array $scenario, int $seed, bool $playerPreview): array
    {
        return $this->simulator->simulate(
            $scenario['participants'],
            $scenario['defenses'],
            $scenario['modifiers'],
            $scenario['rounds'],
            $seed,
            shieldRechargePercent: $scenario['recharge'],
            restoreSurvivorShields: $scenario['restore_shields'],
            playerPreview: $playerPreview
        );
    }
}
