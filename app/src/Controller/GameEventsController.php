<?php

namespace App\Controller;

use App\Service\Event\GameEventService;
use App\Service\Event\ChallengeQueryService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GameEventsController extends AbstractController
{
    #[Route('/evenements', name: 'app_game_events', methods: ['GET'])]
    public function index(UniverseContext $context, GameEventService $events, ChallengeQueryService $challenges): Response
    {
        $membership = $context->membership();
        return $this->render('player/events.html.twig', ['membership' => $membership, 'events' => array_values(array_filter($events->visibleFor($membership),static fn($event):bool=>empty($event->parameters['challenge_id']))), 'challenges'=>$challenges->forPlayer($membership)]);
    }
}
