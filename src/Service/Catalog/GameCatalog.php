<?php
namespace App\Service\Catalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use App\Entity\Universe;
final class GameCatalog
{
    private array $data=[];
    public function __construct(#[Autowire('%kernel.project_dir%/config/game')] string $dir)
    {
        foreach (['buildings','technologies','ships','defenses','universes'] as $group) {
            foreach (glob($dir.'/'.$group.'/*.yaml') as $file) {
                $d=Yaml::parseFile($file);
                if (!isset($d['slug']) || isset($this->data[$group][$d['slug']])) throw new \LogicException('Slug absent ou dupliqué : '.$file);
                $this->data[$group][$d['slug']]=$d;
            }
        }
        $this->data['planet_conquest']=Yaml::parseFile($dir.'/planet_conquest.yaml');
        $this->data['espionage']=Yaml::parseFile($dir.'/espionage.yaml');
        $this->data['boosts']=Yaml::parseFile($dir.'/boosts.yaml');
        $this->data['rules']=Yaml::parseFile($dir.'/rules.yaml');
        $this->data['combat_modifiers']=Yaml::parseFile($dir.'/combat_modifiers.yaml');
    }
    public function all(string $group): array { return $this->data[$group] ?? []; }
    public function forUniverse(Universe $universe): array
    {
        $config = $this->data['universes'][$universe->slug] ?? [
            'slug' => $universe->slug,
            'name' => $universe->name,
            'features' => ['guilds_enabled' => false, 'planet_conquest' => false],
            'multipliers' => ['building_time' => 1, 'research_time' => 1, 'ship_build_time' => 1, 'resource_production' => 1, 'travel_time' => 1],
            'limits' => ['max_planets' => 5, 'max_fleet_size' => 100],
        ];
        foreach (['features', 'multipliers', 'limits'] as $section) {
            $config[$section] = array_replace($config[$section] ?? [], $universe->gameSettings[$section] ?? []);
        }
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
    public function get(string $group,string $slug): array { return $this->data[$group][$slug] ?? throw new \DomainException('Élément de catalogue inconnu.'); }
    public function rules(): array { return $this->data['rules']; }
    public function costs(array $definition,int $level=1,int $quantity=1): array
    {
        $result=[];
        foreach ($definition['costs_base'] ?? $definition['costs'] ?? [] as $resource=>$base) $result[$resource]=(int)ceil($base*($definition['costs_scaling'] ?? 1)**($level-1)*$quantity);
        return $result;
    }
}
