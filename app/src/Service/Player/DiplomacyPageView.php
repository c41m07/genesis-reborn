<?php

namespace App\Service\Player;

use App\Entity\{Guild, GuildMember, GuildRank, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

/** Prépare uniquement les listes et formulaires de la destination demandée. */
final class DiplomacyPageView
{
    public function __construct(
        private GameRepository $repository,
        private RelationResolver $relations,
        private DiplomacyAccessPolicy $access,
        private GuildPresence $presence,
        private SocialProfileView $profiles,
        private GameCatalog $catalog,
    ) {
    }

    public function prepare(PlayerUniverse $membership, ?Guild $own, string $page, string $search, int $rankId, int $afterId = 0): array
    {
        $enabled = (bool) $this->catalog->forUniverse($membership->universe)['features']['guilds_enabled'];
        $tab = match (true) {
            $page === 'guilds' && !$own => 'join',
            $page === 'diplomacy' && !$own => 'relations',
            default => $page,
        };
        $ownMember = $own ? $this->repository->one(GuildMember::class, ['playerUniverse' => $membership, 'guild' => $own]) : null;
        $guildPermissions = [];
        foreach (DiplomacyAccessPolicy::PERMISSIONS as $permission => $label) {
            $guildPermissions[$permission] = $this->access->allows($ownMember, $permission);
        }
        $data = [
            'membership' => $membership, 'own' => $own, 'ownMember' => $ownMember, 'enabled' => $enabled,
            'tab' => $tab, 'search' => $search,
            'pageTitle' => match ($tab) {
                'guilds' => 'Ma guilde', 'join' => 'Trouver une guilde', 'create' => 'Créer une guilde',
                'members' => 'Membres de ma guilde', 'ranks' => 'Grades et permissions', 'management' => 'Gestion de ma guilde',
                'relations' => 'Relations entre commandants', default => 'Relations entre guildes',
            }, 'guildPermissions' => $guildPermissions,
            'ownRow' => ['members' => []], 'guilds' => [], 'guildRelations' => [], 'guildSearchResults' => [],
            'ranks' => [], 'selectedRank' => null, 'rankAssignments' => [], 'memberPresence' => [],
            'rankPermissionLabels' => DiplomacyAccessPolicy::PERMISSIONS,
            'guildPreview' => [], 'profile' => null, 'guildRelationCounts' => [], 'players' => [], 'searchPlayers' => [],
            'incomingPersonalCount' => 0, 'incomingGuildCount' => 0, 'nextPersonalCursor' => null, 'afterPersonalCursor' => $afterId,
        ];
        // Les relations personnelles restent consultables même lorsque les guildes sont désactivées.
        if ($tab === 'relations') {
            $contracts = $this->repository->personalContracts($membership);
            $guildRelations = $own ? $this->repository->guildRelations($own) : [];
            $guildIds = $own ? [$own->id] : [];
            foreach ($guildRelations as $relation) {
                $peer = $relation->guildA->id === $own->id ? $relation->guildB : $relation->guildA;
                if ($peer->archivedAt === null && in_array($relation->status, ['allies', 'enemies'], true)) {
                    $guildIds[] = $peer->id;
                }
            }
            $players = $this->repository->personalDiplomacyPlayers($membership, $contracts, $guildIds, $afterId);
            if (count($players) > 50) {
                $players = array_slice($players, 0, 50);
                $data['nextPersonalCursor'] = $players[49]->id;
            }
            $rows = $this->relations->displayRows($membership, $players, $own, $contracts, $guildRelations);
            $data['players'] = array_values(array_filter($rows, static fn(array $row): bool => $row['contract'] || $row['allied'] || $row['enemy']));
            $matches = $this->repository->searchMessageRecipients($membership, $search);
            $data['searchPlayers'] = array_values($this->relations->displayRows($membership, $matches, $own, $contracts, $guildRelations));
            foreach ($contracts as $contract) {
                $peer = $contract->playerA->id === $membership->id ? $contract->playerB : $contract->playerA;
                if (in_array($contract->status, ['pending', 'peace_pending'], true) && $contract->proposer->id !== $membership->id && $peer->isPlayerControlled()) {
                    ++$data['incomingPersonalCount'];
                }
            }
            return $data;
        }
        if (!$enabled) {
            return $data;
        }
        if ($own && in_array($tab, ['members', 'management'], true)) {
            $data['ownRow']['members'] = $this->repository->all(GuildMember::class, ['guild' => $own]);
            $data['guilds'] = [['guild' => $own, 'members' => $data['ownRow']['members']]];
        }
        if ($own && in_array($tab, ['members', 'ranks'], true)) {
            $data['ranks'] = $this->repository->all(GuildRank::class, ['guild' => $own], ['position' => 'ASC', 'id' => 'ASC']);
            $data['selectedRank'] = $rankId === 0 ? null : ($data['ranks'][0] ?? null);
            foreach ($data['ranks'] as $rank) {
                if ($rank->id === $rankId) {
                    $data['selectedRank'] = $rank;
                }
            }
        }
        if ($own && $tab === 'members') {
            $data['memberPresence'] = $this->presence->forMember($membership);
            foreach ($data['ownRow']['members'] as $member) {
                if ($ownMember && $this->access->canAssignRank($ownMember, $member, null)) {
                    $choices = array_filter($data['ranks'], fn(GuildRank $rank): bool => $this->access->canAssignRank($ownMember, $member, $rank));
                    $data['rankAssignments'][] = ['member' => $member, 'choices' => array_values($choices)];
                }
            }
        }
        if ($own && $tab === 'guilds') {
            $data['guildRelationCounts'] = $this->repository->guildRelationCounts($own);
            $data['profile'] = $this->profiles->guild($membership, $own->id);
            $data['guildPreview'] = $this->repository->guildConversation($own, 3);
        }
        if ($tab === 'join' || ($own && $tab === 'diplomacy')) {
            $relations = $own ? $this->repository->guildRelations($own) : [];
            foreach ($this->repository->searchGuilds($membership, $search, $own) as $guild) {
                $data['guildSearchResults'][] = $this->guildRow($guild, $own, $relations);
            }
            foreach ($relations as $relation) {
                $peer = $relation->guildA->id === $own->id ? $relation->guildB : $relation->guildA;
                if ($peer->archivedAt !== null || $relation->status === 'neutral' && $relation->proposal === null) {
                    continue;
                }
                $data['guildRelations'][] = $this->guildRow($peer, $own, $relations);
                if ($relation->proposer && $relation->proposer->id !== $own->id && ($relation->proposal || $relation->status === 'pending')) {
                    ++$data['incomingGuildCount'];
                }
            }
        }
        return $data;
    }

    /** @param array<int, \App\Entity\GuildRelation> $relations Relations déjà limitées à l’univers du lecteur. */
    private function guildRow(Guild $guild, ?Guild $own, array $relations): array
    {
        $relation = $relations[$guild->id] ?? null;
        return [
            'guild' => $guild, 'status' => $relation?->status ?? 'neutral', 'relation' => $relation,
            'members' => $this->repository->all(GuildMember::class, ['guild' => $guild]),
            'history' => $own ? $this->repository->guildTreatyHistory($own, $guild) : [],
        ];
    }
}
