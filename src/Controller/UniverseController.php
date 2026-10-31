<?php
namespace App\Controller;
use App\Entity\{Player,Universe};
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
        return $this->render('universe/index.html.twig',['universes'=>$repo->all(Universe::class),'configs'=>$catalog->all('universes'),'now'=>$clock->now()]);
    }
    #[Route('/univers/{id}/rejoindre',name:'app_universe_join',methods:['POST'],requirements:['id'=>'\d+'])]
    public function join(int $id,Request $request,UniverseService $service): Response
    {
        if(!$this->isCsrfTokenValid('join'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try { $service->select($this->getUser(),$id);return $this->redirectToRoute('app_home'); }
        catch(\DomainException $e) { $this->addFlash('error',$e->getMessage());return $this->redirectToRoute('app_universes'); }
    }
}
