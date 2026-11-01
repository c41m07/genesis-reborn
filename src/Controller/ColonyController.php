<?php
namespace App\Controller;
use App\Entity\{Planet,PlayerDiscovery};
use App\Repository\GameRepository;
use App\Service\Universe\UniverseContext;
use App\Service\Colony\{ColonyView,ResourceService};
use App\Service\Queue\QueueService;
use App\Service\Player\{LeaderboardService,RelationResolver};
use App\Service\Catalog\GameCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
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
        $m=$this->context->membership();$planets=$this->repo->planets($m->universe,$m);
        $chosen=$request->query->getInt('colony',$request->getSession()->get('selected_planet_'.$m->id,0));$planet=null;
        foreach($planets as $candidate) if($candidate->id===$chosen) $planet=$candidate;
        if($request->query->has('colony') && !$planet) throw $this->createNotFoundException('Planète inaccessible.');
        $planet??=$planets[0]??null;$section=$this->section((string)$request->query->get('section','overview'));
        return $planet ? $this->redirectToRoute('app_colony',array_filter(['id'=>$planet->id,'section'=>$section==='overview'?null:$section])) : $this->redirectToRoute('app_universes');
    }
    #[Route('/planetes',name:'app_planets',methods:['GET'])]
    public function planets(): Response
    {
        $membership=$this->context->membership();
        $planets=$this->repo->planets($membership->universe,$membership);
        $available=array_values(array_filter($this->repo->planets($membership->universe,null,true),static fn(Planet $planet): bool => !$planet->npcPlanet));
        return $this->render('colony/planets.html.twig',['membership'=>$membership,'planets'=>$planets,'available_planets'=>$available]);
    }
    #[Route('/planetes/{id}',name:'app_colony',methods:['GET'],requirements:['id'=>'\d+'])]
    public function show(int $id,Request $request,ColonyView $view,LeaderboardService $leaderboard,RelationResolver $relations,GameCatalog $catalog): Response
    {
        $p=$this->owned($id);$m=$this->context->membership();$section=$this->section((string)$request->query->get('section','overview'));$data=$view->data($m,$p);
        if($section==='overview') {
            $rank=null;foreach($leaderboard->forUniverse($m) as $row) if($row['membership']->id===$m->id) {$rank=$row['ranks']['points'];break;}
            $features=$catalog->forUniverse($m->universe)['features']??[];
            $data['empire_rank']=$rank;$data['empire_guild']=$relations->guild($m);$data['guilds_enabled']=(bool)($features['guilds_enabled']??false);
        }
        return $this->render('colony/show.html.twig',$data+['section'=>$section]);
    }
    #[Route('/planetes/{id}/ressources',name:'app_resources',methods:['GET'],requirements:['id'=>'\d+'])]
    public function resources(int $id,ResourceService $resources): JsonResponse
    {
        $planet=$this->owned($id);$this->queueService->synchronize($this->context->membership());
        return $this->json($resources->snapshot($planet),200,['Cache-Control'=>'private, no-store']);
    }
    #[Route('/planetes/{id}/construire',name:'app_queue_add',methods:['POST'],requirements:['id'=>'\d+'])]
    public function add(int $id,Request $request,QueueService $service): Response
    {
        $p=$this->owned($id);
        if(!$this->isCsrfTokenValid('build'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $quantity=filter_var($request->request->get('quantity',1),FILTER_VALIDATE_INT);
        try {
            if($quantity===false) throw new \DomainException('Quantité invalide.');
            $service->add($this->context->membership(),$p,(string)$request->request->get('group'),(string)$request->request->get('slug'),$quantity,(string)$request->request->get('request_key'));
            $this->addFlash('success','Commande enregistrée dans la file.');
        } catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        return $this->redirectToRoute('app_colony',['id'=>$id,'section'=>$this->section((string)$request->request->get('section','overview'))]);
    }
    #[Route('/planetes/{id}/file/{itemId}/annuler',name:'app_queue_cancel',methods:['POST'],requirements:['id'=>'\d+','itemId'=>'\d+'])]
    public function cancel(int $id,int $itemId,Request $request,QueueService $service): Response
    {
        $this->owned($id);
        if(!$this->isCsrfTokenValid('cancel'.$itemId,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try { $service->cancel($this->context->membership(),$itemId,(string)$request->request->get('request_key'));$this->addFlash('success','Commande et dépendances annulées. 25 % du coût payé remboursé, dans la limite du stockage.'); }
        catch(\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        return $this->redirectToRoute('app_colony',['id'=>$id,'section'=>$this->section((string)$request->request->get('section','overview'))]);
    }
    private function section(string $section): string
    {
        return in_array($section,['buildings','technologies','ships','defenses'],true)?$section:'overview';
    }
    #[Route('/carte',name:'app_map',methods:['GET'])]
    public function map(Request $request): Response
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
        $galaxyId = $request->query->getInt('galaxy');
        $selectedGalaxy = null;
        foreach ($galaxies as $galaxy) {
            if (($galaxyId > 0 && $galaxy->id === $galaxyId) || ($galaxyId === 0 && $selectedGalaxy === null)) {
                $selectedGalaxy = $galaxy;
            }
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

        return $this->render('universe/map.html.twig', [
            'membership' => $m,
            'systems' => $systems,
            'selected_system' => $selectedSystem,
            'galaxies' => $galaxies,
            'selected_galaxy' => $selectedGalaxy,
        ]);
    }
}
