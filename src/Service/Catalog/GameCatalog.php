<?php
namespace App\Service\Catalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
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
        $this->data['rules']=Yaml::parseFile($dir.'/rules.yaml');
        $this->data['combat_modifiers']=Yaml::parseFile($dir.'/combat_modifiers.yaml');
    }
    public function all(string $group): array { return $this->data[$group] ?? []; }
    public function get(string $group,string $slug): array { return $this->data[$group][$slug] ?? throw new \DomainException('Élément de catalogue inconnu.'); }
    public function rules(): array { return $this->data['rules']; }
    public function costs(array $definition,int $level=1,int $quantity=1): array
    {
        $result=[];
        foreach ($definition['costs_base'] ?? $definition['costs'] ?? [] as $resource=>$base) $result[$resource]=(int)ceil($base*($definition['costs_scaling'] ?? 1)**($level-1)*$quantity);
        return $result;
    }
}
