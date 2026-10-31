<?php
namespace App\Controller;
use App\Entity\{Fleet,FleetShip,Planet,PlayerDiscovery};
use App\Repository\GameRepository;
use App\Service\Universe\UniverseContext;
use App\Service\Fleet\FleetService;
use App\Service\Catalog\GameCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
final class FleetController extends AbstractController
{
    #[Route('/flottes',name:'app_fleets',methods:['GET'])]
    public function index(Request $request,UniverseContext $context,GameRepository $repo,GameCatalog $catalog,\App\Service\Queue\QueueService $queues): Response
    {
        $m=$context->membership();$queues->synchronize($m);$idle=[];
        foreach($repo->all(Fleet::class,['playerUniverse'=>$m,'status'=>'idle']) as $f) { $ships=array_filter($repo->all(FleetShip::class,['fleet'=>$f]),fn($s)=>$s->quantity>0);if($ships) $idle[]=['fleet'=>$f,'ships'=>$ships]; }
        $known=[];foreach($repo->all(PlayerDiscovery::class,['playerUniverse'=>$m]) as $d) $known[$d->solarSystem->id]=true;
        $targets=array_filter($repo->planets($m->universe),fn($p)=>isset($known[$p->solarSystem->id]));
        $systems=$repo->systems($m->universe);$mission=(string)$request->query->get('mission','explore');
        if(!in_array($mission,['explore','colonize','transport'],true)) throw $this->createNotFoundException('Mission indisponible.');
        $systemId=$request->query->getInt('system');$planetId=$request->query->getInt('planet');
        if($systemId&&!array_filter($systems,fn($s)=>$s->id===$systemId)) throw $this->createNotFoundException('Système inaccessible.');
        if($planetId&&!array_filter($targets,fn($p)=>$p->id===$planetId&&($mission!=='transport'||$p->owner?->id===$m->id)&&($mission!=='colonize'||$p->owner===null))) throw $this->createNotFoundException('Destination inaccessible.');
        $origin=$request->getSession()->get('selected_planet_'.$m->id,0);
        usort($idle,fn($a,$b)=>(($a['fleet']->homePlanet->id===$planetId?2:($a['fleet']->homePlanet->id===$origin?0:1)))<=>(($b['fleet']->homePlanet->id===$planetId?2:($b['fleet']->homePlanet->id===$origin?0:1))));
        return $this->render('fleet/index.html.twig',['prefill'=>['mission'=>$mission,'system'=>$systemId,'planet'=>$planetId],'active_fleets'=>$repo->all(Fleet::class,['playerUniverse'=>$m,'status'=>['traveling','returning']],['arrivalAt'=>'ASC']),'membership'=>$m,'fleets'=>$repo->all(Fleet::class,['playerUniverse'=>$m],['id'=>'DESC']),'idle'=>$idle,'systems'=>$systems,'targets'=>$targets,'catalog'=>$catalog->all('ships'),'resources'=>$catalog->rules()['resources'],'fuel_pending'=>$catalog->rules()['fuel_ratio_policy']==='pending']);
    }
    #[Route('/flottes/estimer',name:'app_fleet_preview',methods:['POST'])]
    public function preview(Request $request,UniverseContext $context,GameRepository $repo,FleetService $service): Response
    {
        if(!$this->isCsrfTokenValid('fleet',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try {
            $m=$context->membership();$origin=$repo->find(Planet::class,(int)$request->request->get('origin'));
            if(!$origin || $origin->owner?->id!==$m->id) throw new \DomainException('Origine inaccessible.');
            $mission=(string)$request->request->get('mission');$ratio=filter_var($request->request->get('ratio'),FILTER_VALIDATE_FLOAT);
            if($ratio===false) throw new \DomainException('Vitesse invalide.');
            $result=$service->preview($m,$origin,$mission,(int)$request->request->get($mission==='explore'?'system':'planet'),$request->request->all('ships'),$ratio,$request->request->all('cargo'));
            return $this->json($result,200,['Cache-Control'=>'private, no-store']);
        } catch(\DomainException $e) { return $this->json(['error'=>$e->getMessage()],422,['Cache-Control'=>'private, no-store']); }
    }
    #[Route('/flottes/envoyer',name:'app_fleet_send',methods:['POST'])]
    public function send(Request $request,UniverseContext $context,GameRepository $repo,FleetService $service): Response
    {
        if(!$this->isCsrfTokenValid('fleet',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $m=$context->membership();
        try {
            $origin=$repo->find(Planet::class,(int)$request->request->get('origin'));
            if(!$origin || $origin->owner?->id!==$m->id) throw new \DomainException('Origine inaccessible.');
            $mission=(string)$request->request->get('mission');
            $target=(int)$request->request->get($mission==='explore'?'system':'planet');
            $ratio=filter_var($request->request->get('ratio'),FILTER_VALIDATE_FLOAT);
            if($ratio===false) throw new \DomainException('Vitesse invalide.');
            $service->send($m,$origin,$mission,$target,$request->request->all('ships'),$ratio,$request->request->all('cargo'),(string)$request->request->get('request_key'));
            $this->addFlash('success','Flotte envoyée. Carburant aller-retour réservé.');
        } catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        return $this->redirectToRoute('app_fleets');
    }
}
