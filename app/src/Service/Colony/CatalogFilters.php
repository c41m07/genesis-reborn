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

    public function options(string $group,array $definitions): array
    {
        $present=['all'=>true];
        foreach ($definitions as $definition) $present[$this->category($group,$definition)]=true;
        return array_intersect_key(self::LABELS[$group],$present);
    }
}
