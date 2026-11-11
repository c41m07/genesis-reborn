<?php
namespace App\Controller;
use App\Entity\{PlayerUniverse,Guild,GuildMember,GuildRank,GuildRelation};
use App\Service\Universe\UniverseContext;
use App\Service\Player\{RelationResolver,DiplomacyService,DiplomacyAccessPolicy,GuildRankService};
use App\Service\Catalog\GameCatalog;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
final class DiplomacyController extends AbstractController
{
    #[Route('/diplomatie',name:'app_diplomacy',methods:['GET','POST'])]
    public function index(Request $request,UniverseContext $context,RelationResolver $relations,DiplomacyService $service,GameRepository $repo,GameCatalog $catalog,DiplomacyAccessPolicy $access,GuildRankService $rankService): Response
    {
        $m=$context->membership();
        $search=mb_substr(trim($request->query->getString('q')),0,$request->query->getString('tab')==='diplomacy'?60:40);
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('diplomacy',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            try {
                $action=(string)$request->request->get('action');$target=(int)$request->request->get('target');
                match($action){'rank_save'=>$rankService->save($m,$target,$request->request->getString('name'),$request->request->getInt('position'),$request->request->all('permissions'),$request->request->getInt('version')),'rank_assign'=>$rankService->assign($m,$target,$request->request->getInt('rank'),$request->request->getInt('expected_rank')),'create'=>$service->create($m,(string)$request->request->get('name')),'join'=>$service->join($m,$target),'leave'=>$service->leave($m),'transfer'=>$service->transfer($m,$target),'disband'=>$service->disband($m),'relation'=>$service->relation($m,$target,(string)$request->request->get('status'),$request->request->getInt('relation_id'),$request->request->getInt('relation_version'),$request->request->getString('treaty_action','legacy')),'propose','accept','break','rival','peace_propose','peace_accept','peace_reject'=>$service->alliance($m,$target,$action),default=>throw new \DomainException('Action inconnue.')};
                $this->addFlash('success','Diplomatie mise à jour.');
            } catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
            return $this->redirectToRoute('app_diplomacy', ['q' => $search, 'tab' => match ($action) { 'relation' => 'diplomacy', 'propose', 'accept', 'break', 'rival', 'peace_propose', 'peace_accept', 'peace_reject' => 'relations', default => 'guilds' }]);
        }
        $own=$relations->guild($m);
        $players=[];$searchPlayers=[];
        foreach($repo->all(PlayerUniverse::class,['universe'=>$m->universe]) as $pu) {
            if($pu->id===$m->id || !$pu->isPlayerControlled()) continue;
            $contract=$relations->contract($m,$pu);
            $allied=$relations->allied($m,$pu);
            $enemy=$relations->enemy($m,$pu);
            if($contract || $allied || $enemy) $players[]=['player'=>$pu,'contract'=>$contract,'allied'=>$allied,'enemy'=>$enemy,'guildStatus'=>$relations->guildStatus($own,$relations->guild($pu))];
        }
        foreach($repo->searchMessageRecipients($m,$search) as $pu) $searchPlayers[]=['player'=>$pu,'contract'=>$relations->contract($m,$pu),'allied'=>$relations->allied($m,$pu),'enemy'=>$relations->enemy($m,$pu),'guildStatus'=>$relations->guildStatus($own,$relations->guild($pu))];
        $own=$relations->guild($m);$guilds=[];foreach($repo->all(Guild::class,['universe'=>$m->universe,'archivedAt'=>null]) as $g) $guilds[]=['guild'=>$g,'status'=>$relations->guildStatus($own,$g),'relation'=>$own && $own->id!==$g->id ? $repo->one(GuildRelation::class,$own->id<$g->id ? ['guildA'=>$own,'guildB'=>$g] : ['guildA'=>$g,'guildB'=>$own]) : null,'members'=>$repo->all(GuildMember::class,['guild'=>$g]),'history'=>$own && $own->id!==$g->id ? $repo->guildTreatyHistory($own,$g) : []];
        $guildRelations=[];$guildSearchResults=[];
        foreach($guilds as $row) {
            if($own && $row['guild']->id===$own->id) continue;
            if($own && ($row['status']!=='neutral' || $row['relation']?->proposal!==null)) $guildRelations[]=$row;
            if(mb_strlen($search)>=2 && mb_stripos($row['guild']->name,$search)!==false && count($guildSearchResults)<20) $guildSearchResults[]=$row;
        }
        $ownMember=$own ? $repo->one(GuildMember::class,['playerUniverse'=>$m]) : null;
        $ranks=$own ? $repo->all(GuildRank::class,['guild'=>$own],['position'=>'ASC','id'=>'ASC']) : [];
        $rankAssignments=[];
        if($ownMember) foreach($repo->all(GuildMember::class,['guild'=>$own]) as $member) {
            if(!$access->canAssignRank($ownMember,$member,null)) continue;
            $rankAssignments[]=['member'=>$member,'choices'=>array_values(array_filter($ranks,fn(GuildRank $rank): bool=>$access->canAssignRank($ownMember,$member,$rank)))];
        }
        $guildPermissions=[];
        foreach(DiplomacyAccessPolicy::PERMISSIONS as $permission=>$label) $guildPermissions[$permission]=$access->allows($ownMember,$permission);
        return $this->render('player/diplomacy.html.twig',['ownMember'=>$ownMember,'ranks'=>$ranks,'rankAssignments'=>$rankAssignments,'guildPermissions'=>$guildPermissions,'rankPermissionLabels'=>DiplomacyAccessPolicy::PERMISSIONS,'membership'=>$m,'search'=>$search,'searchPlayers'=>$searchPlayers,'players'=>$players,'guilds'=>$guilds,'guildRelations'=>$guildRelations,'guildSearchResults'=>$guildSearchResults,'guildPreview'=>$own ? $repo->guildConversation($own,3) : [],'own'=>$own,'enabled'=>$catalog->forUniverse($m->universe)['features']['guilds_enabled']]);
    }
}
