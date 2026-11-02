<?php
namespace App\Controller;
use App\Entity\{PlayerUniverse,Guild,GuildMember};
use App\Service\Universe\UniverseContext;
use App\Service\Player\{RelationResolver,DiplomacyService};
use App\Service\Catalog\GameCatalog;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
final class DiplomacyController extends AbstractController
{
    #[Route('/diplomatie',name:'app_diplomacy',methods:['GET','POST'])]
    public function index(Request $request,UniverseContext $context,RelationResolver $relations,DiplomacyService $service,GameRepository $repo,GameCatalog $catalog): Response
    {
        $m=$context->membership();
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('diplomacy',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            try {
                $action=(string)$request->request->get('action');$target=(int)$request->request->get('target');
                match($action){'create'=>$service->create($m,(string)$request->request->get('name')),'join'=>$service->join($m,$target),'leave'=>$service->leave($m),'transfer'=>$service->transfer($m,$target),'disband'=>$service->disband($m),'relation'=>$service->relation($m,$target,(string)$request->request->get('status')),'propose','accept','break'=>$service->alliance($m,$target,$action),default=>throw new \DomainException('Action inconnue.')};
                $this->addFlash('success','Diplomatie mise à jour.');
            } catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
            return $this->redirectToRoute('app_diplomacy');
        }
        $players=[];foreach($repo->all(PlayerUniverse::class,['universe'=>$m->universe]) as $pu) if($pu->id!==$m->id) $players[]=['player'=>$pu,'contract'=>$relations->contract($m,$pu),'allied'=>$relations->allied($m,$pu)];
        $own=$relations->guild($m);$guilds=[];foreach($repo->all(Guild::class,['universe'=>$m->universe]) as $g) $guilds[]=['guild'=>$g,'status'=>$relations->guildStatus($own,$g),'members'=>$repo->all(GuildMember::class,['guild'=>$g])];
        return $this->render('player/diplomacy.html.twig',['membership'=>$m,'players'=>$players,'guilds'=>$guilds,'own'=>$own,'enabled'=>$catalog->forUniverse($m->universe)['features']['guilds_enabled']]);
    }
}
