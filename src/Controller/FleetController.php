<?php
namespace App\Controller;
use App\Entity\{Fleet,FleetShip,Planet,PlayerDiscovery};
use App\Repository\GameRepository;
use App\Service\Universe\UniverseContext;
use App\Service\Fleet\FleetService;
use App\Service\Combat\CombatEventService;
use App\Service\Catalog\GameCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
final class FleetController extends AbstractController
{
    #[Route('/flottes',name:'app_fleets',methods:['GET'])]
    public function index(Request $request,UniverseContext $context,GameRepository $repo,GameCatalog $catalog,\App\Service\Queue\QueueService $queues,CombatEventService $combats): Response
    {
        $m=$context->membership();$queues->synchronize($m);$idle=[];
        foreach($repo->all(Fleet::class,['playerUniverse'=>$m,'status'=>'idle']) as $f) { $ships=array_filter($repo->all(FleetShip::class,['fleet'=>$f]),fn($s)=>$s->quantity>0);if($ships) $idle[]=['fleet'=>$f,'ships'=>$ships]; }
        $systems=$repo->systems($m->universe);$mission=(string)$request->query->get('mission','explore');$universeConfig=$catalog->get('universes',$m->universe->slug);$conquestEnabled=(bool)($universeConfig['features']['planet_conquest']??false);
        $known=[];foreach($repo->all(PlayerDiscovery::class,['playerUniverse'=>$m]) as $d) $known[$d->solarSystem->id]=true;
        $targets=array_filter($repo->planets($m->universe),fn($p)=>isset($known[$p->solarSystem->id]));
        if($mission==='spy') $targets=array_filter($targets,fn($p)=>$p->owner?->id!==$m->id);
        if($mission==='attack') $targets=array_filter($targets,fn($p)=>$combats->canAttack($m,$p));
        if($mission==='colonize') $targets=array_filter($targets,fn($p)=>$p->owner===null&&!$p->npcPlanet);
        if($mission==='conquer') $targets=array_filter($targets,fn($p)=>$conquestEnabled&&$combats->canConquer($m,$p));
        if($mission==='defend') $targets=array_filter($targets,fn($p)=>$combats->canDefend($m,$p));
        if($mission==='orbit') $targets=array_filter($targets,fn($p)=>$combats->canOrbit($m,$p));
        if($mission==='conquer'&&count($repo->planets($m->universe,$m))>=$universeConfig['limits']['max_planets']) $targets=[];
        if(!in_array($mission,['explore','colonize','transport','attack','conquer','defend','orbit','spy'],true)) throw $this->createNotFoundException('Mission indisponible.');
        $systemId=$request->query->getInt('system');$planetId=$request->query->getInt('planet');
        if($systemId&&!array_filter($systems,fn($s)=>$s->id===$systemId)) throw $this->createNotFoundException('Système inaccessible.');
        if($planetId&&!array_filter($targets,fn($p)=>$p->id===$planetId&&($mission!=='transport'||$p->owner?->id===$m->id)&&($mission!=='colonize'||($p->owner===null&&!$p->npcPlanet)))) throw $this->createNotFoundException('Destination inaccessible.');
        $origin=$request->getSession()->get('selected_planet_'.$m->id,0);
        usort($idle,fn($a,$b)=>(($a['fleet']->homePlanet->id===$planetId?2:($a['fleet']->homePlanet->id===$origin?0:1)))<=>(($b['fleet']->homePlanet->id===$planetId?2:($b['fleet']->homePlanet->id===$origin?0:1))));
        $orbitRequests=[];foreach($repo->planets($m->universe,$m) as $p) foreach($repo->all(Fleet::class,['destination'=>$p,'status'=>'orbit_pending','mission'=>'orbit'],['id'=>'ASC']) as $f) $orbitRequests[]=$f;
        return $this->render('fleet/index.html.twig',['prefill'=>['mission'=>$mission,'system'=>$systemId,'planet'=>$planetId],'active_fleets'=>$repo->all(Fleet::class,['playerUniverse'=>$m,'status'=>['traveling','returning','defending','orbiting','orbit_pending']],['arrivalAt'=>'ASC']),'membership'=>$m,'fleets'=>$repo->all(Fleet::class,['playerUniverse'=>$m],['id'=>'DESC']),'idle'=>$idle,'systems'=>$systems,'targets'=>$targets,'catalog'=>$catalog->all('ships'),'resources'=>$catalog->rules()['resources'],'fuel_pending'=>$catalog->rules()['fuel_ratio_policy']==='pending','orbit_requests'=>$orbitRequests,'conquest_enabled'=>$conquestEnabled]);
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
            $orbitHours=0.0;
            if($mission==='orbit') {
                $orbitHours=filter_var($request->request->get('orbit_hours',0),FILTER_VALIDATE_FLOAT);
                if($orbitHours===false) throw new \DomainException('Durée d’orbite invalide.');
            }
            $result=$service->preview($m,$origin,$mission,(int)$request->request->get($mission==='explore'?'system':'planet'),$request->request->all('ships'),$ratio,$request->request->all('cargo'),$orbitHours);
            return $this->json($result,200,['Cache-Control'=>'private, no-store']);
        } catch(\DomainException $e) { return $this->json(['error'=>$e->getMessage()],422,['Cache-Control'=>'private, no-store']); }
    }
    #[Route('/flottes/orbite/{id}/repondre', name: 'app_orbit_decision', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function orbitDecision(int $id, Request $request, UniverseContext $context, FleetService $service): Response
    {
        if (!$this->isCsrfTokenValid('orbit-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        $decision = (string) $request->request->get('decision');
        if (!in_array($decision, ['accept', 'reject'], true)) {
            throw $this->createNotFoundException();
        }
        try {
            $service->decideOrbit($context->membership(), $id, $decision === 'accept');
            $this->addFlash('success', $decision === 'accept' ? 'Demande d’orbite acceptée.' : 'Demande d’orbite refusée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('app_fleets');
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
            $orbitHours=0.0;
            if($mission==='orbit') {
                $orbitHours=filter_var($request->request->get('orbit_hours',0),FILTER_VALIDATE_FLOAT);
                if($orbitHours===false) throw new \DomainException('Durée d’orbite invalide.');
            }
            $service->send($m,$origin,$mission,$target,$request->request->all('ships'),$ratio,$request->request->all('cargo'),(string)$request->request->get('request_key'),$orbitHours);
            $this->addFlash('success','Flotte envoyée. Carburant aller-retour réservé.');
        } catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        return $this->redirectToRoute('app_fleets');
    }
}
