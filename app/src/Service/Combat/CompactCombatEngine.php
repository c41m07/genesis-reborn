<?php

namespace App\Service\Combat;

/**
 * Adaptateur du moteur exact à représentation compacte. Symfony reste seul
 * responsable des autorisations, de l'instantané métier et de la transaction.
 */
final class CompactCombatEngine
{
    public const CHECKPOINT_VERSION = 2;
    private const GROUP_LIMIT = 4096;
    private const UNIT_INDEX_LIMIT = 0xfffffffe;
    private const COMPUTE_TIMEOUT_SECONDS = 180;

    public function __construct(private readonly string $binary = '/usr/local/bin/genesis-combat-engine')
    {
    }

    public function simulate(
        array $participants,
        array $stationaryDefenses,
        array $modifiers,
        int $maxRounds,
        int $seed,
        ?int $roundLimit = null,
        float $shieldRechargePercent = 5.0,
        bool $restoreSurvivorShields = true,
        bool $independentCamps = false,
    ): array {
        return $this->run($participants, $stationaryDefenses, $modifiers, $maxRounds, $seed, $roundLimit,
            $shieldRechargePercent, $restoreSurvivorShields, $independentCamps, false, null);
    }

    /**
     * Le format 1 est importé une fois si un renfort fait passer un combat sur
     * le moteur compact. Le format 2 garde un état opaque et compressé.
     *
     * @param array{version: int, completed_round: int, payload?: string, random?: array, durability?: array}|null $checkpoint
     */
    public function advance(
        array $participants,
        array $stationaryDefenses,
        array $modifiers,
        int $maxRounds,
        int $seed,
        int $roundLimit,
        float $shieldRechargePercent,
        bool $restoreSurvivorShields,
        bool $independentCamps,
        ?array $checkpoint = null,
    ): array {
        return $this->run($participants, $stationaryDefenses, $modifiers, $maxRounds, $seed, $roundLimit,
            $shieldRechargePercent, $restoreSurvivorShields, $independentCamps, true, $checkpoint);
    }

