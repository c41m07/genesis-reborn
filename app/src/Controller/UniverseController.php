<?php
namespace App\Controller;
use App\Entity\{Player,Universe,PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Universe\UniverseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Psr\Clock\ClockInterface;
final class UniverseController extends AbstractController
{
    #[Route('/univers',name:'app_universes',methods:['GET'])]
    public function index(GameRepository $repo,GameCatalog $catalog,ClockInterface $clock): Response
    {
        $universes = $repo->all(Universe::class);
        $configs = [];
        foreach ($universes as $universe) {
            $configs[$universe->slug] = $catalog->forUniverse($universe);
        }

        $joined=[];
        foreach($repo->all(PlayerUniverse::class,['player'=>$this->getUser()]) as $member) if(!$member->isForgotten()) $joined[$member->universe->id]=true;
        return $this->render('universe/index.html.twig', ['joined'=>$joined,'universes' => $universes, 'configs' => $configs, 'now' => $clock->now()]);
    }
    #[Route('/univers/{id}/fermeture', name: 'app_universe_closing', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function closing(int $id, GameRepository $repo, \App\Service\Universe\UniverseClosingClock $clock): Response
    {
        $universe = $repo->find(Universe::class, $id);
        if (!$universe instanceof Universe) throw $this->createNotFoundException('Univers introuvable.');
        $response = $this->json($clock->snapshot($universe));
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
    #[Route('/univers/{id}/rejoindre',name:'app_universe_join',methods:['POST'],requirements:['id'=>'\d+'])]
    public function join(int $id,Request $request,UniverseService $service): Response
    {
        if(!$this->isCsrfTokenValid('join'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try { $service->select($this->getUser(),$id);return $this->redirectToRoute('app_home'); }
        catch(\DomainException $e) { $this->addFlash('error',$e->getMessage());return $this->redirectToRoute('app_universes'); }
    }
}
