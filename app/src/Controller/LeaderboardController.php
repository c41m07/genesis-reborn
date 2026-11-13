<?php
namespace App\Controller;

use App\Service\Player\{DailyLeaderboard,RelationResolver};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class LeaderboardController extends AbstractController
{
    #[Route('/classement', name: 'app_leaderboard', methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, DailyLeaderboard $leaderboard, RelationResolver $relations): Response
    {
        $membership = $context->membership();
        $tab = $request->query->getString('tab') === 'guilds' ? 'guilds' : 'players';
        $ownGuild = $relations->guild($membership);
        $daily = $leaderboard->forUniverse($membership);
        $categories = ['points'=>'Points totaux', 'buildings'=>'Points de bâtiments', 'research'=>'Points de recherche', 'fleet'=>'Points de flotte', 'planets'=>'Nombre de planètes'];
        $category = $request->query->getString('category', 'points');
        if (!isset($categories[$category])) $category = 'points';
        $rows = $daily['players'];
        usort($rows, static fn (array $a, array $b): int => $a['ranks'][$category] <=> $b['ranks'][$category]);
        $pageSize = 50;
        $pages = max(1, (int) ceil(count($rows) / $pageSize));
        $page = max(1, min($pages, $request->query->getInt('page', 1)));
        $rows = array_slice($rows, ($page - 1) * $pageSize, $pageSize);
        foreach ($rows as &$row) {
            $peer = $row['membership'];
            $guild = $relations->guild($peer);
            $row['guild_tag'] = $guild?->tag;
            $guildStatus = $relations->guildStatus($ownGuild, $guild);
            $row['relation'] = $peer->id === $membership->id ? 'self' : ($relations->enemy($membership, $peer) ? 'enemy' : ($guildStatus === 'allies' ? 'guild' : ($relations->contract($membership, $peer)?->status === 'accepted' ? 'commander' : 'neutral')));
        }
        unset($row);

        $guildRows = $tab === 'guilds' ? $daily['guilds'] : [];
        foreach ($guildRows as &$row) {
            $status = $relations->guildStatus($ownGuild, $row['guild']);
            $row['relation'] = $status === 'enemies' ? 'enemy' : ($status === 'allies' ? 'guild' : 'neutral');
        }
        unset($row);

        return $this->render('player/leaderboard.html.twig', [
            'daily' => $daily,
            'categories' => $categories,
            'category' => $category,
            'page' => $page,
            'pages' => $pages,
            'membership' => $membership,
            'rows' => $rows,
            'tab' => $tab,
            'guildRows' => $guildRows,
            'ownGuild' => $ownGuild,
        ]);
    }

    #[Route('/api/classement', name: 'api_leaderboard', methods: ['GET'])]
    public function api(UniverseContext $context, DailyLeaderboard $leaderboard): JsonResponse
    {
        $daily = $leaderboard->forUniverse($context->membership());
        $rows = array_map(static fn (array $row): array => [
            'rank' => $row['ranks']['points'],
            'ranks' => $row['ranks'],
            'score_changes' => $row['score_changes'],
            'rank_changes' => $row['rank_changes'],
            'username' => $row['membership']->player->username,
            'total_points' => $row['total_points'],
            'research_points' => $row['research_points'],
            'fleet_points' => $row['fleet_points'],
            'building_points' => $row['building_points'],
            'fleet_units' => $row['fleet_units'],
            'ship_units' => $row['ship_units'],
            'defense_units' => $row['defense_units'],
            'planets' => $row['planets'],
        ], $daily['players']);

        return $this->json(['universe' => $context->membership()->universe->slug, 'players' => $rows, 'updated_at'=>$daily['updated_at']->format(DATE_ATOM), 'comparison_at'=>$daily['comparison_at']?->format(DATE_ATOM), 'next_update'=>$daily['next_update']->format(DATE_ATOM), 'timezone'=>DailyLeaderboard::TIMEZONE]);
    }
}
