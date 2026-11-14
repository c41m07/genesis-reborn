<?php
namespace App\Controller;

use App\Entity\{GuildMember,GuildProject,GuildProjectContribution,GuildProjectDefinition,PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Boost\BoostGrantService;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\{DiplomacyAccessPolicy,GuildProjectRules,GuildProjectService,RelationResolver,VacationCalendar};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class GuildProjectController extends AbstractController
{
    #[Route('/guildes/projets',name:'app_guild_projects',methods:['GET'])]
    public function index(UniverseContext $context,GameRepository $repo,GameCatalog $catalog,RelationResolver $relations,DiplomacyAccessPolicy $access,GuildProjectRules $rules,BoostGrantService $boosts,VacationCalendar $vacations): Response
    {
        $actor=$context->membership();$guild=$relations->guild($actor);
        $enabled=(bool)$catalog->forUniverse($actor->universe)['features']['guilds_enabled'];
        $readOnly=$vacations->isActive($actor);$rows=[];$launched=[];$available=[];
        if($guild && $enabled) {
            foreach($repo->all(GuildProject::class,['guildId'=>$guild->id,'universe'=>$actor->universe],['id'=>'DESC']) as $project) {
                $launched[$project->definition->id]=true;
                $rows[]=$this->projectRow($project,$actor,$repo,$rules,$boosts);
            }
            foreach($repo->all(GuildProjectDefinition::class,['universe'=>$actor->universe,'enabled'=>true]) as $definition) {
                if(!isset($launched[$definition->id])) $available[]=['definition'=>$definition,'rewarded'=>$boosts->hasGuildProjectReward($actor,$definition->id)];
            }
        }
        $member=$guild?$repo->one(GuildMember::class,['playerUniverse'=>$actor]):null;
        return $this->render('player/guild_projects.html.twig',['membership'=>$actor,'guild'=>$guild,'enabled'=>$enabled,'readOnly'=>$readOnly,'canStart'=>!$readOnly && $access->allows($member,'projects.start'),'rows'=>$rows,'available'=>$available,'planets'=>$guild?$repo->planets($actor->universe,$actor):[],'resources'=>$catalog->rules()['resources']]);
    }

    #[Route('/guildes/projets/{id}',name:'app_guild_project',requirements:['id'=>'\d+'],methods:['GET'])]
    public function show(int $id,UniverseContext $context,GameRepository $repo,GameCatalog $catalog,RelationResolver $relations,GuildProjectRules $rules,BoostGrantService $boosts,VacationCalendar $vacations): Response
    {
        $actor=$context->membership();$guild=$relations->guild($actor);
        $project=$repo->find(GuildProject::class,$id);
        if(!$guild || !$catalog->forUniverse($actor->universe)['features']['guilds_enabled'] || !$project || $project->guildId!==$guild->id || $project->universe->id!==$actor->universe->id) throw $this->createNotFoundException('Projet inaccessible.');
        return $this->render('player/guild_project.html.twig',[
            'membership'=>$actor,'guild'=>$guild,'project'=>$project,'row'=>$this->projectRow($project,$actor,$repo,$rules,$boosts),
            'readOnly'=>$vacations->isActive($actor),'planets'=>$repo->planets($actor->universe,$actor),'resources'=>$catalog->rules()['resources'],
        ]);
    }

    private function projectRow(GuildProject $project,PlayerUniverse $actor,GameRepository $repo,GuildProjectRules $rules,BoostGrantService $boosts): array
    {
        $contributions=[];$own=[];
        foreach($repo->all(GuildProjectContribution::class,['project'=>$project]) as $row) {
            $player=$repo->find(PlayerUniverse::class,$row->playerUniverseId);
            $contributions[]=['name'=>$player?->getDisplayName()??'Ancien membre #'.$row->playerUniverseId,'resources'=>$row->resources];
            if($row->playerUniverseId===$actor->id) $own=$row->resources;
        }
        return ['project'=>$project,'remaining'=>$project->status==='active'?$rules->remaining($project->snapshot['stages'][$project->stageIndex]['costs'],$project->progress):[],
            'contributions'=>$contributions,'own'=>$own,'qualified'=>$rules->eligible($own,$project->snapshot['minimums']),
            'rewarded'=>$boosts->hasGuildProjectReward($actor,$project->definition->id)];
    }

    #[Route('/guildes/projets/modeles/{id}/lancer',name:'app_guild_project_start',requirements:['id'=>'\d+'],methods:['POST'])]
    public function start(int $id,Request $request,UniverseContext $context,GuildProjectService $projects): Response
    {
        if(!$this->isCsrfTokenValid('guild_project_start_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try { $project=$projects->start($context->membership(),$id,$request->request->getInt('version'));$this->addFlash('success','Projet lancé. Les membres peuvent maintenant contribuer.'); }
        catch(\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return isset($project)?$this->redirectToRoute('app_guild_project',['id'=>$project->id]):$this->redirectToRoute('app_guild_projects');
    }

    #[Route('/guildes/projets/{id}/contribuer',name:'app_guild_project_contribute',requirements:['id'=>'\d+'],methods:['POST'])]
    public function contribute(int $id,Request $request,UniverseContext $context,GuildProjectService $projects): Response
    {
        if(!$this->isCsrfTokenValid('guild_project_donation_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try {
            $completed=$projects->contribute($context->membership(),$id,$request->request->getInt('planet_id'),$request->request->all('resources'),$request->request->getInt('version'));
            $this->addFlash('success',$completed?'Don enregistré. Le projet est terminé.':'Don enregistré. Merci pour votre contribution.');
        } catch(\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_guild_project',['id'=>$id]);
    }
}
