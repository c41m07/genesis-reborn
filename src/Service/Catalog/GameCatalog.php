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
        if(isset($definition['category'])&&array_key_exists('base_attack',$definition)){
            foreach(['hp','shield','base_attack','precision','tracking','evasion','speed_system','speed_galaxy','fuel_capacity','cargo_capacity'] as $stat)if(isset($definition[$stat])&&is_numeric($definition[$stat])&&(float)$definition[$stat]>0)$definition[$stat]=(int)ceil((float)$definition[$stat]*$factor);
            if(isset($definition['signature'])&&is_numeric($definition['signature'])&&(float)$definition['signature']>0)$definition['signature']=(int)ceil((float)$definition['signature']/$factor);
            if($factor>1)$definition['extra_shot_chances'][]=$factor-1;
        }
        return $definition;
    }
    public function get(string $group,string $slug): array { return $this->data[$group][$slug] ?? throw new \DomainException('Élément de catalogue inconnu.'); }
    public function rules(): array { return $this->data['rules']; }
    public function costs(array $definition,int $level=1,int $quantity=1): array
    {
        $result=[];$factor=(float)($definition['_tier_factor']??self::tierFactor((string)($definition['_tier']??'basic')));
        foreach ($definition['costs_base'] ?? $definition['costs'] ?? [] as $resource=>$base) {
            $amount=(float)$base*$factor*($definition['costs_scaling'] ?? 1)**($level-1)*$quantity;
            $result[$resource]=$base>0?max(1,(int)ceil($amount)):0;
        }
        return $result;
    }
}
