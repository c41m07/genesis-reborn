<?php
namespace App\Controller;

use App\Entity\BoostEvent;
use App\Service\Event\ChallengeQueryService;
use App\Service\Universe\UniverseContext;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChallengeController extends AbstractController
{
    #[Route('/evenements/defis/{id}',name:'app_challenge',requirements:['id'=>'\d+'],methods:['GET'])]
    public function show(int $id,UniverseContext $context,EntityManagerInterface $em,ChallengeQueryService $query,GameCatalog $catalog): Response
    {
        $membership=$context->membership();$event=$em->find(BoostEvent::class,$id);
        if (!$event || $event->universe->id!==$membership->universe->id || (!($event->configuration['guided'] ?? false) && !str_starts_with($event->eventKey,'weekly-'))) throw $this->createNotFoundException('Défi introuvable dans cet univers.');
        return $this->render('player/challenge.html.twig',['challenge'=>$query->view($event,$membership),'resources'=>$catalog->rules()['resources']]);
    }
}
