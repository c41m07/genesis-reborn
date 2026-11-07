<?php
namespace App\Controller;
use App\Entity\{Planet,PlayerDiscovery,Fleet,FleetShip};
use App\Repository\GameRepository;
use App\Service\Universe\UniverseContext;
use App\Service\Colony\{ColonyView,ResourceService};
use App\Service\Queue\QueueService;
use App\Service\Player\{LeaderboardService,RelationResolver};
use App\Service\Catalog\GameCatalog;
use App\Manager\GameManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Psr\Clock\ClockInterface;
final class ColonyController extends AbstractController
{
    public function __construct(private UniverseContext $context,private GameRepository $repo,private QueueService $queueService) {}
    private function owned(int $id): Planet
    {
        $m=$this->context->membership();$p=$this->repo->find(Planet::class,$id);
        if(!$p || $p->owner?->id!==$m->id || $p->solarSystem->galaxy->universe->id!==$m->universe->id) throw $this->createNotFoundException('Planète inaccessible.');return $p;
    }
    #[Route('/',name:'app_home',methods:['GET'])]
    public function home(Request $request): Response
    {
        if (!$this->getUser()) return $this->render('public/home.html.twig');
        $m=$this->context->membership();$planets=$this->repo->planets($m->universe,$m);
        $chosen=$request->query->getInt('colony',$request->getSession()->get('selected_planet_'.$m->id,0));$planet=null;
        foreach($planets as $candidate) if($candidate->id===$chosen) $planet=$candidate;
        if($request->query->has('colony') && !$planet) throw $this->createNotFoundException('Planète inaccessible.');
        $planet??=$planets[0]??null;$section=$this->section((string)$request->query->get('section','overview'));
        return $planet ? $this->redirectToRoute('app_colony',array_filter(['id'=>$planet->id,'section'=>$section==='overview'?null:$section])) : $this->redirectToRoute('app_universes');
    }
    #[Route('/planetes',name:'app_planets',methods:['GET'])]
    public function planets(ResourceService $resources): Response
    {
        $membership=$this->context->membership();
        $planets=$this->repo->planets($membership->universe,$membership);
        $available=array_values(array_filter($this->repo->planets($membership->universe,null,true),static fn(Planet $planet): bool => !$planet->npcPlanet));
        $positionModifiers=[];
        foreach($planets as $planet) $positionModifiers[$planet->id]=$resources->positionProductionModifiers($planet);
        return $this->render('colony/planets.html.twig',['membership'=>$membership,'planets'=>$planets,'available_planets'=>$available,'position_modifiers'=>$positionModifiers]);
    }
    #[Route('/planetes/{id}',name:'app_colony',methods:['GET'],requirements:['id'=>'\d+'])]
    public function show(int $id,Request $request,ColonyView $view,LeaderboardService $leaderboard,RelationResolver $relations,GameCatalog $catalog): Response
    {
        $p=$this->owned($id);$m=$this->context->membership();$section=$this->section((string)$request->query->get('section','overview'));$data=$view->data($m,$p,$request->query->getBoolean('showLockedItems'));
        if($section==='overview') {
            $points=['building_points'=>0,'research_points'=>0,'fleet_points'=>0,'total_points'=>0];
            $rank=null;foreach($leaderboard->forUniverse($m) as $row) if($row['membership']->id===$m->id) {$rank=$row['ranks']['points'];$points=array_intersect_key($row,$points);break;}
            $features=$catalog->forUniverse($m->universe)['features']??[];
            $data['empire_points']=$points;$data['empire_rank']=$rank;$data['empire_guild']=$relations->guild($m);$data['guilds_enabled']=(bool)($features['guilds_enabled']??false);
        }
        return $this->render('colony/show.html.twig',$data+['section'=>$section]);
    }
    #[Route('/technologies',name:'app_technology_tree',methods:['GET'])]
    public function technologyTree(Request $request, ColonyView $view): Response
    {
        $membership = $this->context->membership();
        $planets = $this->repo->planets($membership->universe, $membership);
        if ($planets === []) {
            return $this->redirectToRoute('app_universes');
        }
        $selectedId = (int) $request->getSession()->get('selected_planet_'.$membership->id, 0);
        $planet = null;
        foreach ($planets as $candidate) {
            if ($candidate->id === $selectedId) {
                $planet = $candidate;
                break;
            }
        }
        $planet ??= $planets[0];
        return $this->render('player/technology_tree.html.twig', $view->data($membership, $planet) + ['tree_standalone' => true]);
    }