    private function run(
        array $participants,
        array $stationaryDefenses,
        array $modifiers,
        int $maxRounds,
        int $seed,
        ?int $roundLimit,
        float $shieldRechargePercent,
        bool $restoreSurvivorShields,
        bool $independentCamps,
        bool $saveCheckpoint,
        ?array $checkpoint,
    ): array {
        if ($maxRounds < 1 || $maxRounds > 100) {
            throw new \DomainException('Nombre de rounds invalide.');
        }
        if ($seed < 0 || $seed > 0xffffffff) {
            throw new \DomainException('Graine de combat invalide.');
        }
        if (!is_finite($shieldRechargePercent) || $shieldRechargePercent < 0 || $shieldRechargePercent > 100) {
            throw new \DomainException('Pourcentage de recharge des boucliers invalide.');
        }
        $roundLimit = max(1, min($maxRounds, $roundLimit ?? $maxRounds));
        $groups = $this->groups($participants, $stationaryDefenses, $maxRounds);
        $owners = $this->labels($groups, 'owner');
        $scoreOwners = ['', ...array_values(array_filter($this->labels($groups, 'score_owner'), static fn (string $label): bool => $label !== ''))];
        $camps = $this->labels($groups, 'camp');
        $categories = array_values(array_unique([...$this->labels($groups, 'category'), ...$this->labels($groups, 'second_category')]));
        $this->validateModifiers($modifiers);

        $existingRound = $checkpoint['completed_round'] ?? 0;
        $checkpointVersion = $checkpoint['version'] ?? null;
        if ($checkpoint !== null && (!in_array($checkpointVersion, [1, self::CHECKPOINT_VERSION], true)
                || !is_int($existingRound) || $existingRound < 1 || $existingRound >= $roundLimit)) {
            throw new \LogicException('Point de reprise du combat incompatible.');
        }
        $legacy = $checkpointVersion === 1 ? $this->legacy($checkpoint, $groups) : null;
        $input = tmpfile();
        $output = tmpfile();
        $errors = tmpfile();
        $readPath = $writePath = '-';
        try {
            if ($input === false || $output === false || $errors === false) {
                throw new \RuntimeException('Fichiers temporaires du moteur de combat indisponibles.');
            }
            if ($checkpointVersion === self::CHECKPOINT_VERSION) {
                $bytes = is_string($checkpoint['payload'] ?? null) ? base64_decode($checkpoint['payload'], true) : false;
                if ($bytes === false || !str_starts_with($bytes, "\x1f\x8b")) {
                    throw new \LogicException('Données du point de reprise du combat incompatibles.');
                }
                $readPath = $this->temporaryPath();
                if (file_put_contents($readPath, $bytes) !== strlen($bytes)) {
                    throw new \RuntimeException('Impossible de préparer la reprise du combat.');
                }
                unset($bytes);
            }
            if ($saveCheckpoint) {
                $writePath = $this->temporaryPath();
            }
            $this->write($input, ['GR-COMBAT-2', $seed, $maxRounds, $roundLimit, $shieldRechargePercent,
                (int) $independentCamps, (int) $restoreSurvivorShields, count($groups), count($owners), count($scoreOwners) - 1,
                $legacy === null ? 0 : $existingRound]);
            foreach ($groups as $group) {
                $this->write($input, [
                    bin2hex($group['identity']),
                    array_search($group['owner'], $owners, true),
                    array_search($group['score_owner'], $scoreOwners, true),
                    $group['role'] === 'attacker' ? 0 : ($group['role'] === 'defender' ? 1 : 2),
                    array_search($group['camp'], $camps, true),
                    array_search($group['category'], $categories, true),
                    match ($group['tier']) {'intermediate' => 2, 'superior' => 3, default => 1},
                    $group['arrival'], $group['quantity'], $group['hp'], $group['shield'], $group['attack'],
                    $group['precision'], $group['tracking'], $group['evasion'], $group['weight'], $group['shots_min'], $group['shots_max'],
                    $group['second_attack'], $group['second_min'], $group['second_max'], $group['second_precision'],
                    array_search($group['second_category'], $categories, true), count($group['extras']), ...$group['extras'],
                ]);
            }
            foreach ($groups as $source) {
                $relations = $modifiers[$source['slug']] ?? [];
                $this->write($input, array_map(
                    static fn (array $target): float => (float) ($relations[$target['slug']] ??
                        ($target['role'] === 'static_defense' ? ($relations['ground_defense'] ?? 1.0) : 1.0)),
                    $groups,
                ));
            }
            if ($legacy !== null) {
                $this->write($input, $legacy['random']);
                $this->write($input, [count($legacy['durability'])]);
                foreach ($legacy['durability'] as $unit) {
                    $this->write($input, $unit);
                }
            }
            rewind($input);
            $this->execute([$this->binary, $readPath, $writePath], $input, $output, $errors);
            rewind($output);
            $result = $this->result($output, $groups, $owners, $scoreOwners, $camps, $seed, $existingRound, $roundLimit, $independentCamps);
            if ($saveCheckpoint && $result['outcome'] === 'ongoing') {
                $bytes = file_get_contents($writePath);
                if ($bytes === false || !str_starts_with($bytes, "\x1f\x8b")) {
                    throw new \RuntimeException('Point de reprise non produit par le moteur de combat.');
                }
                $result['checkpoint'] = [
                    'version' => self::CHECKPOINT_VERSION,
                    'completed_round' => $result['rounds_played'],
                    'payload' => base64_encode($bytes),
                ];
            }

            return $result;
        } finally {
            foreach ([$input, $output, $errors] as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            foreach ([$readPath, $writePath] as $path) {
                if ($path !== '-') {
                    unlink($path);
                }
            }
        }
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'genesis-combat-');
        if ($path === false) {
            throw new \RuntimeException('Fichiers temporaires du moteur de combat indisponibles.');
        }

        return $path;
    }

