<?php
namespace App\Service\Colony;

final class CatalogFilters
{
    private const LABELS=[
        'buildings'=>['all'=>'Tous','economy'=>'Production','storage'=>'Stockage','infrastructure'=>'Infrastructures','defense'=>'Fortifications'],
        'technologies'=>['all'=>'Toutes','economy'=>'Économie et industrie','combat'=>'Combat','logistics'=>'Propulsion et logistique','expansion'=>'Colonisation','defense'=>'Défense','espionage'=>'Espionnage','infrastructure'=>'Autres recherches'],
        'ships'=>['all'=>'Tous','light'=>'Légers','escort'=>'Escorteurs','bomber'=>'Bombardiers','heavy'=>'Lourds','capital'=>'Capitaux','transport'=>'Transport','colony'=>'Colonisation','espionage'=>'Espionnage','utility'=>'Utilitaires'],
        'defenses'=>['all'=>'Toutes','light'=>'Anti-légers','escort'=>'Anti-escorteurs','heavy'=>'Anti-lourds','capital'=>'Anti-capitaux','defense'=>'Autres défenses'],
    ];

    public function category(string $group,array $definition): string
    {
        $slug=$definition['slug'];$effect=$definition['effect'] ?? [];
        if ($group==='buildings') return isset($definition['production_resource'])?'economy':(isset($definition['storage_resource'])?'storage':($slug==='fortress'?'defense':'infrastructure'));
        if ($group==='defenses') return isset(self::LABELS['defenses'][$definition['target_class'] ?? ''])?$definition['target_class']:'defense';
        if ($group==='ships') {
            if ($slug==='transport') return 'transport';
            if ($slug==='colony_ship') return 'colony';
            if ($slug==='probe') return 'espionage';
            $category=$definition['category'] ?? '';
            return in_array($category,['light','escort','bomber','heavy','capital'],true)?$category:'utility';
        }
        return match($effect['type'] ?? '') {
            'time_discount','cost_discount'=>'economy',
            'planet_limit'=>'expansion',
            'defense_rebuild'=>'defense',
            'unlock'=>'espionage',
            'stat_bonus'=>in_array($effect['stat'] ?? '',['cargo_capacity','fuel_capacity'],true) || in_array('speed_system',$effect['stats'] ?? [],true)?'logistics':'combat',
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
