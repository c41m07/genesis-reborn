<?php
namespace App\Service\Colony;

final class CatalogFilters
{
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

        $groups = [];
        foreach ($families as $slug => $tiers) {
            $definition = $catalog[$slug] ?? ['slug' => $slug, 'label' => $slug];
            $category = $this->category('ships', $definition);
            $salvo = ($definition['base_attack'] ?? 0) * (($definition['shots_min'] ?? 0) + ($definition['shots_max'] ?? 0)) / 2;
            $groups[$category][$slug] = [
                'definition' => $definition,
                'tiers' => $tiers,
                'strength' => ($salvo + ($definition['shield'] ?? 0) + ($definition['hp'] ?? 0)) / 3,
            ];
        }

        $ordered = [];
        foreach (self::LABELS['ships'] as $category => $label) {
            if (!isset($groups[$category])) {
                continue;
            }
            $rows = $groups[$category];
            uasort($rows, static fn(array $a, array $b): int => $a['strength'] <=> $b['strength']
                ?: strcmp($a['definition']['slug'], $b['definition']['slug']));
            $ordered[$label] = $rows;
        }
        return $ordered;
    }

    public function options(string $group,array $definitions): array
    {
        $present=['all'=>true];
        foreach ($definitions as $definition) $present[$this->category($group,$definition)]=true;
        return array_intersect_key(self::LABELS[$group],$present);
    }
}
