<?php

namespace App\Service\Combat;

use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Index de ciblage d’un round du simulateur joueur : une force par camp, avec défenses statiques côté défenseur. */
#[Exclude]
final class PlayerCombatTargets
{
    /** @var array<string, array{alive: CombatTargetWeights, damaged: CombatTargetWeights}> */
    private array $pools = [];

    /** @var array<string, array{weight: int, alive: bool, damaged: bool, positions: array<string, int>}> */
    private array $targets = [];

    public function __construct(array $units, int $round)
    {
        $members = [];
        foreach ($units as $key => $unit) {
            if ($unit['hp'] <= 0 || $unit['arrival_round'] > $round) {
                continue;
            }

            $side = $unit['role'] === 'attacker' ? 'attacker' : 'defender';
            $positions = [];
            foreach ([$side, $side . ':' . $unit['category']] as $pool) {
                $positions[$pool] = count($members[$pool] ?? []);
                $members[$pool][] = $key;
            }
            $this->targets[$key] = [
                'weight' => max(1, (int) round(max(1.0, $unit['signature']) * 100)),
                'alive' => false,
                'damaged' => false,
                'positions' => $positions,
            ];
        }

        foreach ($members as $pool => $keys) {
            $this->pools[$pool] = [
                'alive' => new CombatTargetWeights($keys),
                'damaged' => new CombatTargetWeights($keys),
            ];
        }

        foreach ($this->targets as $key => $_) {
            $this->update($key, $units[$key]);
        }
    }

    public function hasOpponent(array $attacker): bool
    {
        $side = $attacker['role'] === 'attacker' ? 'defender' : 'attacker';
        return isset($this->pools[$side]) && !$this->pools[$side]['alive']->isEmpty();
    }

    public function draw(Randomizer $random, array $attacker, ?string $category = null): ?string
    {
        $side = $attacker['role'] === 'attacker' ? 'defender' : 'attacker';
        $pool = $this->pools[$category === null ? $side : $side . ':' . $category] ?? null;
        if ($pool === null) {
            return null;
        }

        return $pool['damaged']->draw($random) ?? $pool['alive']->draw($random);
    }

    public function update(string $key, array $unit): void
    {
        $target = &$this->targets[$key];
        $alive = $unit['hp'] > 0;
        $damaged = $alive && ($unit['hp'] < $unit['initial_hp'] || $unit['shield'] < $unit['max_shield']);
        // Un impact peut rendre une cible prioritaire ou la retirer immédiatement des deux listes.
        foreach (['alive' => $alive, 'damaged' => $damaged] as $poolType => $present) {
            if ($target[$poolType] === $present) {
                continue;
            }
            $difference = $present ? $target['weight'] : -$target['weight'];
            foreach ($target['positions'] as $pool => $position) {
                $this->pools[$pool][$poolType]->add($position, $difference);
            }
            $target[$poolType] = $present;
        }
    }
}
