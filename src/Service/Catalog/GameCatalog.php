<?php
namespace App\Service\Catalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use App\Entity\Universe;
final class GameCatalog
{
    private array $data=[];
    public const TIERS=['basic','intermediate','superior'];
    public function __construct(#[Autowire('%kernel.project_dir%/config/game')] string $dir)
    {
        foreach (['buildings','technologies','ships','defenses','universes'] as $group) {
            foreach (glob($dir.'/'.$group.'/*.yaml') as $file) {
                $d=Yaml::parseFile($file);
                if (!isset($d['slug']) || isset($this->data[$group][$d['slug']])) throw new \LogicException('Slug absent ou dupliqué : '.$file);
                $this->data[$group][$d['slug']]=$d;
            }
            if ($group === 'defenses') {
                uasort($this->data[$group], static fn (array $a, array $b): int => ($a['display_order'] ?? PHP_INT_MAX) <=> ($b['display_order'] ?? PHP_INT_MAX));
            }
        }
        $this->data['progression_tiers']=Yaml::parseFile($dir.'/progression_tiers.yaml');
        $this->data['planet_conquest']=Yaml::parseFile($dir.'/planet_conquest.yaml');
        $this->data['espionage']=Yaml::parseFile($dir.'/espionage.yaml');
        $this->data['boosts']=Yaml::parseFile($dir.'/boosts.yaml');
        $this->data['rules']=Yaml::parseFile($dir.'/rules.yaml');
        $this->data['combat_modifiers']=Yaml::parseFile($dir.'/combat_modifiers.yaml');
        $this->data['combat_modifiers']['relations']=$this->data['combat_modifiers']['modifiers'];
        $this->data['combat_modifiers']['modifiers']=$this->expandCounterGroups($this->data['combat_modifiers']['relations'],$this->data['combat_modifiers']['target_groups']??[]);
    }
    public function all(string $group): array { return $this->data[$group] ?? []; }
    /** Nom de famille stable, commun aux trois rangs et aux relations de contre. */
    public function combatLabel(string $slug):string
    {
        return $this->data['combat_modifiers']['target_groups'][$slug]['label']??$this->data['combat_modifiers']['labels'][$slug]??$this->data['ships'][$slug]['label']??$slug;
    }
    /** Développe les classes de cible pour le moteur et ses instantanés de combat. */
    private function expandCounterGroups(array $relations,array $groups):array
    {
        $expanded=$relations;
        foreach($groups as $group=>$definition){
            foreach($definition['members'] as $member){
                if(!isset($this->data['ships'][$member]))throw new \LogicException('Membre de classe de contre inconnu : '.$member);
            }
            foreach($relations as $attacker=>$targets){
                if(!array_key_exists($group,$targets))continue;
                unset($expanded[$attacker][$group]);
                foreach($definition['members'] as $member)$expanded[$attacker][$member]=$targets[$member]??$targets[$group];
            }
        }
        return $expanded;
    }
    public function tiers(): array { return $this->data['progression_tiers']['tiers']; }
    public static function tierFactor(string $tier): float
    {
        return match($tier){'basic'=>1.0,'intermediate'=>1.25,'superior'=>1.5625,default=>throw new \DomainException('Rang de progression invalide.')};
    }
    public static function colonyLimit(array|int $levels,array $effect,int $universeLimit):int
    {
        if(is_int($levels)||is_numeric($levels))$levels=['basic'=>(int)$levels];
        $base=(int)($effect['base_planets']??1);$perLevel=(float)($effect['value_per_level']??1);$total=$base;
        foreach(self::TIERS as $tier){$cap=(int)($effect['tier_max_levels'][$tier]??0);$total+=min(max(0,(int)($levels[$tier]??0)),$cap)*$perLevel;}
        return max(1,min((int)($effect['max_planets']??$universeLimit),$universeLimit,(int)$total));
    }
    /** Rendements décroissants partagés par les trois rangs de recherche. */
    public static function diminishingProgress(array|int $levels,int $maxLevel=25):float
    {
        if(is_int($levels)||is_numeric($levels))$levels=['basic'=>(int)$levels];
        $maxLevel=max(1,$maxLevel);$weighted=0.0;$weightTotal=0.0;
        foreach(self::TIERS as $tier){$weight=self::tierFactor($tier);$weightTotal+=$weight;$weighted+=$weight*min($maxLevel,max(0,(int)($levels[$tier]??0)))/$maxLevel;}
        $share=$weightTotal>0?$weighted/$weightTotal:0.0;
        return log(1+2*$share)/log(3);
    }
    public static function diminishingBonus(array|int $levels,float $cap,int $maxLevel=25):float
    {
        return max(0.0,$cap)*self::diminishingProgress($levels,$maxLevel);
    }
    /** Multiplicateur de progression par niveau selon les paliers du catalogue. */
    public static function growthMultiplier(int $level,array $segments):float
    {
        if($level<1)throw new \InvalidArgumentException('Niveau de progression invalide.');
        $factor=1.0;
        for($targetLevel=2;$targetLevel<=$level;$targetLevel++){
            foreach($segments as $segment){
                if(!isset($segment['max_level'])||$targetLevel<=(int)$segment['max_level']){$factor*=(float)$segment['multiplier'];break;}
            }
        }
        return $factor;
    }
    public function forUniverse(Universe $universe): array
    {
        $config = $this->data['universes'][$universe->slug] ?? [
            'slug' => $universe->slug,'name' => $universe->name,
            'features' => ['guilds_enabled' => false, 'planet_conquest' => false],
            'multipliers' => ['building_time' => 1, 'research_time' => 1, 'ship_build_time' => 1, 'resource_production' => 1, 'travel_time' => 1],
            'limits' => ['max_planets' => 5, 'max_fleet_size' => 100],
        ];
        foreach (['features', 'multipliers', 'limits'] as $section) $config[$section] = array_replace($config[$section] ?? [], $universe->gameSettings[$section] ?? []);
        $config['catalog']=[];
        foreach(['buildings','technologies','ships','defenses'] as $group){
            $config['catalog'][$group]=$this->data[$group];
            foreach($universe->gameSettings['catalog_overrides'][$group]??[] as $slug=>$override){
                if(isset($config['catalog'][$group][$slug])&&is_array($override))$config['catalog'][$group][$slug]=array_replace_recursive($config['catalog'][$group][$slug],$override);
            }
        }
        return $config;
    }
    public function getForUniverse(Universe $universe,string $group,string $slug):array
    {
        if(!in_array($group,['buildings','technologies','ships','defenses'],true))return $this->get($group,$slug);
        return $this->forUniverse($universe)['catalog'][$group][$slug]??throw new \DomainException('Élément de catalogue inconnu.');
    }
    public function getTierForUniverse(Universe $universe,string $group,string $slug,string $tier):array
    {
        return $this->withTier($this->getForUniverse($universe,$group,$slug),$tier);
    }
    public function withTier(array $definition,string $tier):array
    {
        if(!in_array($tier,self::TIERS,true))throw new \DomainException('Rang de progression invalide.');
        $factor=self::tierFactor($tier);
        $definition['_tier']=$tier;
        $definition['_tier_factor']=$factor;
        $definition['_tier_label']=$this->tiers()[$tier]['label'];
        $definition['label']=$definition['tier_labels'][$tier]??$definition['label'];
        $definition['description']=$definition['tier_descriptions'][$tier]??$definition['description']??'';
        if(array_key_exists('base_attack',$definition)){
            $definition['tier']=$tier;
            foreach(['hp','shield','base_attack','secondary_attack','speed_system','speed_galaxy','fuel_capacity','cargo_capacity'] as $stat)if(isset($definition[$stat])&&is_numeric($definition[$stat])&&(float)$definition[$stat]>0)$definition[$stat]=(int)ceil((float)$definition[$stat]*$factor);
        }
        return $definition;
    }
    public function get(string $group,string $slug): array { return $this->data[$group][$slug] ?? throw new \DomainException('Élément de catalogue inconnu.'); }
    public function rules(): array { return $this->data['rules']; }
    public function costs(array $definition,int $level=1,int $quantity=1,?string $operationGroup=null): array
    {
        if($level<1||$quantity<1)throw new \InvalidArgumentException('Niveau ou quantité de coût invalide.');
        $tier=(string)($definition['_tier']??'basic');
        $profiles=$definition['cost_profiles']??null;
        $base=is_array($profiles)?($profiles[$tier]??[]):($definition['costs_base']??$definition['costs']??[]);
        $factor=is_array($profiles)?1.0:self::tierFactor($tier);
        $curveKey=match($operationGroup){'buildings'=>'building_cost','technologies'=>'research_cost',default=>null};
        $segments=$curveKey?($this->data['rules']['progression_curves'][$curveKey]??[]):[];
        $levelFactor=$segments!==[]?self::growthMultiplier($level,$segments):max(0.01,(float)($definition['costs_scaling']??1))**($level-1);
        $result=['metal'=>0,'nano'=>0,'antimatter'=>0,'hydrogen'=>0];
        foreach($base as $resource=>$amount){
            $resource=match($resource){'carbon'=>'nano','energy'=>'electricity',default=>$resource};
            if($resource==='electricity')continue;
            if(!array_key_exists($resource,$result)||!is_numeric($amount)||$amount<=0)continue;
            $scaled=(float)$amount*$factor*$levelFactor*$quantity;
            $result[$resource]=max(1,(int)ceil($scaled));
        }
        return $result;
    }
}
