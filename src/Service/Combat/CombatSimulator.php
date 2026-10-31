<?php
namespace App\Service\Combat;

use Random\Engine\Mt19937;
use Random\Randomizer;

/** Combat déterministe sur un instantané validé par les services métier. */
final class CombatSimulator
{
    private const MAX_UNITS = 5000;

    /**
     * Les participants doivent déjà être regroupés dans leurs camps par l'appelant.
     * Chaque entrée de participant contient id, role, camp, arrival_round et units.
     */
    public function simulate(array $participants, array $stationaryDefenses, array $modifiers, int $maxRounds, int $seed): array
    {
        if ($maxRounds < 1 || $maxRounds > 100) {
            throw new \DomainException('Nombre de rounds invalide.');
        }
        if ($seed < 0 || $seed > 0xffffffff) {
            throw new \DomainException('Graine de combat invalide.');
        }
        $units = $this->snapshot($participants, $stationaryDefenses, $maxRounds);
        $this->validateModifiers($modifiers);
        $random = new Randomizer(new Mt19937($seed));
        $rounds = [];
        $outcome = 'draw';

        for ($roundNumber = 1; $roundNumber <= $maxRounds; ++$roundNumber) {
            $order = [];
            foreach ($units as $key => $unit) {
                if ($unit['arrival_round'] <= $roundNumber && $unit['hp'] > 0) {
                    $order[] = $key;
                }
            }
            $order = $random->shuffleArray($order);
            $shots = [];

            foreach ($order as $attackerKey) {
                if ($units[$attackerKey]['hp'] <= 0) {
                    continue;
                }
                $targets = $this->targetsFor($attackerKey, $units, $roundNumber);
                if ($targets === []) {
                    continue;
                }
                $targetKey = $targets[$random->getInt(0, count($targets) - 1)];
                $attacker = $units[$attackerKey];
                $target = $units[$targetKey];
                $chance = $this->hitChance($attacker, $target);
                $shotCount = $random->getInt($attacker['shots_min'], $attacker['shots_max']);

                for ($shotNumber = 1; $shotNumber <= $shotCount; ++$shotNumber) {
                    $hit = $random->getInt(1, 10000) <= ($chance * 100);
                    $damage = 0.0;
                    $destroyed = false;
                    if ($hit && $units[$targetKey]['hp'] > 0) {
                        $modifier = $modifiers[$attacker['slug']][$target['slug']] ?? 1.0;
                        $damage = min($units[$targetKey]['hp'], $attacker['base_attack'] * $modifier);
                        $units[$targetKey]['hp'] = max(0.0, $units[$targetKey]['hp'] - $damage);
                        $destroyed = $units[$targetKey]['hp'] <= 0;
                    }
                    $shots[] = [
                        'attacker' => $attackerKey,
                        'target' => $targetKey,
                        'shot' => $shotNumber,
                        'hit' => $hit,
                        'chance' => $chance,
                        'damage' => $damage,
                        'target_hp' => $units[$targetKey]['hp'],
                        'destroyed' => $destroyed,
                    ];
                }
            }

            $rounds[] = ['number' => $roundNumber, 'shots' => $shots];
            $aliveAttackers = $this->countAlive($units, 'attacker', $roundNumber);
            $aliveDefenders = $this->countAlive($units, 'defender', $roundNumber) + $this->countAlive($units, 'static_defense', $roundNumber);
            $futureAttackers = $this->countFuture($units, 'attacker', $roundNumber);
            $futureDefenders = $this->countFuture($units, 'defender', $roundNumber) + $this->countFuture($units, 'static_defense', $roundNumber);
            if ($aliveAttackers === 0 && $futureAttackers === 0) {
                $outcome = ($aliveDefenders + $futureDefenders) === 0 ? 'draw' : 'defender_wins';
                break;
            }
            if ($aliveDefenders === 0 && $futureDefenders === 0) {
                $outcome = ($aliveAttackers + $futureAttackers) === 0 ? 'draw' : 'attacker_wins';
                break;
            }
            if ($roundNumber === $maxRounds) {
                // La règle du document prévoit la retraite des attaquants survivants.
                $outcome = 'defender_wins';
            }
        }

        return [
            'seed' => $seed,
            'outcome' => $outcome,
            'rounds_played' => count($rounds),
            'rounds' => $rounds,
            'survivors' => array_map(static fn (array $unit): array => [
                'owner' => $unit['owner'],
                'role' => $unit['role'],
                'camp' => $unit['camp'],
                'slug' => $unit['slug'],
                'hp' => $unit['hp'],
            ], array_filter($units, static fn (array $unit): bool => $unit['hp'] > 0)),
            'losses' => $this->losses($units),
        ];
    }

    public function hitChance(array $attacker, array $target): float
    {
        $value = $attacker['precision'] + $attacker['tracking'] - $target['evasion'] + ($target['signature'] / 5) - 15;

        return max(15.0, min(95.0, $value));
    }

