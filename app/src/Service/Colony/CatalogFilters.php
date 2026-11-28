<?php
namespace App\Service\Colony;

final class CatalogFilters
{
    private const TECHNOLOGY_ORDER = [
        'engineering_foundations' => 0,
        'naval_engineering' => 1,
        'automated_defense_repair' => 2,
        'nanomaterial_synthesis' => 3,
        'vector_propulsion' => 4,
        'weapon_calibration' => 5,
        'target_acquisition' => 6,
        'structural_armor' => 7,
        'shield_harmonics' => 8,
        'evasive_navigation' => 9,
        'signature_reduction' => 10,
        'rapid_fire_cycle' => 11,
        'cryogenic_reserves' => 12,
        'modular_cargo_bays' => 13,
        'tactical_intelligence' => 14,
        'intelligence_countermeasures' => 15,
        'signature_masking' => 16,
    ];

    private const LABELS=[
        'buildings'=>['all'=>'Tous','energy'=>'Énergie','economy'=>'Production','storage'=>'Stockage','infrastructure'=>'Infrastructures','defense'=>'Fortifications'],
        'technologies'=>['all'=>'Toutes','economy'=>'Économie','industry'=>'Industrie','logistics'=>'Logistique','propulsion'=>'Propulsion','combat'=>'Combat','defense'=>'Défense','expansion'=>'Colonisation','espionage'=>'Espionnage','infrastructure'=>'Autres recherches'],
        'ships'=>['all'=>'Tous','light'=>'Légers','bomber'=>'Bombardiers','escort'=>'Escorteurs','heavy'=>'Lourds','capital'=>'Capitaux','transport'=>'Transport','colony'=>'Colonisation','espionage'=>'Espionnage','utility'=>'Utilitaires'],
        'defenses'=>['all'=>'Toutes','light'=>'Anti-légers','escort'=>'Anti-escorteurs','heavy'=>'Anti-lourds','capital'=>'Anti-capitaux','defense'=>'Autres défenses'],
    ];

    public function category(string $group,array $definition): string
    {
        $slug=$definition['slug'];$effect=$definition['effect'] ?? [];
        if ($group === 'buildings') {
            return match (true) {
                ($definition['production_resource'] ?? null) === 'electricity' => 'energy',
                isset($definition['production_resource']) => 'economy',
                isset($definition['storage_resource']) => 'storage',
                $slug === 'fortress' => 'defense',
                default => 'infrastructure',
            };
        }
        if ($group==='defenses') return isset(self::LABELS['defenses'][$definition['target_class'] ?? ''])?$definition['target_class']:'defense';
        if ($group==='ships') {
            if ($slug==='transport') return 'transport';
            if ($slug==='colony_ship') return 'colony';
            if ($slug==='probe') return 'espionage';
            $category=$definition['category'] ?? '';
            return in_array($category,['light','escort','bomber','heavy','capital'],true)?$category:'utility';
        }
        return match($effect['type'] ?? '') {
            'time_discount' => 'industry',
            'cost_discount' => 'economy',
            'planet_limit'=>'expansion',
            'defense_rebuild'=>'defense',
            'unlock'=>'espionage',
            'stat_bonus' => match (true) {
                in_array('speed_system', $effect['stats'] ?? [], true) => 'propulsion',
                in_array($effect['stat'] ?? '', ['cargo_capacity', 'fuel_capacity'], true) => 'logistics',
                default => 'combat',
            },
            'extra_shot_chance'=>'combat',
            default=>'infrastructure',
        };
    }

    public function familyIcon(string $group, array $definition, ?string $category = null): string
    {
        if ($group === 'ships') {
            return $this->shipIcon($definition);
        }
        if ($group === 'defenses') {
            return 'diplomacy';
        }

        $category ??= $this->category($group, $definition);
        if ($group === 'buildings') {
            return match ($category) {
                'energy' => 'electricity',
                'economy' => 'extraction',
                'storage' => 'storage',
                'defense' => 'fortification',
                default => 'buildings',
            };
        }

        return match ($category) {
            'combat' => 'target',
            'defense' => 'shield',
            'espionage' => 'intelligence',
            'infrastructure' => 'research',
            default => $category,
        };
    }

    // Les sélections de mission reprennent les catégories du chantier, y compris les rôles civils.
    public function shipIcon(array $definition): string
    {
        $category = $this->category('ships', $definition);

        return match ($category) {
            'light' => 'fleet',
            'transport' => 'freighter',
            'colony' => 'colony-ship',
            'espionage' => 'probe',
            'utility' => 'satellite',
            'bomber', 'escort', 'heavy', 'capital' => $category,
        };
    }

    /** Regroupe uniquement les choix présents et conserve les ordinaux des escortes. */
    public function shipTableGroups(array $ships, array $catalog): array
    {
        $families = [];
        foreach ($ships as $ship) {
            $slug = $ship instanceof \App\Entity\FleetShip ? $ship->slug : $ship['slug'];
            $tier = $ship instanceof \App\Entity\FleetShip ? $ship->tier : $ship['tier'];
            $families[$slug][$tier] = $ship;
        }

        $definitions = [];
        foreach ($families as $slug => $tiers) {
            $definitions[$slug] = $catalog[$slug] ?? ['slug' => $slug, 'label' => $slug];
        }

        $groups = [];
        foreach ($this->groupedDefinitions('ships', $definitions) as $category => $rows) {
            foreach ($rows as $slug => $definition) {
                $groups[$category][$slug] = [
                    'definition' => $definition,
                    'tiers' => $families[$slug],
                    'strength' => $this->shipStrength($definition),
                ];
            }
        }

        return $groups;
    }

    /** @return array<string, array<string, array>> Familles par catégorie, dans l’ordre de présentation. */
    public function groupedDefinitions(string $group, array $definitions): array
    {
        $groups = [];
        foreach ($definitions as $slug => $definition) {
            $groups[$this->category($group, $definition)][$slug] = $definition;
        }

        $ordered = [];
        foreach (self::LABELS[$group] as $category => $label) {
            if (!isset($groups[$category])) {
                continue;
            }
            $rows = $groups[$category];
            if ($group === 'ships') {
                uasort($rows, fn(array $left, array $right): int => $this->shipStrength($left) <=> $this->shipStrength($right)
                    ?: strcmp($left['slug'], $right['slug']));
            } elseif ($group === 'technologies') {
                uksort($rows, static fn(string $left, string $right): int =>
                    (self::TECHNOLOGY_ORDER[$left] ?? PHP_INT_MAX) <=> (self::TECHNOLOGY_ORDER[$right] ?? PHP_INT_MAX));
            }
            $ordered[$label] = $rows;
        }

        return $ordered;
    }

    private function shipStrength(array $definition): float
    {
        $salvo = ($definition['base_attack'] ?? 0) * (($definition['shots_min'] ?? 0) + ($definition['shots_max'] ?? 0)) / 2;
        return ($salvo + ($definition['shield'] ?? 0) + ($definition['hp'] ?? 0)) / 3;
    }

    public function options(string $group,array $definitions): array
    {
        $present=['all'=>true];
        foreach ($definitions as $definition) $present[$this->category($group,$definition)]=true;
        return array_intersect_key(self::LABELS[$group],$present);
    }
}
