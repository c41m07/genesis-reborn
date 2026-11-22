<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Service\Combat\CombatSimulator;

/** Prépare un rapport d’exercice sans charger d’unité, de joueur ou de planète. */
final class PirateExercise
{
    private const MAX_ROUNDS = 6;
    private const UNITS_PER_SIDE = 2;

    public function __construct(private CombatSimulator $simulator)
    {
    }

    /**
     * @return array{
     *     outcome: string,
     *     escortSurvivors: int,
     *     escortLosses: int,
     *     pirateSurvivors: int,
     *     pirateLosses: int,
     *     rounds: list<array{number: int, shots: int, hits: int, shieldDamage: float, structureDamage: float}>
     * }
     */
    public function report(int $seed): array
    {
        // Ces unités n'existent pas dans le catalogue. Ce petit exercice autorise le rejeu
        // sans lire ni financer une composition réelle, sans dépendre non plus de l'équilibrage.
        $definition = [
            'quantity' => self::UNITS_PER_SIDE,
            'hp' => 50,
            'shield' => 12,
            'precision' => 80,
            'tracking' => 25,
            'evasion' => 15,
            'signature' => 25,
            'base_attack' => 60,
            'shots_min' => 1,
            'shots_max' => 1,
        ];
        $participants = [
            ['id' => 'pirates', 'camp' => 'pirates', 'role' => 'attacker', 'arrival_round' => 1, 'units' => [['slug' => 'drill_raider'] + $definition]],
            ['id' => 'escort', 'camp' => 'escort', 'role' => 'defender', 'arrival_round' => 1, 'units' => [['slug' => 'drill_escort'] + $definition]],
        ];
        $result = $this->simulator->simulate($participants, [], [], self::MAX_ROUNDS, $seed);
        $escortSurvivors = count(array_filter($result['survivors'], static fn (array $unit): bool => $unit['camp'] === 'escort'));
        $pirateSurvivors = count(array_filter($result['survivors'], static fn (array $unit): bool => $unit['camp'] === 'pirates'));
        $rounds = [];
        foreach ($result['rounds'] as $round) {
            $shots = $round['shots'];
            $rounds[] = [
                'number' => $round['number'],
                'shots' => count($shots),
                'hits' => count(array_filter($shots, static fn (array $shot): bool => $shot['hit'])),
                'shieldDamage' => (float) array_sum(array_column($shots, 'shield_damage')),
                'structureDamage' => (float) array_sum(array_column($shots, 'structure_damage')),
            ];
        }

        return [
            'outcome' => $result['outcome'],
            'escortSurvivors' => $escortSurvivors,
            'escortLosses' => self::UNITS_PER_SIDE - $escortSurvivors,
            'pirateSurvivors' => $pirateSurvivors,
            'pirateLosses' => self::UNITS_PER_SIDE - $pirateSurvivors,
            'rounds' => $rounds,
        ];
    }
}
