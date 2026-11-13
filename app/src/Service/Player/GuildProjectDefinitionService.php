<?php
namespace App\Service\Player;

use App\Entity\{AdminAuditLog,GuildProjectDefinition,Player,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;

final class GuildProjectDefinitionService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private GameCatalog $catalog,private GuildProjectRules $rules,private ClockInterface $clock) {}

    public function save(Player $actor,int $id,array $draft,int $expectedVersion): GuildProjectDefinition
    {
        return $this->manager->transaction(function()use($actor,$id,$draft,$expectedVersion): GuildProjectDefinition {
            $this->manager->lock($actor);
            if(!in_array('ROLE_ADMIN',$actor->getRoles(),true) || $actor->lockedAt!==null) throw new \DomainException('Droits administrateur requis.');
            $data=$this->rules->definition($draft,$this->catalog->rules()['resources']);
            $universeId=filter_var($draft['universe_id']??0,FILTER_VALIDATE_INT);
            $universe=$universeId?$this->repo->find(Universe::class,$universeId):null;
            if(!$universe) throw new \DomainException('Choisissez un univers existant.');
            $definition=$id>0?$this->repo->find(GuildProjectDefinition::class,$id):new GuildProjectDefinition();
            if(!$definition) throw new \DomainException('Modèle de projet introuvable.');
            if($id>0) {
                $this->manager->lock($definition);
                if($definition->version!==$expectedVersion) throw new \DomainException('Le modèle a changé. Rechargez le formulaire.');
                if($definition->universe->id!==$universe->id) throw new \DomainException('L’univers d’un modèle existant ne peut pas changer.');
            } elseif($expectedVersion!==0) throw new \DomainException('Formulaire de création invalide.');
            $duplicate=$this->repo->one(GuildProjectDefinition::class,['universe'=>$universe,'name'=>$data['name']]);
            if($duplicate && $duplicate->id!==$definition->id) throw new \DomainException('Ce nom de projet existe déjà dans cet univers.');
            $before=$id>0?['name'=>$definition->name,'stages'=>$definition->stages,'minimums'=>$definition->minimums,'enabled'=>$definition->enabled]:null;
            $definition->universe=$universe;$definition->name=$data['name'];$definition->description=$data['description'];
            $definition->stages=$data['stages'];$definition->minimums=$data['minimums'];
            $definition->enabled=in_array($draft['enabled']??false,[true,1,'1'],true);
            $definition->createdAt??=$this->clock->now();$definition->updatedAt=$this->clock->now();
            $this->manager->save($definition);$this->manager->flush();
            $log=new AdminAuditLog();$log->actor=$actor;$log->action=$id>0?'guild_project_definition_updated':'guild_project_definition_created';
            $log->targetType='guild_project_definition';$log->targetId=$definition->id;$log->createdAt=$this->clock->now();
            $log->details=['universe_id'=>$universe->id,'before'=>$before,'after'=>$data+['enabled'=>$definition->enabled]];
            $this->manager->save($log);

            return $definition;
        });
    }
}
