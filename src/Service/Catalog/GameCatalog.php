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
        }
        $this->data['progression_tiers']=Yaml::parseFile($dir.'/progression_tiers.yaml');
        $this->data['planet_conquest']=Yaml::parseFile($dir.'/planet_conquest.yaml');
        $this->data['espionage']=Yaml::parseFile($dir.'/espionage.yaml');
        $this->data['boosts']=Yaml::parseFile($dir.'/boosts.yaml');
        $this->data['rules']=Yaml::parseFile($dir.'/rules.yaml');
        $this->data['combat_modifiers']=Yaml::parseFile($dir.'/combat_modifiers.yaml');
    }
    public function all(string $group): array { return $this->data[$group] ?? []; }
    public function tiers(): array { return $this->data['progression_tiers']['tiers']; }
    public static function tierFactor(string $tier): float
    {
        return match($tier){'basic'=>1.0,'intermediate'=>1.25,'superior'=>1.5625,default=>throw new \DomainException('Rang de progression invalide.')};
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
        if(array_key_exists('base_attack',$definition)){
            $definition['tier']=$tier;
            foreach(['hp','shield','base_attack','speed_system','speed_galaxy','fuel_capacity','cargo_capacity'] as $stat)if(isset($definition[$stat])&&is_numeric($definition[$stat])&&(float)$definition[$stat]>0)$definition[$stat]=(int)ceil((float)$definition[$stat]*$factor);
            if($factor>1)$definition['extra_shot_chances'][]=$factor-1;
        }
        return $definition;
    }
    public function get(string $group,string $slug): array { return $this->data[$group][$slug] ?? throw new \DomainException('Élément de catalogue inconnu.'); }
    public function rules(): array { return $this->data['rules']; }
    public function costs(array $definition,int $level=1,int $quantity=1): array
    {
        if($level<1||$quantity<1)throw new \InvalidArgumentException('Niveau ou quantité de coût invalide.');
        $tier=(string)($definition['_tier']??'basic');
        $profiles=$definition['cost_profiles']??null;
        $base=is_array($profiles)?($profiles[$tier]??[]):($definition['costs_base']??$definition['costs']??[]);
        $factor=is_array($profiles)?1.0:self::tierFactor($tier);
        $scale=max(0.01,(float)($definition['costs_scaling']??1));$result=['metal'=>0,'nano'=>0,'antimatter'=>0,'hydrogen'=>0];
        foreach($base as $resource=>$amount){
            $resource=match($resource){'carbon'=>'nano','energy'=>'electricity',default=>$resource};
            if($resource==='electricity')continue;
            if(!array_key_exists($resource,$result)||!is_numeric($amount)||$amount<=0)continue;
            $scaled=(float)$amount*$factor*$scale**($level-1)*$quantity;
            $result[$resource]=max(1,(int)ceil($scaled));
        }
        return $result;
    }
}
