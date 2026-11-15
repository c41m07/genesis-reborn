<?php

namespace App\Controller;

use App\Entity\{Guild, GuildMember, GuildRank};
use App\Service\Universe\UniverseContext;
use App\Service\Player\{RelationResolver, DiplomacyService, DiplomacyAccessPolicy, GuildRankService, GuildPresence};
use App\Service\Catalog\GameCatalog;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class DiplomacyController extends AbstractController
{
    #[Route('/diplomatie', name: 'app_diplomacy', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UniverseContext $context,
        RelationResolver $relations,
        DiplomacyService $service,
        GameRepository $repository,
        GameCatalog $catalog,
        DiplomacyAccessPolicy $access,
        GuildRankService $rankService,
        GuildPresence $presence,
    ): Response
    {
        $membership = $context->membership();
        $search = mb_substr(trim($request->query->getString('q')), 0, $request->query->getString('tab') === 'relations' ? 40 : 60);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('diplomacy', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }
            $updated = false;
            try {
                $action = (string) $request->request->get('action');
                $target = (int) $request->request->get('target');
                match ($action) {
                    'rank_save' => $rankService->save(
                        $membership,
                        $target,
                        $request->request->getString('name'),
                        $request->request->getInt('position'),
                        $request->request->all('permissions'),
                        $request->request->getInt('version')
                    ),
                    'rank_assign' => $rankService->assign($membership, $target, $request->request->getInt('rank'), $request->request->getInt('expected_rank')),
                    'create' => $service->create($membership, (string) $request->request->get('name'), $request->request->getString('tag')),
                    'tag' => $service->setTag($membership, $request->request->getString('tag')),
                    'join' => $service->join($membership, $target),
                    'leave' => $service->leave($membership),
                    'transfer' => $service->transfer($membership, $target),
                    'disband' => $service->disband($membership),
                    'relation' => $service->relation(
                        $membership,
                        $target,
                        (string) $request->request->get('status'),
                        $request->request->getInt('relation_id'),
                        $request->request->getInt('relation_version'),
                        $request->request->getString('treaty_action', 'legacy')
                    ),
                    'propose', 'accept', 'break', 'rival', 'peace_propose', 'peace_accept', 'peace_reject' => $service->alliance($membership, $target, $action),
                    default => throw new \DomainException('Action inconnue.'),
                };
                $updated = true;
                $this->addFlash('success', 'Diplomatie mise à jour.');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
            $redirect = ['q' => $search, 'tab' => match ($action) {
                'rank_save' => 'ranks',
                'rank_assign' => 'members',
                'join' => $updated ? 'guilds' : 'join',
                'relation' => 'diplomacy',
                'propose', 'accept', 'break', 'rival', 'peace_propose', 'peace_accept', 'peace_reject' => 'relations',
                default => 'guilds',
            }];
            if ($action === 'rank_save') {
                $redirect['rank'] = $target;
                if ($updated) {
                    $guild = $relations->guild($membership);
                    $saved = $guild ? $repository->one(GuildRank::class, ['guild' => $guild, 'name' => trim($request->request->getString('name'))]) : null;
                    if ($saved) {
                        $redirect['rank'] = $saved->id;
                    }
                }
            }
            return $this->redirectToRoute('app_diplomacy', $redirect);
        }
        $ownGuild = $relations->guild($membership);
        // Valider l’onglet avant de préparer ses listes et formulaires.
        $tabs = $ownGuild ? [
            'guilds' => 'Ma guilde',
            'members' => 'Membres',
            'ranks' => 'Grades et permissions',
            'diplomacy' => 'Relations guildes',
            'relations' => 'Relations commandants',
        ] : ['guilds' => 'Créer une guilde', 'join' => 'Rejoindre une guilde', 'relations' => 'Relations commandants'];
        $tab = $request->query->getString('tab', 'guilds');
        if (!$ownGuild && ($tab === 'diplomacy' || $tab === 'guilds' && $search !== '')) {
            $tab = 'join';
        }
        if (!isset($tabs[$tab])) {
            $tab = 'guilds';
        }
        $incomingPersonalCount = 0;
        $personalContracts = $repository->personalContracts($membership);
        $guildRelationsByPeer = $ownGuild ? $repository->guildRelations($ownGuild) : [];
        foreach ($personalContracts as $contract) {
            $peer = $contract->playerA->id === $membership->id ? $contract->playerB : $contract->playerA;
            if (in_array($contract->status, ['pending', 'peace_pending'], true) && $contract->proposer->id !== $membership->id && $peer->isPlayerControlled() && $peer->universe->id === $membership->universe->id) {
                ++$incomingPersonalCount;
            }
        }
        $players = [];
        $searchPlayers = [];
        if ($tab === 'relations') {
            $rows = $relations->displayRows($membership, $repository->membershipIdentities($membership->universe), $ownGuild, $personalContracts, $guildRelationsByPeer);
            $players = array_values(array_filter($rows, static fn(array $row): bool => $row['contract'] || $row['allied'] || $row['enemy']));
            foreach ($repository->searchMessageRecipients($membership, $search) as $player) {
                if (isset($rows[$player->id])) {
                    $searchPlayers[] = $rows[$player->id];
                }
            }
        }
        $guilds = [];
        foreach ($repository->all(Guild::class, ['universe' => $membership->universe, 'archivedAt' => null]) as $guild) {
            $isOwn = $ownGuild?->id === $guild->id;
            $relation = $ownGuild && !$isOwn ? $guildRelationsByPeer[$guild->id] ?? null : null;
            $status = $isOwn ? 'allies' : $relation?->status ?? 'neutral';
            $matchesSearch = mb_strlen($search) >= 2 && mb_stripos($guild->name, $search) !== false;
            $showDetails = $isOwn && in_array($tab, ['guilds', 'members'], true) || !$isOwn && ($tab === 'diplomacy' && ($status !== 'neutral' || $relation?->proposal !== null || $matchesSearch)) || $tab === 'join' && $matchesSearch;
            $guilds[] = [
                'guild' => $guild,
                'status' => $status,
                'relation' => $relation,
                'members' => $showDetails ? $repository->all(GuildMember::class, ['guild' => $guild]) : [],
                'history' => $showDetails && $ownGuild && !$isOwn ? $repository->guildTreatyHistory($ownGuild, $guild) : [],
            ];
        }
        $guildRelations = [];
        $guildSearchResults = [];
        foreach ($guilds as $row) {
            if ($ownGuild && $row['guild']->id === $ownGuild->id) {
                continue;
            }
            if ($ownGuild && ($row['status'] !== 'neutral' || $row['relation']?->proposal !== null)) {
                $guildRelations[] = $row;
            }
            if (mb_strlen($search) >= 2 && mb_stripos($row['guild']->name, $search) !== false && count($guildSearchResults) < 20) {
                $guildSearchResults[] = $row;
            }
        }
        $ownGuildMember = $ownGuild ? $repository->one(GuildMember::class, ['playerUniverse' => $membership]) : null;
        $ranks = $ownGuild && in_array($tab, ['members', 'ranks'], true) ? $repository->all(GuildRank::class, ['guild' => $ownGuild], ['position' => 'ASC', 'id' => 'ASC']) : [];
        $rankAssignments = [];
        if ($ownGuildMember && $tab === 'members') {
            foreach ($repository->all(GuildMember::class, ['guild' => $ownGuild]) as $member) {
                if (!$access->canAssignRank($ownGuildMember, $member, null)) {
                    continue;
                }
                $rankAssignments[] = ['member' => $member, 'choices' => array_values(array_filter($ranks, fn(GuildRank $rank): bool => $access->canAssignRank($ownGuildMember, $member, $rank)))];
            }
        }
        $ownGuildRow = null;
        foreach ($guilds as $row) {
            if ($ownGuild && $row['guild']->id === $ownGuild->id) {
                $ownGuildRow = $row;
            }
        }
        $selectedRank = $ranks[0] ?? null;
        if ($request->query->has('rank')) {
            $selectedId = $request->query->getInt('rank');
            if ($selectedId === 0) {
                $selectedRank = null;
            } else {
                foreach ($ranks as $rank) {
                    if ($rank->id === $selectedId) {
                        $selectedRank = $rank;
                    }
                }
            }
        }
        $guildPermissions = [];
        foreach (DiplomacyAccessPolicy::PERMISSIONS as $permission => $label) {
            $guildPermissions[$permission] = $access->allows($ownGuildMember, $permission);
        }
        $memberPresence = $tab === 'members' && $ownGuild && $ownGuildRow && $catalog->forUniverse($membership->universe)['features']['guilds_enabled'] ? $presence->forMember($membership) : [];
        $response = $this->render('player/diplomacy.html.twig', [
            'incomingPersonalCount' => $incomingPersonalCount,
            'memberPresence' => $memberPresence,
            'tabs' => $tabs,
            'tab' => $tab,
            'ownRow' => $ownGuildRow,
            'selectedRank' => $selectedRank,
            'ownMember' => $ownGuildMember,
            'ranks' => $ranks,
            'rankAssignments' => $rankAssignments,
            'guildPermissions' => $guildPermissions,
            'rankPermissionLabels' => DiplomacyAccessPolicy::PERMISSIONS,
            'membership' => $membership,
            'search' => $search,
            'searchPlayers' => $searchPlayers,
            'players' => $players,
            'guilds' => $guilds,
            'guildRelations' => $guildRelations,
            'guildSearchResults' => $guildSearchResults,
            'guildPreview' => $ownGuild && $tab === 'guilds' ? $repository->guildConversation($ownGuild, 3) : [],
            'own' => $ownGuild,
            'enabled' => $catalog->forUniverse($membership->universe)['features']['guilds_enabled'],
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
