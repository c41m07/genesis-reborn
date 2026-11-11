<?php
namespace App\Service\Player;

use App\Entity\{Guild,GuildMember,GuildProject,GuildProjectContribution,GuildProjectDefinition,OutboxMessage,Planet,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{ActionGuard,ResourceService};
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final class GuildProjectService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private ActionGuard $guard,private ResourceService $resources,private GameCatalog $catalog,private DiplomacyAccessPolicy $access,private GuildProjectRules $rules,private VacationCalendar $vacations,private ClockInterface $clock) {}

    private function member(PlayerUniverse $actor): GuildMember
    {
        $this->guard->lockMembership($actor);
        if($this->vacations->isActive($actor)) throw new \DomainException('Les projets sont indisponibles pendant les vacances.');
        if(!$this->catalog->forUniverse($actor->universe)['features']['guilds_enabled']) throw new \DomainException('Les guildes sont désactivées dans cet univers.');
        $member=$this->repo->one(GuildMember::class,['playerUniverse'=>$actor]);
        if(!$member) throw new \DomainException('Rejoignez une guilde pour participer à ses projets.');
        $this->manager->lock($member);$this->manager->lock($member->guild);
        if($member->rank) $this->manager->lock($member->rank);
        if($member->guild->archivedAt!==null || $member->guild->universe->id!==$actor->universe->id) throw new \DomainException('Guilde inaccessible.');

        return $member;
    }

    public function start(PlayerUniverse $actor,int $definitionId,int $expectedVersion): GuildProject
    {
        return $this->manager->transaction(function()use($actor,$definitionId,$expectedVersion): GuildProject {
            $member=$this->member($actor);
            if(!$this->access->allows($member,'projects.start')) throw new \DomainException('Permission de lancement des projets requise.');
            $definition=$this->repo->find(GuildProjectDefinition::class,$definitionId);
            if(!$definition || $definition->universe->id!==$actor->universe->id) throw new \DomainException('Projet inaccessible dans cet univers.');
            $this->manager->lock($definition);
            if(!$definition->enabled || $definition->version!==$expectedVersion) throw new \DomainException('Le modèle a changé ou n’est plus disponible. Rechargez la page.');
            if($this->repo->one(GuildProject::class,['guildId'=>$member->guild->id,'definition'=>$definition])) throw new \DomainException('Votre guilde a déjà lancé ce projet. Il ne peut pas être recommencé.');
            $project=new GuildProject();$project->universe=$actor->universe;$project->definition=$definition;
            $project->guildId=$member->guild->id;$project->guildName=$member->guild->name;$project->startedAt=$this->clock->now();
            $project->snapshot=['name'=>$definition->name,'description'=>$definition->description,'stages'=>$definition->stages,'minimums'=>$definition->minimums,'version'=>$definition->version];
            $this->manager->save($project);

            return $project;
        });
    }

    public function contribute(PlayerUniverse $actor,int $projectId,int $planetId,array $amounts,int $expectedVersion): bool
    {
        return $this->manager->transaction(function()use($actor,$projectId,$planetId,$amounts,$expectedVersion): bool {
            $member=$this->member($actor);
            $project=$this->repo->find(GuildProject::class,$projectId);
            if(!$project || $project->guildId!==$member->guild->id || $project->universe->id!==$actor->universe->id) throw new \DomainException('Projet inaccessible à votre guilde.');
            $this->manager->lock($project);
            if($project->status!=='active') throw new \DomainException('Ce projet n’accepte plus de dons.');
            if($project->version!==$expectedVersion) throw new \DomainException('Le projet a progressé. Rechargez la page avant de donner.');
            $planet=$this->repo->find(Planet::class,$planetId);
            if(!$planet) throw new \DomainException('Colonie inaccessible.');
            $this->guard->lock($actor,$planet);
            $costs=$project->snapshot['stages'][$project->stageIndex]['costs'];
            $remaining=$this->rules->remaining($costs,$project->progress);
            $paid=$this->rules->quantities($amounts,$this->catalog->rules()['resources'],GuildProjectRules::MAX_STAGE_AMOUNT);
            foreach($paid as $resource=>$amount) if($amount>($remaining[$resource]??0)) throw new \DomainException('Le don dépasse les besoins restants du palier pour '.$this->catalog->rules()['resources'][$resource].'.');
            $this->resources->consume($planet,$paid);
            $contribution=$this->repo->one(GuildProjectContribution::class,['project'=>$project,'playerUniverseId'=>$actor->id]);
            if(!$contribution) { $contribution=new GuildProjectContribution();$contribution->project=$project;$contribution->playerUniverseId=$actor->id;$this->manager->save($contribution); }
            foreach($paid as $resource=>$amount) {
                $project->progress[$resource]=($project->progress[$resource]??0)+$amount;
                $contribution->resources[$resource]=($contribution->resources[$resource]??0)+$amount;
            }
            $contribution->planetId=$planet->id;$contribution->updatedAt=$this->clock->now();
            if(array_sum($this->rules->remaining($costs,$project->progress))===0) {
                ++$project->stageIndex;$project->progress=[];
                if($project->stageIndex===count($project->snapshot['stages'])) $this->complete($project,$member->guild,$contribution);
            }

            return $project->status==='completed';
        });
    }

    private function complete(GuildProject $project,Guild $guild,GuildProjectContribution $current): void
    {
        $contributions=[];
        foreach($this->repo->all(GuildProjectContribution::class,['project'=>$project]) as $row) $contributions[$row->playerUniverseId]=$row->resources;
        $contributions[$current->playerUniverseId]=$current->resources;
        $eligible=[];
        foreach($this->repo->all(GuildMember::class,['guild'=>$guild]) as $member) {
            $player=$member->playerUniverse;
            if($player->isPlayerControlled() && $player->universe->id===$project->universe->id && $this->rules->eligible($contributions[$player->id]??[],$project->snapshot['minimums'])) $eligible[]=$player->id;
        }
        sort($eligible,SORT_NUMERIC);
        $project->eligibleIds=$eligible;$project->status='completed';$project->completedAt=$this->clock->now();
        if($eligible===[]) { $project->rewardsDeliveredAt=$this->clock->now();return; }
        $project->rewardToken=Uuid::v4()->toRfc4122();
        $outbox=new OutboxMessage();$outbox->kind='guild_project';$outbox->targetId=$project->id;$outbox->token=$project->rewardToken;$outbox->dueAt=$this->clock->now();
        $this->manager->save($outbox);
    }

    /** Appelé dans la transaction de dissolution ou d’archivage, guilde verrouillée. */
    public function abandonForGuild(Guild $guild): void
    {
        foreach($this->repo->all(GuildProject::class,['guildId'=>$guild->id,'universe'=>$guild->universe,'status'=>'active']) as $project) {
            $this->manager->lock($project);$project->status='abandoned';
        }
    }
}
