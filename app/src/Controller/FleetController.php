<?php
namespace App\Controller;
use App\Entity\{Fleet,FleetShip,Planet,PlayerDiscovery,SolarSystem,CombatParticipant};
use App\Repository\GameRepository;
use App\Service\Universe\UniverseContext;
use App\Service\Fleet\{FleetService,DestinationCatalogue};
use App\Service\Player\PreferencesService;
use App\Service\Combat\CombatEventService;
use App\Service\Catalog\GameCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
final class FleetController extends AbstractController
{
    #[Route('/flottes',name:'app_fleets',methods:['GET'])]
    public function index(Request $request,UniverseContext $context,GameRepository $repo,GameCatalog $catalog,\App\Service\Queue\QueueService $queues,CombatEventService $combats,\App\Service\Colony\EmpireDetails $details,DestinationCatalogue $destinations,\App\Service\Fleet\OrbitRequestView $orbitView,\App\Service\Fleet\FleetCompositionService $compositions): Response
    {
        $m=$context->membership();$queues->synchronize($m);$idle=[];
        foreach($repo->all(Fleet::class,['playerUniverse'=>$m,'status'=>'idle']) as $f) { $ships=array_filter($repo->all(FleetShip::class,['fleet'=>$f]),fn($s)=>$s->quantity>0);if($ships) $idle[]=['fleet'=>$f,'ships'=>$ships]; }
        $systems=$repo->systems($m->universe);$mission=(string)$request->query->get('mission','explore');$universeConfig=$catalog->forUniverse($m->universe);$conquestEnabled=(bool)($universeConfig['features']['planet_conquest']??false);
        if(!in_array($mission,DestinationCatalogue::MISSIONS,true)) throw $this->createNotFoundException('Mission indisponible.');
        $destinationChoices=$destinations->choices($m,$mission);
        $systemId=$request->query->getInt('system');$planetId=$request->query->getInt('planet');
        if($systemId&&!array_filter($systems,fn($system)=>$system->id===$systemId)) throw $this->createNotFoundException('Système inaccessible.');
        $planet=$planetId?$repo->find(Planet::class,$planetId):null;
        if($planetId && (!$planet instanceof Planet || !$destinations->canPrefill($m,$planet,$mission))) throw $this->createNotFoundException('Destination inaccessible.');
        $prefillCoordinates='';$mode='friendly';
        if($mission==='explore' && $systemId) {
            foreach($systems as $system) if($system->id===$systemId) {$prefillCoordinates=$system->galaxy->coordinateCode().':'.$system->number();break;}
            $mode='custom';
        } elseif($planet) {
            $prefillCoordinates=$mission==='explore'?$planet->solarSystem->galaxy->coordinateCode().':'.$planet->solarSystem->number():$planet->coordinates();$mode=$destinations->allows($m,$planet,$mission)?$destinations->category($m,$planet):'custom';
        }
        $origin=$request->getSession()->get('selected_planet_'.$m->id,0);
        usort($idle,fn($a,$b)=>(($a['fleet']->homePlanet->id===$planetId?2:($a['fleet']->homePlanet->id===$origin?0:1)))<=>(($b['fleet']->homePlanet->id===$planetId?2:($b['fleet']->homePlanet->id===$origin?0:1))));
        $activeFleets=$repo->all(Fleet::class,['playerUniverse'=>$m,'status'=>['traveling','returning','attacking','defending','orbiting','orbit_pending','occupying']],['arrivalAt'=>'ASC']);$combatEvents=[];foreach($activeFleets as $activeFleet){$participant=$repo->one(CombatParticipant::class,['fleet'=>$activeFleet]);if($participant && $participant->combatEvent->status==='pending')$combatEvents[$activeFleet->id]=$participant->combatEvent;}
        $loaded=[];$loadedOrigin=0;
        if($request->query->getInt('composition')) {
            try {
                $saved=$compositions->owned($m,$request->query->getInt('composition'));
                $source=$repo->find(Planet::class,$request->query->getInt('origin'));
                if(!$source) throw new \DomainException('Colonie inaccessible.');
                $plan=$compositions->plan($m,$saved,$source);
                if(!hash_equals($plan['fingerprint'],$request->query->getString('confirmation')) || ($plan['missing'] && !$request->query->getBoolean('adjust'))) {
                    $this->addFlash('error','Les disponibilités ou la composition ont changé. Vérifiez les quantités avant de charger.');
                    return $this->redirectToRoute('app_fleet_composition_load',['id'=>$saved->id,'origin'=>$source->id]);
                }
                $loaded=$plan['missing']?$plan['available']:$saved->ships;$loadedOrigin=$source->id;
            } catch(\DomainException) {throw $this->createNotFoundException('Composition ou colonie inaccessible.');}
        }
        $orbitRequests=$orbitView->forMember($m);
        return $this->render('fleet/index.html.twig',['loaded_composition'=>$loaded,'loaded_origin'=>$loadedOrigin,'fleet_details'=>$details->fleets($m,$activeFleets),'prefill'=>['mission'=>$mission,'system'=>$systemId,'planet'=>$planetId,'coordinates'=>$prefillCoordinates,'mode'=>$mode],'active_fleets'=>$activeFleets,'combat_events'=>$combatEvents,'membership'=>$m,'fleets'=>$repo->all(Fleet::class,['playerUniverse'=>$m],['id'=>'DESC']),'idle'=>$idle,'systems'=>$systems,'destination_choices'=>$destinationChoices,'catalog'=>$catalog->all('ships'),'resources'=>$catalog->rules()['resources'],'fuel_pending'=>$catalog->rules()['fuel_ratio_policy']==='pending','orbit_requests'=>$orbitRequests,'fleet_limit'=>(int)$universeConfig['limits']['max_fleet_size'],'conquest_enabled'=>$conquestEnabled]);
    }
    #[Route('/flottes/destinations',name:'app_fleet_destinations',methods:['GET'])]
    public function destinations(Request $request,UniverseContext $context,DestinationCatalogue $destinations): Response
    {
        $membership=$context->membership();
        try {
            $data=$destinations->choices($membership,$request->query->getString('mission','explore'),
                $request->query->has('q')?$request->query->getString('q'):null);
            return $this->json($data,200,['Cache-Control'=>'private, no-store']);
        } catch(\DomainException $error) {
            return $this->json(['error'=>$error->getMessage()],422,['Cache-Control'=>'private, no-store']);
        }
    }

