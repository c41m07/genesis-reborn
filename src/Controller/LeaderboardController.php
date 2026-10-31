<?php
namespace App\Controller;

use App\Service\Player\LeaderboardService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class LeaderboardController extends AbstractController
{
    #[Route('/classement', name: 'app_leaderboard', methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, LeaderboardService $leaderboard): Response
    {
        $membership = $context->membership();
        $sort = $request->query->getString('by', 'points');
        if (!in_array($sort, ['points', 'fleet', 'planets'], true)) {
            $sort = 'points';
        }

        return $this->render('player/leaderboard.html.twig', [
            'membership' => $membership,
            'rows' => $leaderboard->forUniverse($membership),
            'sort' => $sort,
        ]);
    }

    #[Route('/api/classement', name: 'api_leaderboard', methods: ['GET'])]
    public function api(UniverseContext $context, LeaderboardService $leaderboard): JsonResponse
    {
        $rows = array_map(static fn (array $row): array => [
            'rank' => $row['ranks']['points'],
            'username' => $row['membership']->player->username,
            'total_points' => $row['total_points'],
            'research_points' => $row['research_points'],
            'fleet_points' => $row['fleet_points'],
            'building_points' => $row['building_points'],
            'fleet_units' => $row['fleet_units'],
            'ship_units' => $row['ship_units'],
            'defense_units' => $row['defense_units'],
            'planets' => $row['planets'],
        ], $leaderboard->forUniverse($context->membership()));

        return $this->json(['universe' => $context->membership()->universe->slug, 'players' => $rows]);
    }
}
