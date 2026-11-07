<?php
namespace App\Controller;

use App\Service\Player\{LeaderboardService,RelationResolver};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class LeaderboardController extends AbstractController
{
    #[Route('/classement', name: 'app_leaderboard', methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, LeaderboardService $leaderboard, RelationResolver $relations): Response
    {
        $membership = $context->membership();
        $tab = $request->query->getString('tab') === 'guilds' ? 'guilds' : 'players';
        $ownGuild = $relations->guild($membership);
        $rows = $leaderboard->forUniverse($membership);
        foreach ($rows as &$row) {
            $peer = $row['membership'];
            $guild = $relations->guild($peer);
            $guildStatus = $relations->guildStatus($ownGuild, $guild);
            $row['relation'] = $peer->id === $membership->id ? 'self' : ($guildStatus === 'enemies' ? 'enemy' : ($guildStatus === 'allies' ? 'guild' : ($relations->contract($membership, $peer)?->status === 'accepted' ? 'commander' : 'neutral')));
        }
        unset($row);

        $guildRows = $tab === 'guilds' ? $leaderboard->guildsForUniverse($membership, $rows) : [];
        foreach ($guildRows as &$row) {
            $status = $relations->guildStatus($ownGuild, $row['guild']);
            $row['relation'] = $status === 'enemies' ? 'enemy' : ($status === 'allies' ? 'guild' : 'neutral');
        }
        unset($row);

        return $this->render('player/leaderboard.html.twig', [
            'membership' => $membership,
            'rows' => $rows,
            'tab' => $tab,
            'guildRows' => $guildRows,
            'ownGuild' => $ownGuild,
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
