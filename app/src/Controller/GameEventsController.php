<?php

namespace App\Controller;

use App\Entity\Player;
use App\Repository\GameRepository;
use App\Service\Event\ChallengeQueryService;
use App\Service\PirateCampaign\PiratePilotGate;
use App\Service\Universe\UniverseContext;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class GameEventsController extends AbstractController
{
    #[Route('/evenements', name: 'app_game_events', methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, GameRepository $repository, ClockInterface $clock, PiratePilotGate $pirateCampaign): Response
    {
        $membership = $context->membership();
        $now = $clock->now();
        $groups = [];
        foreach (['current', 'upcoming'] as $group) {
            $groups[$group] = $repository->playerGameEventsPage($membership, $now, $group === 'upcoming', $request->query->getInt($group.'_page', 1));
        }
        $player = $this->getUser();

        return $this->render('player/events.html.twig', ['membership' => $membership, 'eventGroups' => $groups,
            'pirateCampaignAvailable' => $pirateCampaign->admits($player instanceof Player ? $player : null, $membership, $now)]);
    }

    #[Route('/evenements/defis', name: 'app_game_challenges', methods: ['GET'])]
    public function challenges(Request $request, UniverseContext $context, ChallengeQueryService $challenges): Response
    {
        $membership = $context->membership();
        return $this->render('player/challenges.html.twig', [
            'membership' => $membership, 'listing' => $challenges->playerPage($membership, $request->query->getInt('page', 1)),
        ]);
    }
}
