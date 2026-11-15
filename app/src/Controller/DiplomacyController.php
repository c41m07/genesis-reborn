<?php
namespace App\Controller;
use App\Entity\{Guild,GuildMember,GuildRank};
use App\Service\Universe\UniverseContext;
use App\Service\Player\{RelationResolver,DiplomacyService,DiplomacyAccessPolicy,GuildRankService,GuildPresence};
use App\Service\Catalog\GameCatalog;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
final class DiplomacyController extends AbstractController
{
    #[Route('/diplomatie',name:'app_diplomacy',methods:['GET','POST'])]
    public function index(Request $request,UniverseContext $context,RelationResolver $relations,DiplomacyService $service,GameRepository $repo,GameCatalog $catalog,DiplomacyAccessPolicy $access,GuildRankService $rankService,GuildPresence $presence): Response
    {
        $m=$context->membership();
        $search=mb_substr(trim($request->query->getString('q')),0,$request->query->getString('tab')==='relations'?40:60);
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('diplomacy',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $updated=false;
            try {
                $action=(string)$request->request->get('action');$target=(int)$request->request->get('target');
                match($action){'rank_save'=>$rankService->save($m,$target,$request->request->getString('name'),$request->request->getInt('position'),$request->request->all('permissions'),$request->request->getInt('version')),'rank_assign'=>$rankService->assign($m,$target,$request->request->getInt('rank'),$request->request->getInt('expected_rank')),'create'=>$service->create($m,(string)$request->request->get('name'),$request->request->getString('tag')),'tag'=>$service->setTag($m,$request->request->getString('tag')),'join'=>$service->join($m,$target),'leave'=>$service->leave($m),'transfer'=>$service->transfer($m,$target),'disband'=>$service->disband($m),'relation'=>$service->relation($m,$target,(string)$request->request->get('status'),$request->request->getInt('relation_id'),$request->request->getInt('relation_version'),$request->request->getString('treaty_action','legacy')),'propose','accept','break','rival','peace_propose','peace_accept','peace_reject'=>$service->alliance($m,$target,$action),default=>throw new \DomainException('Action inconnue.')};
                $updated=true;
                $this->addFlash('success','Diplomatie mise à jour.');
            } catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
            $redirect=['q'=>$search,'tab'=>match($action){'rank_save'=>'ranks','rank_assign'=>'members','join'=>$updated ? 'guilds' : 'join','relation'=>'diplomacy','propose','accept','break','rival','peace_propose','peace_accept','peace_reject'=>'relations',default=>'guilds'}];
            if($action==='rank_save') {
                $redirect['rank']=$target;
                if($updated) {
                    $guild=$relations->guild($m);
                    $saved=$guild ? $repo->one(GuildRank::class,['guild'=>$guild,'name'=>trim($request->request->getString('name'))]) : null;
                    if($saved) $redirect['rank']=$saved->id;
                }
            }
            return $this->redirectToRoute('app_diplomacy',$redirect);
        }
        $own=$relations->guild($m);
        // Valider l’onglet avant de préparer ses listes et formulaires.
        $tabs=$own ? ['guilds'=>'Ma guilde','members'=>'Membres','ranks'=>'Grades et permissions','diplomacy'=>'Relations guildes','relations'=>'Relations commandants'] : ['guilds'=>'Créer une guilde','join'=>'Rejoindre une guilde','relations'=>'Relations commandants'];
        $tab=$request->query->getString('tab','guilds');
        if(!$own && ($tab==='diplomacy' || ($tab==='guilds' && $search!==''))) $tab='join';
        if(!isset($tabs[$tab])) $tab='guilds';
        $incomingPersonalCount=0;
        $personalContracts=$repo->personalContracts($m);
        $guildRelationsByPeer=$own ? $repo->guildRelations($own) : [];
        foreach($personalContracts as $contract) {
            $peer=$contract->playerA->id===$m->id ? $contract->playerB : $contract->playerA;
            if(in_array($contract->status,['pending','peace_pending'],true) && $contract->proposer->id!==$m->id && $peer->isPlayerControlled() && $peer->universe->id===$m->universe->id) ++$incomingPersonalCount;
        }
        $players=[];$searchPlayers=[];
        if($tab==='relations') {
            $rows=$relations->displayRows($m,$repo->membershipIdentities($m->universe),$own,$personalContracts,$guildRelationsByPeer);
            $players=array_values(array_filter($rows,static fn(array $row):bool=>$row['contract'] || $row['allied'] || $row['enemy']));
            foreach($repo->searchMessageRecipients($m,$search) as $player) if(isset($rows[$player->id])) $searchPlayers[]=$rows[$player->id];
        }
        $guilds=[];
        foreach($repo->all(Guild::class,['universe'=>$m->universe,'archivedAt'=>null]) as $guild) {
            $isOwn=$own?->id===$guild->id;
            $relation=$own && !$isOwn ? ($guildRelationsByPeer[$guild->id]??null) : null;
            $status=$isOwn ? 'allies' : ($relation?->status??'neutral');
            $matchesSearch=mb_strlen($search)>=2 && mb_stripos($guild->name,$search)!==false;
            $showDetails=($isOwn && in_array($tab,['guilds','members'],true))
                || (!$isOwn && ($tab==='diplomacy' && ($status!=='neutral' || $relation?->proposal!==null || $matchesSearch)))
                || ($tab==='join' && $matchesSearch);
            $guilds[]=['guild'=>$guild,'status'=>$status,'relation'=>$relation,
                'members'=>$showDetails ? $repo->all(GuildMember::class,['guild'=>$guild]) : [],
                'history'=>$showDetails && $own && !$isOwn ? $repo->guildTreatyHistory($own,$guild) : []];
        }
        $guildRelations=[];$guildSearchResults=[];
        foreach($guilds as $row) {
            if($own && $row['guild']->id===$own->id) continue;
            if($own && ($row['status']!=='neutral' || $row['relation']?->proposal!==null)) $guildRelations[]=$row;
            if(mb_strlen($search)>=2 && mb_stripos($row['guild']->name,$search)!==false && count($guildSearchResults)<20) $guildSearchResults[]=$row;
        }
        $ownMember=$own ? $repo->one(GuildMember::class,['playerUniverse'=>$m]) : null;
        $ranks=$own && in_array($tab,['members','ranks'],true) ? $repo->all(GuildRank::class,['guild'=>$own],['position'=>'ASC','id'=>'ASC']) : [];
        $rankAssignments=[];
        if($ownMember && $tab==='members') foreach($repo->all(GuildMember::class,['guild'=>$own]) as $member) {
            if(!$access->canAssignRank($ownMember,$member,null)) continue;
            $rankAssignments[]=['member'=>$member,'choices'=>array_values(array_filter($ranks,fn(GuildRank $rank): bool=>$access->canAssignRank($ownMember,$member,$rank)))];
        }
        $ownRow=null;
        foreach($guilds as $row) if($own && $row['guild']->id===$own->id) $ownRow=$row;
        $selectedRank=$ranks[0]??null;
        if($request->query->has('rank')) {
            $selectedId=$request->query->getInt('rank');
            if($selectedId===0) $selectedRank=null;
            else foreach($ranks as $rank) if($rank->id===$selectedId) $selectedRank=$rank;
        }
        $guildPermissions=[];
        foreach(DiplomacyAccessPolicy::PERMISSIONS as $permission=>$label) $guildPermissions[$permission]=$access->allows($ownMember,$permission);
        $memberPresence=$tab==='members' && $own && $ownRow && $catalog->forUniverse($m->universe)['features']['guilds_enabled'] ? $presence->forMember($m) : [];
        $response=$this->render('player/diplomacy.html.twig',['incomingPersonalCount'=>$incomingPersonalCount,'memberPresence'=>$memberPresence,'tabs'=>$tabs,'tab'=>$tab,'ownRow'=>$ownRow,'selectedRank'=>$selectedRank,'ownMember'=>$ownMember,'ranks'=>$ranks,'rankAssignments'=>$rankAssignments,'guildPermissions'=>$guildPermissions,'rankPermissionLabels'=>DiplomacyAccessPolicy::PERMISSIONS,'membership'=>$m,'search'=>$search,'searchPlayers'=>$searchPlayers,'players'=>$players,'guilds'=>$guilds,'guildRelations'=>$guildRelations,'guildSearchResults'=>$guildSearchResults,'guildPreview'=>$own && $tab==='guilds' ? $repo->guildConversation($own,3) : [],'own'=>$own,'enabled'=>$catalog->forUniverse($m->universe)['features']['guilds_enabled']]);
        $response->headers->set('Cache-Control','private, no-store');
        return $response;
    }
}
