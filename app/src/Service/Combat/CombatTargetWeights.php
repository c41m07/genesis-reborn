<?php

namespace App\Service\Combat;

use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Tirage pondéré dans l’ordre initial, sans reparcourir toutes les unités à chaque tir. */
#[Exclude]
final class CombatTargetWeights
{
    /** @var array<int, int> Poids cumulés par intervalles d’un arbre de Fenwick. */
    private array $tree;

    private int $total = 0;

    private int $topBit = 1;

    /** @param list<string> $keys */
    public function __construct(private readonly array $keys)
    {
        $size = count($keys);
        $this->tree = array_fill(0, $size + 1, 0);
        while ($this->topBit <= intdiv($size, 2)) {
            $this->topBit <<= 1;
        }
    }

    public function add(int $position, int $difference): void
    {
        $this->total += $difference;
        for ($index = $position + 1; $index < count($this->tree); $index += $index & -$index) {
            $this->tree[$index] += $difference;
        }
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function draw(Randomizer $random): ?string
    {
        if ($this->total === 0) {
            return null;
        }

        // Même intervalle et même ordre qu’un tirage linéaire : la graine reste reproductible.
        $remaining = $random->getInt(1, $this->total);
        $prefix = 0;
        for ($bit = $this->topBit; $bit > 0; $bit >>= 1) {
            $next = $prefix + $bit;
            if (isset($this->tree[$next]) && $this->tree[$next] < $remaining) {
                $remaining -= $this->tree[$next];
                $prefix = $next;
            }
        }

        return $this->keys[$prefix];
    }
}