    private function write(mixed $stream, array $fields): void
    {
        // JSON fixe le point décimal et préserve les doubles sur tous les
        // réglages régionaux, sans accepter NAN ni INF dans le protocole.
        $line = implode(' ', array_map(
            static fn ($value): string => is_float($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value,
            $fields,
        )) . "\n";
        if (fwrite($stream, $line) !== strlen($line)) {
            throw new \RuntimeException('Impossible de préparer les données du combat.');
        }
    }

    private function execute(array $command, mixed $input, mixed $output, mixed $errors): void
    {
        if (!is_executable($this->binary)) {
            throw new \RuntimeException('Moteur de combat compact indisponible.');
        }
        // Arguments séparés et descripteurs locaux, pas de shell. Les fichiers
        // empêchent qu'un enfant se bloque sur un tampon de sortie plein.
        $process = proc_open($command, [$input, $output, $errors], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Impossible de lancer le moteur de combat.');
        }
        $deadline = hrtime(true) + self::COMPUTE_TIMEOUT_SECONDS * 1_000_000_000;
        try {
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (hrtime(true) >= $deadline) {
                    proc_terminate($process, 9);
                    throw new CombatCapacityExceeded('Le calcul exact du combat a dépassé sa durée maximale.');
                }
                usleep(20_000);
            } while (true);
        } finally {
            proc_close($process);
        }
        if ($status['exitcode'] === 2) {
            throw new CombatCapacityExceeded('Le calcul exact du combat dépasse la mémoire disponible.');
        }
        if ($status['exitcode'] !== 0) {
            // La sortie détaillée du processus reste locale et peut contenir
            // des informations liées au calcul; elle n'est pas rendue à l'UI.
            throw new \RuntimeException('Le moteur de combat n’a pas terminé son calcul.');
        }
    }

    private function labels(array $groups, string $column): array
    {
        return array_values(array_unique(array_column($groups, $column), SORT_STRING));
    }