    #[Route('/planetes/{id}/renommer',name:'app_planet_rename',methods:['POST'],requirements:['id'=>'\\d+'])]
    public function rename(int $id,Request $request,GameManager $manager): Response
    {
        $planet=$this->owned($id);
        if(!$this->isCsrfTokenValid('rename-planet-'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $name=trim((string)$request->request->get('name',''));
        if($name==='' || mb_strlen($name)>80) {
            $this->addFlash('error','Le nom de la planète doit contenir entre 1 et 80 caractères.');
        } else {
            $planet->name=$name;$manager->save($planet);$manager->flush();
            $this->addFlash('success','Planète renommée.');
        }
        return $this->redirectToRoute('app_planets',['_fragment'=>'planet-'.$id]);
    }
    #[Route('/planetes/{id}/ressources',name:'app_resources',methods:['GET'],requirements:['id'=>'\d+'])]
    public function resources(int $id,ResourceService $resources,LeaderboardService $leaderboard): JsonResponse
    {
        $planet=$this->owned($id);$this->queueService->synchronize($this->context->membership());
        $snapshot=$resources->snapshot($planet);
        foreach($leaderboard->forUniverse($this->context->membership()) as $row) if($row['membership']->id===$planet->owner->id) {$snapshot['_protection']=$row['protection'];break;}
        return $this->json($snapshot,200,['Cache-Control'=>'private, no-store']);
    }
    #[Route('/planetes/{id}/construire',name:'app_queue_add',methods:['POST'],requirements:['id'=>'\d+'])]
    public function add(int $id,Request $request,QueueService $service): Response
    {
        $p=$this->owned($id);
        if(!$this->isCsrfTokenValid('build'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $quantity=filter_var($request->request->get('quantity',1),FILTER_VALIDATE_INT);
        try {
            if($quantity===false) throw new \DomainException('Quantité invalide.');
            $service->add($this->context->membership(),$p,(string)$request->request->get('group'),(string)$request->request->get('slug'),$quantity,(string)$request->request->get('request_key'),(string)$request->request->get('tier','basic'));
            $this->addFlash('success','Commande enregistrée dans la file.');
        } catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        return $this->redirectToRoute('app_colony',['id'=>$id,'section'=>$this->section((string)$request->request->get('section','overview')),'_fragment'=>'item-'.(string)$request->request->get('slug','').'-'.(string)$request->request->get('tier','basic')]);
    }
    #[Route('/planetes/{id}/file/{itemId}/annuler',name:'app_queue_cancel',methods:['POST'],requirements:['id'=>'\d+','itemId'=>'\d+'])]
    public function cancel(int $id,int $itemId,Request $request,QueueService $service): Response
    {
        $this->owned($id);
        if(!$this->isCsrfTokenValid('cancel'.$itemId,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try { $service->cancel($this->context->membership(),$itemId,(string)$request->request->get('request_key'));$this->addFlash('success','Commande et dépendances annulées. 25 % du coût payé remboursé, dans la limite du stockage.'); }
        catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        return $this->redirectToRoute('app_colony',['id'=>$id,'section'=>$this->section((string)$request->request->get('section','overview')),'_fragment'=>'panel-'. $this->section((string)$request->request->get('section','overview'))]);
    }
    private function section(string $section): string
    {
        return in_array($section,['buildings','technologies','ships','defenses'],true)?$section:'overview';
    }
    #[Route('/carte',name:'app_map',methods:['GET'])]
    public function map(Request $request, GameCatalog $catalog, ClockInterface $clock): Response
    {
        return $this->render('universe/map.html.twig', $this->mapData($request, $catalog, $clock));
    }

    #[Route('/carte-v2',name:'app_map_v2',methods:['GET'])]
    public function mapV2(Request $request, GameCatalog $catalog, ClockInterface $clock, RelationResolver $relations): Response
    {
        return $this->render('universe/map_v2.html.twig', $this->mapData($request, $catalog, $clock, true, $relations));
    }

    private function mapData(Request $request, GameCatalog $catalog, ClockInterface $clock, bool $interactive = false, ?RelationResolver $relations = null): array
    {
        $m=$this->context->membership();$known=[];
        foreach($this->repo->all(PlayerDiscovery::class,['playerUniverse'=>$m]) as $d) $known[$d->solarSystem->id]=true;
        $systems=[];
        foreach($this->repo->systems($m->universe) as $s) $systems[]=['system'=>$s,'known'=>isset($known[$s->id]),'planets'=>isset($known[$s->id])?$this->repo->all(Planet::class,['solarSystem'=>$s]):[]];
        $galaxiesById = [];
        foreach ($systems as $row) {
            $galaxiesById[$row['system']->galaxy->id] = $row['system']->galaxy;
        }
        $galaxies = array_values($galaxiesById);
        $ownedPlanets = $this->repo->planets($m->universe, $m);
        $originId = (int) $request->getSession()->get('selected_planet_'.$m->id, 0);
        $mapOrigin = null;
        foreach ($ownedPlanets as $ownedPlanet) {
            if ($ownedPlanet->id === $originId) {
                $mapOrigin = $ownedPlanet;
                break;
            }
        }
        $mapOrigin ??= $ownedPlanets[0] ?? null;
        $defaultGalaxyId = $interactive ? $mapOrigin?->solarSystem->galaxy->id : null;
        $galaxyId = $request->query->getInt('galaxy');
        $selectedGalaxy = null;
        foreach ($galaxies as $galaxy) {
            if ($galaxyId > 0 && $galaxy->id === $galaxyId) {
                $selectedGalaxy = $galaxy;
                break;
            }
            if ($galaxyId === 0 && $selectedGalaxy === null && (!$interactive || $defaultGalaxyId === null || $galaxy->id === $defaultGalaxyId)) {
                $selectedGalaxy = $galaxy;
            }
        }
        if ($galaxyId === 0 && $selectedGalaxy === null) {
            $selectedGalaxy = $galaxies[0] ?? null;
        }
        if ($galaxyId > 0 && $selectedGalaxy === null) {
            throw $this->createNotFoundException('Galaxie inaccessible dans cet univers.');
        }
        $systems = array_values(array_filter($systems, fn (array $row): bool => $selectedGalaxy !== null && $row['system']->galaxy->id === $selectedGalaxy->id));

        $selectedId = $request->query->getInt('system');
        $selectedSystem = null;
        foreach ($systems as $row) {
            if (($selectedId > 0 && $row['system']->id === $selectedId) || ($selectedId === 0 && $selectedSystem === null)) {
                $selectedSystem = $row;
            }
        }
        if ($selectedId > 0 && $selectedSystem === null) {
            throw $this->createNotFoundException('Système inaccessible dans cet univers.');
        }

        $mapStock=[];$mapFleetLimit=max(1,(int)($catalog->forUniverse($m->universe)['limits']['max_fleet_size']??1000000));
        if($mapOrigin) { $idleFleet=$this->repo->one(Fleet::class,['homePlanet'=>$mapOrigin,'playerUniverse'=>$m,'status'=>'idle']);if($idleFleet) foreach($this->repo->all(FleetShip::class,['fleet'=>$idleFleet]) as $ship) $mapStock[$ship->slug]=$ship->quantity; }

        $galaxyV2Json = '';
        $flights = [];
        if ($interactive) {
            $mapSystems = [];
            foreach ($systems as $row) {
                $system = $row['system'];
                $mapSystems[] = [
                    'id' => $system->id,
                    'name' => $system->name,
                    'galaxy' => $system->galaxy->coordinateCode(),
                    'galaxyId' => $system->galaxy->id,
                    'number' => $system->number(),
                    'x' => $system->coordX,
                    'y' => $system->coordY,
                    'known' => $row['known'],
                    'planets' => array_map(fn (Planet $planet): array => [
                        'id' => $planet->id,
                        'name' => $planet->name,
                        'position' => $planet->position,
                        'coordinates' => $planet->coordinates(),
                        'ownedByCurrentPlayer' => $planet->owner?->id === $m->id,
                        'ownerName' => $planet->owner?->player->username,
                        'npc' => $planet->npcPlanet,
                        'conquestLevel' => $planet->conquestLevel,
                    ], $row['planets']),
                ];
            }

            $missionLabels = ['explore' => 'Exploration', 'spy' => 'Espionnage', 'colonize' => 'Colonisation', 'transport' => 'Transport', 'attack' => 'Attaque', 'conquer' => 'Conquête', 'defend' => 'Défense', 'orbit' => 'Orbite'];
            $statusLabels = ['traveling' => 'En vol', 'returning' => 'Retour', 'attacking' => 'Combat', 'defending' => 'Défense', 'orbiting' => 'En orbite', 'orbit_pending' => 'Demande d’orbite'];
            $relatedOwnerCache = [];
            foreach ($this->repo->activeFleetsInUniverse($m->universe) as $fleet) {
                if (!isset($statusLabels[$fleet->status])) continue;
                $originSystem = $fleet->homePlanet->solarSystem;
                $destinationSystem = $fleet->destination?->solarSystem ?? $fleet->destinationSystem;
                if (!$destinationSystem) continue;
                $isOwnFleet = $fleet->playerUniverse->id === $m->id;
                $targetsOwnColony = $fleet->destination?->owner?->id === $m->id;
                $ownerId = (int) $fleet->playerUniverse->id;
                if (!array_key_exists($ownerId, $relatedOwnerCache)) {
                    $relatedOwnerCache[$ownerId] = $isOwnFleet || ($relations?->allied($m, $fleet->playerUniverse) ?? false);
                }
                $isAlliedFleet = !$isOwnFleet && $relatedOwnerCache[$ownerId];
                if (!$isOwnFleet && !$targetsOwnColony && !$isAlliedFleet) continue;
                $originInGalaxy = $selectedGalaxy && $originSystem->galaxy->id === $selectedGalaxy->id;
                $destinationInGalaxy = $selectedGalaxy && $destinationSystem->galaxy->id === $selectedGalaxy->id;
                if (!$originInGalaxy && !$destinationInGalaxy) continue;

                $ships = array_values(array_filter($this->repo->all(FleetShip::class, ['fleet' => $fleet]), static fn (FleetShip $ship): bool => $ship->quantity > 0));
                $shipsSummary = implode(' · ', array_map(static fn (FleetShip $ship): string => $ship->quantity.' × '.str_replace('_', ' ', $ship->slug), $ships));
                $relationLabel = $isOwnFleet ? 'Votre empire' : ($targetsOwnColony ? 'Flotte entrante' : 'Allié');
                $flights[] = [
                    'id' => $fleet->id,
                    'mission' => $fleet->mission,
                    'missionLabel' => $missionLabels[$fleet->mission] ?? 'Mission',
                    'status' => $fleet->status,
                    'statusLabel' => $statusLabels[$fleet->status],
                    'relationLabel' => $relationLabel,
                    'ownerName' => $fleet->playerUniverse->player->username,
                    'originSystemId' => $originSystem->id,
                    'originGalaxy' => $originSystem->galaxy->coordinateCode(),
                    'originX' => $originSystem->coordX,
                    'originY' => $originSystem->coordY,
                    'originName' => $fleet->homePlanet->name,
                    'destinationSystemId' => $destinationSystem->id,
                    'destinationGalaxy' => $destinationSystem->galaxy->coordinateCode(),
                    'destinationX' => $destinationSystem->coordX,
                    'destinationY' => $destinationSystem->coordY,
                    'destinationName' => $fleet->destination?->name ?? $destinationSystem->name,
                    'destinationCoordinates' => $fleet->destination?->coordinates() ?? ($destinationSystem->galaxy->coordinateCode().':'.$destinationSystem->number()),
                    'departureAt' => $fleet->departureAt?->format(\DateTimeInterface::ATOM),
                    'arrivalAt' => $fleet->arrivalAt?->format(\DateTimeInterface::ATOM),
                    'ships' => $shipsSummary,
                ];
            }

            $galaxyV2Json = json_encode([
                'systems' => $mapSystems,
                'origin' => $mapOrigin ? [
                    'planetName' => $mapOrigin->name,
                    'planetCoordinates' => $mapOrigin->coordinates(),
                    'galaxyId' => $mapOrigin->solarSystem->galaxy->id,
                    'systemId' => $mapOrigin->solarSystem->id,
                    'systemNumber' => $mapOrigin->solarSystem->number(),
                    'x' => $mapOrigin->solarSystem->coordX,
                    'y' => $mapOrigin->solarSystem->coordY,
                    'position' => $mapOrigin->position,
                ] : null,
                'flights' => $flights,
                'serverNow' => $clock->now()->format(\DateTimeInterface::ATOM),
                'galaxy' => $selectedGalaxy?->coordinateCode(),
                'universe' => $m->universe->name,
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        }

        return [
            'membership' => $m,
            'map_origin' => $mapOrigin,
            'map_stock' => $mapStock,
            'map_fleet_limit' => $mapFleetLimit,
            'systems' => $systems,
            'selected_system' => $selectedSystem,
            'galaxies' => $galaxies,
            'selected_galaxy' => $selectedGalaxy,
            'galaxy_v2_json' => $galaxyV2Json,
            'galaxy_v2_flights' => $flights,
            'map_can_colonize' => count($ownedPlanets) < (int) ($catalog->forUniverse($m->universe)['limits']['max_planets'] ?? 31) && ($mapStock['colony_ship'] ?? 0) > 0,
            'map_can_spy' => ($mapStock['probe'] ?? 0) > 0,
            'map_can_explore' => ($mapStock['probe'] ?? 0) > 0,
            'map_can_conquer' => (bool) ($catalog->forUniverse($m->universe)['features']['planet_conquest'] ?? false) && count($ownedPlanets) < (int) ($catalog->forUniverse($m->universe)['limits']['max_planets'] ?? 31) && ($mapStock['colony_ship'] ?? 0) > 0,
        ];
    }
}
