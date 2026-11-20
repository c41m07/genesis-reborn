<?php
namespace App\Controller;

use App\Entity\BoostEvent;
use App\Service\Event\ChallengeQueryService;
use App\Service\Universe\UniverseContext;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class ChallengeController extends AbstractController
{
    #[Route('/evenements/defis/{id}',name:'app_challenge',requirements:['id'=>'\d+'],methods:['GET'])]
    public function show(int $id,Request $request,UniverseContext $context,EntityManagerInterface $em,ChallengeQueryService $query,GameCatalog $catalog): Response
    {
        $membership=$context->membership();$event=$em->find(BoostEvent::class,$id);
        if (!$event || $event->universe->id!==$membership->universe->id || (!($event->configuration['guided'] ?? false) && !str_starts_with($event->eventKey,'weekly-'))) throw $this->createNotFoundException('Défi introuvable dans cet univers.');
        $ranking = $query->contributionRanking($event, $membership, $request->query->getInt('ranking_page', 1));
        $response = $this->render('player/challenge.html.twig', [
            'challenge' => $query->view($event, $membership, includePersonalRank: false),
            'ranking' => $ranking,
            'resources' => $catalog->rules()['resources'],
        ]);
        // La même URL ne montre le classement qu'aux contributeurs de ce défi.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
