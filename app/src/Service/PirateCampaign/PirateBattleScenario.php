<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatSimulator;

/** Données déjà calculées par le serveur. Pas de lecture du catalogue entre deux tentatives. */
final readonly class PirateBattleScenario
{
    private const UNIT_KEYS = [
        'slug', 'tier', 'quantity', 'hp', 'shield', 'precision', 'tracking', 'evasion', 'signature',
        'base_attack', 'category', 'shots_min', 'shots_max', 'extra_shot_chances', 'secondary_attack',
        'secondary_shots_min', 'secondary_shots_max', 'secondary_precision_modifier', 'secondary_target_category',
    ];

    private function __construct(
        private array $pirateUnits,
        private array $modifiers,
        private int $maxRounds,
        private float $shieldRechargePercent,
    ) {
    }

    /**
     * Un jour le scénario sera créé avec la rencontre par une source serveur, pas recalculé depuis
     * les libellés du rapport. Aucun réglage par défaut de campagne n'est fixé dans ce modèle.
     *
     * @param list<array<string,mixed>> $pirateUnits Profils complets, effets et rangs déjà appliqués.
     * @param array<string,array<string,int|float>> $modifiers
     */
    public static function freeze(
        PirateEncounterSnapshot $revealed,
        array $pirateUnits,
        array $modifiers,
        int $maxRounds,
        float $shieldRechargePercent,
    ): self {
        $units = self::freezeUnits($pirateUnits);
        $counts = [];
        foreach ($units as $unit) {
            $counts[$unit['slug']][$unit['tier']] = $unit['quantity'];
        }
        if ($counts !== $revealed->composition()) {
            throw new \DomainException('Les unités pirates ne correspondent pas au renseignement figé.');
        }
        if ($maxRounds < 1 || $maxRounds > 100 || !is_finite($shieldRechargePercent)
            || $shieldRechargePercent < 0 || $shieldRechargePercent > 100) {
            throw new \DomainException('Réglages de combat pirate invalides.');
        }

        return new self($units, self::detached($modifiers), $maxRounds, $shieldRechargePercent);
    }

    /** @param list<array<string,mixed>> $units @return list<array<string,mixed>> */
    public static function freezeUnits(array $units): array
    {
        $bySlug = [];
        $keys = array_fill_keys(self::UNIT_KEYS, true);
        foreach ($units as $unit) {
            $slug = $unit['slug'] ?? null;
            $tier = $unit['tier'] ?? null;
            $count = $unit['quantity'] ?? null;
            if (!is_string($slug) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $slug) !== 1
                || !in_array($tier, GameCatalog::TIERS, true)
                || !is_int($count) || $count < 1 || isset($bySlug[$slug][$tier])) {
                throw new \DomainException('Composition de combat pirate invalide ou dupliquée.');
            }
            $bySlug[$slug][$tier] = self::detached(array_intersect_key($unit, $keys));
        }
        if ($bySlug === []) {
            throw new \DomainException('Une tentative exige deux camps réellement équipés.');
        }

        // L'ordre des données catalogue ne doit pas modifier les tirages d'une tentative.
        ksort($bySlug);
        $result = [];
        foreach ($bySlug as $tiers) {
            ksort($tiers);
            array_push($result, ...array_values($tiers));
        }

        return $result;
    }

    /** Les profils n'admettent que des données, pas des objets ou références vers un catalogue vivant. */
    private static function detached(array $source): array
    {
        $copy = [];
        foreach ($source as $key => $value) {
            if (is_array($value)) {
                $copy[$key] = self::detached($value);
            } elseif (is_scalar($value) || $value === null) {
                $copy[$key] = $value;
            } else {
                throw new \DomainException('Un profil de combat doit être une donnée figée.');
            }
        }

        return $copy;
    }

    /** @return array{units: array, modifiers: array, rounds: int, recharge: float} Persistance serveur uniquement. */
    public function snapshot(): array
    {
        return ['units' => $this->pirateUnits, 'modifiers' => $this->modifiers, 'rounds' => $this->maxRounds, 'recharge' => $this->shieldRechargePercent];
    }

    public static function fromSnapshot(PirateEncounterSnapshot $encounter, array $data): self
    {
        return self::freeze($encounter, $data['units'], $data['modifiers'], $data['rounds'], $data['recharge']);
    }

    public function assertNativeUnitLimit(array $playerUnits): void
    {
        if (array_sum(array_column($this->pirateUnits, 'quantity')) + array_sum(array_column($playerUnits, 'quantity')) > CombatSimulator::MAX_UNITS) {
            throw new \DomainException('La rencontre dépasse la capacité du moteur de combat.');
        }
    }

    /** Calcul pur : des identifiants abstraits empêchent d'introduire un autre joueur ou une défense planétaire. */
    public function resolve(CombatSimulator $simulator, array $playerUnits, int $seed): array
    {
        return $simulator->simulate(
            [
                ['id' => 'escort', 'role' => 'attacker', 'camp' => 'escort', 'arrival_round' => 1, 'units' => $playerUnits],
                ['id' => 'pirates', 'role' => 'defender', 'camp' => 'pirates', 'arrival_round' => 1, 'units' => $this->pirateUnits],
            ],
            [],
            $this->modifiers,
            $this->maxRounds,
            $seed,
            shieldRechargePercent: $this->shieldRechargePercent,
        );
    }
}
