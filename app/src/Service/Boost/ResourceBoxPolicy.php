<?php
namespace App\Service\Boost;

use App\Entity\{AdminAuditLog, Player, Universe};
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class ResourceBoxPolicy
{
    public const FORMATS = ['small'=>'Petite boîte de ressources','medium'=>'Boîte moyenne de ressources','large'=>'Grande boîte de ressources'];
    public const AMOUNTS = [
        'small'=>['metal'=>4000,'nano'=>1000,'hydrogen'=>1000,'antimatter'=>200,'electricity'=>2000],
        'medium'=>['metal'=>10000,'nano'=>2500,'hydrogen'=>2500,'antimatter'=>500,'electricity'=>5000],
        'large'=>['metal'=>20000,'nano'=>5000,'hydrogen'=>5000,'antimatter'=>1000,'electricity'=>10000],
    ];
    public function __construct(private GameCatalog $catalog, private GameManager $manager, private EntityManagerInterface $em, private ClockInterface $clock) {}

    public function settings(Universe $universe): array
    {
        $stored=$universe->gameSettings['resource_boxes'] ?? [];
        $caps=array_replace(self::AMOUNTS['large'],array_intersect_key($stored['caps'] ?? [],self::AMOUNTS['large']));$templates=[];
        foreach (self::FORMATS as $format=>$label) $templates[$format]=array_replace(self::AMOUNTS[$format],array_intersect_key($stored['templates'][$format] ?? [],self::AMOUNTS[$format]));
        return ['caps'=>$caps,'templates'=>$templates];
    }

    public function snapshot(Universe $universe, string $format): array
    {
        if (!isset(self::FORMATS[$format])) throw new \DomainException('Format de boîte inconnu.');
        $settings=$this->settings($universe);$payload=$this->validatePayload(['format'=>$format,'resources'=>$settings['templates'][$format]]);
        foreach ($payload['resources'] as $slug=>$amount) if ($amount>$settings['caps'][$slug]) throw new \DomainException('Le modèle dépasse un plafond de cet univers.');
        return $payload;
    }

    public function validatePayload(array $payload): array
    {
        $format=$payload['format'] ?? '';
        if (!is_string($format) || !isset(self::FORMATS[$format]) || !is_array($payload['resources'] ?? null)) throw new \DomainException('Contenu de boîte invalide.');
        $resources=[];
        foreach ($this->catalog->rules()['resources'] as $slug=>$label) $resources[$slug]=$this->amount($payload['resources'][$slug] ?? null,$label);
        if (array_sum($resources)<=0) throw new \DomainException('Une boîte doit contenir au moins une ressource.');
        return ['format'=>$format,'label'=>self::FORMATS[$format],'resources'=>$resources];
    }

    public function saveSettings(Player $actor, Universe $universe, array $input, string $reason): void
    {
        $reason=trim($reason);
        if ($reason==='' || mb_strlen($reason)>1000) throw new \DomainException('Le motif est obligatoire, dans la limite de 1 000 caractères.');
        $caps=[];$templates=[];
        foreach ($this->catalog->rules()['resources'] as $slug=>$label) $caps[$slug]=$this->amount($input['caps'][$slug] ?? null,$label);
        foreach (self::FORMATS as $format=>$label) {
            $templates[$format]=$this->validatePayload(['format'=>$format,'resources'=>$input['templates'][$format] ?? []])['resources'];
            foreach ($templates[$format] as $slug=>$amount) if ($amount>$caps[$slug]) throw new \DomainException('Un modèle dépasse le plafond de '.$this->catalog->rules()['resources'][$slug].'.');
        }
        $this->manager->transaction(function () use ($actor,$universe,$caps,$templates,$reason): void {
            $this->manager->lock($actor);
            if (!in_array('ROLE_ADMIN',$actor->getRoles(),true) || $actor->lockedAt) throw new \DomainException('Droits administrateur requis.');
            $this->manager->lock($universe);$before=$this->settings($universe);$after=['caps'=>$caps,'templates'=>$templates];$universe->gameSettings['resource_boxes']=$after;
            $entry=new AdminAuditLog();$entry->actor=$actor;$entry->action='resource_box_settings';$entry->targetType='universe';$entry->targetId=$universe->id;$entry->createdAt=$this->clock->now();
            $entry->details=['reason'=>$reason,'before'=>$before,'after'=>$after];$this->em->persist($entry);
        });
    }

    private function amount(mixed $value,string $label): float
    {
        if (!is_scalar($value) || !is_numeric($value) || !is_finite((float)$value) || (float)$value<0 || (float)$value>1e9 || abs((float)$value-round((float)$value,2))>0.000001) throw new \DomainException('Quantité invalide pour '.$label.' : de 0 à 1 milliard, avec deux décimales au maximum.');
        return (float)$value;
    }
}