    #[Route('/flottes/mission-rapide',name:'app_fleet_quick_send',methods:['POST'])]
    public function quickSend(Request $request,UniverseContext $context,GameRepository $repo,FleetService $service,PreferencesService $preferences,GameCatalog $catalog): Response
    {
        $membership=$context->membership();$mission=(string)$request->request->get('mission','');$targetId=filter_var($request->request->get('target'),FILTER_VALIDATE_INT);
        if(!in_array($mission,['explore','spy','colonize'],true) || $targetId===false || $targetId<1) throw $this->createNotFoundException('Mission directe invalide.');
        if(!$this->isCsrfTokenValid('map-mission-'.$mission.'-'.$targetId,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $system=$mission==='explore'?$repo->find(SolarSystem::class,$targetId):null;
        $planet=$mission==='explore'?null:$repo->find(Planet::class,$targetId);
        $destinationSystem=$system??$planet?->solarSystem;
        try {
            $origin=$repo->find(Planet::class,(int)$request->request->get('origin'));
            if(!$origin instanceof Planet || $origin->owner?->id!==$membership->id) throw new \DomainException('Planète de départ inaccessible.');
            if(!$destinationSystem || $destinationSystem->galaxy->universe->id!==$membership->universe->id) throw new \DomainException('Destination inaccessible.');
            if($mission==='explore') $composition=['probe'=>1];
            elseif($mission==='colonize') $composition=['colony_ship'=>1];
            else { $quantity=filter_var($request->request->get('probe_quantity'),FILTER_VALIDATE_INT);if($quantity===false || $quantity<1 || $quantity>100) throw new \DomainException('Choisissez entre 1 et 100 sondes.');$composition=['probe'=>$quantity]; }
            $savedPreferences=$preferences->for($membership->player);
            $defaultSpeed=match($mission){'spy'=>(float)$savedPreferences['galaxySpySpeed'],'colonize'=>(float)$savedPreferences['galaxyColonizeSpeed'],default=>1.0};
            $service->send($membership,$origin,$mission,$targetId,$composition,$defaultSpeed,[],(string)$request->request->get('request_key'));
            $shipSlug=$mission==='colonize'?'colony_ship':'probe';$shipLabel=$catalog->getTierForUniverse($membership->universe,'ships',$shipSlug,'basic')['label'];
            $this->addFlash('success',match($mission){'explore'=>$shipLabel.' envoyée en exploration.','spy'=>$shipLabel.' envoyée en espionnage.','colonize'=>$shipLabel.' envoyé.'});
        } catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        $redirectSystem=$destinationSystem && $destinationSystem->galaxy->universe->id===$membership->universe->id?$destinationSystem:null;$parameters=$redirectSystem?['galaxy'=>$redirectSystem->galaxy->id,'system'=>$redirectSystem->id]:[];
        $returnRoute=(string)$request->request->get('return_to','app_map');
        if(!in_array($returnRoute,['app_map','app_map_v2'],true)) $returnRoute='app_map';
        return $this->redirectToRoute($returnRoute,$parameters);
    }

    #[Route('/flottes/estimer',name:'app_fleet_preview',methods:['POST'])]
    public function preview(Request $request,UniverseContext $context,GameRepository $repo,FleetService $service,DestinationCatalogue $destinations): Response
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
            $mode=(string)$request->request->get('destination_mode','custom');
            $destination=$destinations->resolve($m,$mission,$mode,(string)$request->request->get('coordinates',''),$request->request->get($mode.'_destination'));
            $result=$service->preview($m,$origin,$mission,(int)$destination->id,$request->request->all('ships'),$ratio,$request->request->all('cargo'),$orbitHours);
            if($request->request->getBoolean('require_exact_composition') && $result['ship_adjusted']) throw new \DomainException('La composition n’est plus disponible exactement. Modifiez vos quantités ou vérifiez à nouveau la composition enregistrée.');
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
            $service->decideOrbit($context->membership(), $id, $decision === 'accept', (string)$request->request->get('request_key',''));
            $this->addFlash('success', $decision === 'accept' ? 'Demande d’orbite acceptée.' : 'Demande d’orbite refusée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('app_fleets');
    }

    #[Route('/flottes/envoyer',name:'app_fleet_send',methods:['POST'])]
    public function send(Request $request,UniverseContext $context,GameRepository $repo,FleetService $service,DestinationCatalogue $destinations): Response
    {
        if(!$this->isCsrfTokenValid('fleet',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $m=$context->membership();
        try {
            $origin=$repo->find(Planet::class,(int)$request->request->get('origin'));
            if(!$origin || $origin->owner?->id!==$m->id) throw new \DomainException('Origine inaccessible.');
            $mission=(string)$request->request->get('mission');
            $mode=(string)$request->request->get('destination_mode','custom');
            $destination=$destinations->resolve($m,$mission,$mode,(string)$request->request->get('coordinates',''),$request->request->get($mode.'_destination'));$target=(int)$destination->id;
            $ratio=filter_var($request->request->get('ratio'),FILTER_VALIDATE_FLOAT);
            if($ratio===false) throw new \DomainException('Vitesse invalide.');
            $orbitHours=0.0;
            if($mission==='orbit') {
                $orbitHours=filter_var($request->request->get('orbit_hours',0),FILTER_VALIDATE_FLOAT);
                if($orbitHours===false) throw new \DomainException('Durée d’orbite invalide.');
            }
            $adjusted=$service->send($m,$origin,$mission,$target,$request->request->all('ships'),$ratio,$request->request->all('cargo'),(string)$request->request->get('request_key'),$orbitHours,$request->request->getBoolean('require_exact_composition'));
            $this->addFlash('success','Flotte envoyée. Carburant aller-retour réservé.'.($adjusted?' Quantités ajustées aux stocks et capacités disponibles.':''));
        } catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        return $this->redirectToRoute('app_fleets');
    }
}