    private function groups(array $participants, array $defenses, int $maxRounds): array
    {
        usort($participants, static fn (array $a, array $b): int => strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? '')));
        usort($defenses, static fn (array $a, array $b): int => strcmp((string) ($a['id'] ?? $a['slug'] ?? ''), (string) ($b['id'] ?? $b['slug'] ?? '')));
        $groups = [];
        foreach ($participants as $participant) {
            $role = $participant['role'] ?? '';
            $arrival = $participant['arrival_round'] ?? null;
            if (!in_array($role, ['attacker', 'defender'], true) || !isset($participant['id'], $participant['camp'])
                || !is_int($arrival) || $arrival < 1 || $arrival > $maxRounds) {
                throw new \DomainException('Participant de combat invalide.');
            }
            $definitions = $participant['units'] ?? [];
            usort($definitions, static fn (array $a, array $b): int => strcmp((string) ($a['slug'] ?? ''), (string) ($b['slug'] ?? '')));
            foreach ($definitions as $definition) {
                $groups[] = $this->group($definition, (string) $participant['id'], $role, (string) $participant['camp'],
                    $arrival, (string) ($participant['score_owner'] ?? ''));
            }
        }
        foreach ($defenses as $definition) {
            if (!isset($definition['id'], $definition['camp'])) {
                throw new \DomainException('Défense statique invalide.');
            }
            $groups[] = $this->group($definition, 'planet:' . $definition['id'], 'static_defense', (string) $definition['camp'], 1, '');
        }
        if ($groups === []) {
            throw new \DomainException('Un combat doit contenir au moins une unité.');
        }
        if (count($groups) > self::GROUP_LIMIT || array_sum(array_column($groups, 'quantity')) > self::UNIT_INDEX_LIMIT) {
            throw new CombatCapacityExceeded('Le combat dépasse la capacité d’indexation du moteur.');
        }
        if (count(array_unique(array_column($groups, 'identity'))) !== count($groups)) {
            throw new \DomainException('Identifiant d’unité de combat dupliqué.');
        }

        return $groups;
    }

    private function group(array $definition, string $owner, string $role, string $camp, int $arrival, string $scoreOwner): array
    {
        $slug = $definition['slug'] ?? '';
        $quantity = $definition['quantity'] ?? null;
        if (!is_string($slug) || $slug === '' || !is_int($quantity) || $quantity < 1) {
            throw new \DomainException('Quantité ou type d’unité de combat invalide.');
        }
        $stats = [];
        foreach (['hp', 'precision', 'tracking', 'evasion', 'signature', 'base_attack', 'shield'] as $stat) {
            $value = $definition[$stat] ?? ($stat === 'shield' ? 0 : null);
            if (!is_numeric($value) || !is_finite((float) $value) || $value < 0 || ($stat === 'hp' && $value <= 0)) {
                throw new \DomainException('Statistique de combat invalide : ' . $stat . '.');
            }
            $stats[$stat] = (float) $value;
        }
        $shotsMin = $definition['shots_min'] ?? null;
        $shotsMax = $definition['shots_max'] ?? null;
        if (!is_int($shotsMin) || !is_int($shotsMax) || $shotsMin < ($stats['base_attack'] > 0 ? 1 : 0)
            || $shotsMax < $shotsMin || $shotsMax > 100) {
            throw new \DomainException('Nombre de tirs invalide.');
        }
        $secondAttack = $definition['secondary_attack'] ?? 0;
        $secondMin = $definition['secondary_shots_min'] ?? 0;
        $secondMax = $definition['secondary_shots_max'] ?? $secondMin;
        $secondPrecision = $definition['secondary_precision_modifier'] ?? 0;
        $secondCategory = $definition['secondary_target_category'] ?? '';
        if (isset($definition['secondary_attack']) &&
            (!is_numeric($secondAttack) || !is_finite((float) $secondAttack) || $secondAttack < 0
                || !is_int($secondMin) || !is_int($secondMax) || $secondMin < 0 || $secondMax < $secondMin || $secondMax > 100
                || !is_int($secondPrecision) || !is_string($secondCategory) || $secondCategory === '')) {
            throw new \DomainException('Arme secondaire invalide.');
        }
        if (!isset($definition['secondary_attack'])) {
            [$secondAttack, $secondMin, $secondMax, $secondPrecision, $secondCategory] = [0, 0, 0, 0, ''];
        }
        $extras = [];
        foreach ($definition['extra_shot_chances'] ?? [] as $chance) {
            if (!is_numeric($chance) || !is_finite((float) $chance) || $chance < 0 || $chance > 1) {
                throw new \DomainException('Chance de tir supplémentaire invalide.');
            }
            $extras[] = (float) $chance;
        }
        $weight = round(max(1.0, $stats['signature']) * 100);
        if ($weight >= PHP_INT_MAX) {
            throw new \DomainException('Poids de signature trop élevé.');
        }
        $tier = (string) ($definition['tier'] ?? 'basic');
        $suffix = $tier === 'basic' ? '' : ':' . $tier;

        return $stats + [
            'owner' => $owner,
            'score_owner' => $scoreOwner,
            'camp' => $camp,
            'role' => $role,
            'slug' => $slug,
            'tier' => $tier,
            'identity' => $owner . ':' . $role . ':' . $slug . $suffix,
            'loss_key' => $owner . ':' . $slug . $suffix,
            'arrival' => $arrival,
            'quantity' => $quantity,
            'category' => (string) ($definition['category'] ?? ''),
            'attack' => $stats['base_attack'],
            'weight' => (int) $weight,
            'shots_min' => $shotsMin,
            'shots_max' => $shotsMax,
            'second_attack' => (float) $secondAttack,
            'second_min' => $secondMin,
            'second_max' => $secondMax,
            'second_precision' => $secondPrecision,
            'second_category' => $secondCategory,
            'extras' => $extras,
        ];
    }

    private function validateModifiers(array $modifiers): void
    {
        foreach ($modifiers as $values) {
            if (!is_array($values)) {
                throw new \DomainException('Matrice de combat invalide.');
            }
            foreach ($values as $value) {
                if (!is_numeric($value) || !is_finite((float) $value) || $value <= 0 || $value > 10) {
                    throw new \DomainException('Coefficient de contre invalide.');
                }
            }
        }
    }

    private function legacy(array $checkpoint, array $groups): array
    {
        $savedState = $checkpoint['random'][1] ?? null;
        if (!is_array($savedState) || !array_is_list($savedState) || count($savedState) !== 626
            || !is_int($savedState[624]) || $savedState[624] < 0 || $savedState[624] > 624
            || $savedState[625] !== MT_RAND_MT19937 || !is_array($checkpoint['durability'] ?? null)) {
            throw new \LogicException('Générateur du point de reprise du combat incompatible.');
        }
        $random = [];
        foreach (array_slice($savedState, 0, 624) as $word) {
            if (!is_string($word) || strlen($word) !== 8 || !ctype_xdigit($word)) {
                throw new \LogicException('Générateur du point de reprise du combat incompatible.');
            }
            $random[] = unpack('V', hex2bin($word))[1];
        }
        $random[] = $savedState[624];
        $byIdentity = array_flip(array_column($groups, 'identity'));
        $durability = [];
        foreach ($checkpoint['durability'] as $key => $values) {
            $separator = strrpos((string) $key, ':');
            $identity = $separator === false ? '' : substr((string) $key, 0, $separator);
            $unit = $separator === false ? '' : substr((string) $key, $separator + 1);
            if (!isset($byIdentity[$identity]) || !ctype_digit($unit) || (int) $unit < 1
                || !is_array($values) || !array_is_list($values) || count($values) !== 2
                || (!is_int($values[0]) && !is_float($values[0])) || (!is_int($values[1]) && !is_float($values[1]))) {
                throw new \LogicException('Durabilité du point de reprise du combat incompatible.');
            }
            $durability[] = [$byIdentity[$identity], (int) $unit - 1, (float) $values[0], (float) $values[1]];
        }

        return ['random' => $random, 'durability' => $durability];
    }

    private function result(
        mixed $output,
        array $groups,
        array $owners,
        array $scoreOwners,
        array $camps,
        int $seed,
        int $existingRound,
        int $roundLimit,
        bool $independent,
    ): array {
        $rounds = [];
        $survivors = [];
        $losses = [];
        $finished = false;
        $seenGroups = [];
        while (($line = fgets($output)) !== false) {
            $fields = explode(' ', rtrim($line, "\r\n"));
            $type = array_shift($fields);
            $index = (int) ($fields[0] ?? -1);
            if ($type === 'R' && !$finished && $index === $existingRound + count($rounds) + 1 && $index <= $roundLimit) {
                $rounds[$index] = ['number' => $index, 'groups' => [], 'damage_by_owner' => [], 'damage_by_score_owner' => [],
                    'total_shots' => 0, 'hits' => 0, 'destroyed' => 0];
                $round = &$rounds[$index];
            } elseif ($type === 'G' && !$finished && isset($round, $groups[$index]) && count($fields) === 8) {
                $group = $groups[$index];
                $row = [
                    'owner' => $group['owner'], 'slug' => $group['slug'], 'tier' => $group['tier'], 'shots' => (int) $fields[1], 'hits' => (int) $fields[2], 'lost' => (int) $fields[3],
                    'damage' => (float) $fields[4], 'shield_damage' => (float) $fields[5], 'structure_damage' => (float) $fields[6], 'targeted' => (int) $fields[7],
                ];
                $round['groups'][$group['loss_key']] = $row;
                $round['total_shots'] += $row['shots'];
                $round['hits'] += $row['hits'];
                $round['destroyed'] += $row['lost'];
            } elseif ($type === 'D' && !$finished && isset($round, $owners[$index]) && count($fields) === 2) {
                $round['damage_by_owner'][$owners[$index]] = (float) $fields[1];
            } elseif ($type === 'M' && !$finished && isset($round, $scoreOwners[$index]) && $index > 0 && count($fields) === 2) {
                $round['damage_by_score_owner'][$scoreOwners[$index]] = (float) $fields[1];
            } elseif ($type === 'E' && !$finished && isset($round) && count($fields) === 3 && $index === $round['number']
                && in_array($fields[1], ['ongoing', 'draw', 'attacker_wins', 'defender_wins'], true)) {
                $finished = true;
                $roundsPlayed = $index;
                $outcome = $fields[1];
                $winner = $camps[(int) $fields[2]] ?? null;
            } elseif ($type === 'V' && $finished && isset($groups[$index]) && !isset($seenGroups[$index]) && count($fields) === 6) {
                $group = $groups[$index];
                $alive = (int) $fields[1];
                if ($alive < 0 || $alive > $group['quantity']) {
                    throw new \RuntimeException('Quantités du résultat de combat incohérentes.');
                }
                if ($alive > 0) {
                    $survivors[] = array_intersect_key($group, array_flip(['owner', 'role', 'camp', 'slug', 'tier'])) + [
                        'quantity' => $alive, 'hp_min' => (float) $fields[2], 'hp_max' => (float) $fields[3],
                        'shield_min' => (float) $fields[4], 'shield_max' => (float) $fields[5],
                    ];
                }
                $lost = $group['quantity'] - $alive;
                if ($lost > 0) {
                    $losses[$group['loss_key']] = ($losses[$group['loss_key']] ?? 0) + $lost;
                }
                $seenGroups[$index] = true;
            } else {
                throw new \RuntimeException('Résultat du moteur de combat incompatible.');
            }
        }
        unset($round);
        if (!$finished || count($seenGroups) !== count($groups)) {
            throw new \RuntimeException('Résultat du moteur de combat incomplet.');
        }
        ksort($losses);
        $result = ['seed' => $seed, 'outcome' => $outcome, 'rounds_played' => $roundsPlayed, 'rounds' => array_values($rounds),
            'survivors' => $survivors, 'losses' => $losses];
        if ($independent) {
            $result['winner_camp'] = $winner;
        }

        return $result;
    }
}
