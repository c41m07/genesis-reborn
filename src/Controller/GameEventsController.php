<?php

namespace App\Controller;

use App\Service\Event\GameEventService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GameEventsController extends AbstractController
{
    #[Route('/evenements', name: 'app_game_events', methods: ['GET'])]
    public function index(UniverseContext $context, GameEventService $events): Response
    {
        $membership = $context->membership();
        return $this->render('player/events.html.twig', ['membership' => $membership, 'events' => $events->visibleFor($membership)]);
    }
}