    private function snapshot(array $participants, array $stationaryDefenses, int $maxRounds): array
    {
        usort($participants, static fn (array $a, array $b): int => strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? '')));
        usort($stationaryDefenses, static fn (array $a, array $b): int => strcmp((string) ($a['id'] ?? $a['slug'] ?? ''), (string) ($b['id'] ?? $b['slug'] ?? '')));
        $units = [];
        $total = 0;
        foreach ($participants as $participant) {
            $role = $participant['role'] ?? '';
            $arrivalRound = $participant['arrival_round'] ?? null;
            if (!in_array($role, ['attacker', 'defender'], true) || !isset($participant['id'], $participant['camp']) || !is_int($arrivalRound) || $arrivalRound < 1 || $arrivalRound > $maxRounds) {
                throw new \DomainException('Participant de combat invalide.');
            }
            $this->appendUnits($units, $total, $participant['units'] ?? [], (string) $participant['id'], $role, (string) $participant['camp'], $arrivalRound);
        }
        foreach ($stationaryDefenses as $defense) {
            if (!isset($defense['id'], $defense['camp'])) {
                throw new \DomainException('Défense statique invalide.');
            }
            $this->appendUnits($units, $total, [$defense], 'planet:' . $defense['id'], 'static_defense', (string) $defense['camp'], 1);
        }
        if ($total === 0) {
            throw new \DomainException('Un combat doit contenir au moins une unité.');
        }

        return $units;
    }

    private function appendUnits(array &$units, int &$total, array $definitions, string $owner, string $role, string $camp, int $arrivalRound): void
    {
        usort($definitions, static fn (array $a, array $b): int => strcmp((string) ($a['slug'] ?? ''), (string) ($b['slug'] ?? '')));
        foreach ($definitions as $definition) {
            $quantity = $definition['quantity'] ?? null;
            if (!is_int($quantity) || $quantity < 1) {
                throw new \DomainException('Quantité d’unités de combat invalide.');
            }
            $total += $quantity;
            if ($total > self::MAX_UNITS) {
                throw new \DomainException('Combat trop volumineux.');
            }
            $slug = $definition['slug'] ?? '';
            if (!is_string($slug) || $slug === '') {
                throw new \DomainException('Type d’unité de combat invalide.');
            }
            $stats = [];
            foreach (['hp', 'precision', 'tracking', 'evasion', 'signature', 'base_attack'] as $stat) {
                $value = $definition[$stat] ?? null;
                if (!is_numeric($value) || !is_finite((float) $value) || (in_array($stat, ['hp'], true) && $value <= 0) || (in_array($stat, ['precision', 'tracking', 'evasion', 'signature', 'base_attack'], true) && $value < 0)) {
                    throw new \DomainException('Statistique de combat invalide : ' . $stat . '.');
                }
                $stats[$stat] = (float) $value;
            }
            $shotsMin = $definition['shots_min'] ?? null;
            $shotsMax = $definition['shots_max'] ?? null;
            if (!is_int($shotsMin) || !is_int($shotsMax) || $shotsMin < 1 || $shotsMax < $shotsMin || $shotsMax > 100) {
                throw new \DomainException('Nombre de tirs invalide.');
            }
            for ($index = 1; $index <= $quantity; ++$index) {
                $key = $owner . ':' . $role . ':' . $slug . ':' . $index;
                if (isset($units[$key])) {
                    throw new \DomainException('Identifiant d’unité de combat dupliqué.');
                }
                $units[$key] = $stats + [
                    'owner' => $owner,
                    'role' => $role,
                    'camp' => $camp,
                    'slug' => $slug,
                    'arrival_round' => $arrivalRound,
                    'shots_min' => $shotsMin,
                    'shots_max' => $shotsMax,
                ];
            }
        }
    }

    private function validateModifiers(array $modifiers): void
    {
        foreach ($modifiers as $byAttacker) {
            if (!is_array($byAttacker)) {
                throw new \DomainException('Matrice de combat invalide.');
            }
            foreach ($byAttacker as $modifier) {
                if (!is_numeric($modifier) || !is_finite((float) $modifier) || $modifier <= 0 || $modifier > 10) {
                    throw new \DomainException('Modificateur de dégâts invalide.');
                }
            }
        }
    }

    private function targetsFor(string $attackerKey, array $units, int $roundNumber): array
    {
        $attacker = $units[$attackerKey];
        $targets = [];
        foreach ($units as $key => $target) {
            if ($key === $attackerKey || $target['hp'] <= 0 || $target['arrival_round'] > $roundNumber) {
                continue;
            }
            $hostile = match ($attacker['role']) {
                'static_defense' => $target['role'] === 'attacker',
                'attacker' => $target['role'] === 'defender' || $target['role'] === 'static_defense' || ($target['role'] === 'attacker' && $target['camp'] !== $attacker['camp']),
                default => $target['role'] === 'attacker',
            };
            if ($hostile) {
                $targets[] = $key;
            }
        }

        return $targets;
    }

    private function countAlive(array $units, string $role, int $roundNumber): int
    {
        $count = 0;
        foreach ($units as $unit) {
            if ($unit['role'] === $role && $unit['hp'] > 0 && $unit['arrival_round'] <= $roundNumber) {
                ++$count;
            }
        }

        return $count;
    }

    private function countFuture(array $units, string $role, int $roundNumber): int
    {
        $count = 0;
        foreach ($units as $unit) {
            if ($unit['role'] === $role && $unit['hp'] > 0 && $unit['arrival_round'] > $roundNumber) {
                ++$count;
            }
        }

        return $count;
    }

    private function losses(array $units): array
    {
        $losses = [];
        foreach ($units as $unit) {
            if ($unit['hp'] > 0) {
                continue;
            }
            $key = $unit['owner'] . ':' . $unit['slug'];
            $losses[$key] = ($losses[$key] ?? 0) + 1;
        }
        ksort($losses);

        return $losses;
    }
}
