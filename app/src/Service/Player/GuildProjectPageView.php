<?php

namespace App\Service\Player;

use App\Entity\{Guild, GuildMember, GuildProject, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Boost\BoostGrantService;
use App\Service\Catalog\GameCatalog;

/** Prépare les lectures de la page consultée, sans lancer de projet ni distribuer de récompense. */
final class GuildProjectPageView
{
    private const PAGE_SIZE = 25;

    public function __construct(
        private GameRepository $repository,
        private GameCatalog $catalog,
        private DiplomacyAccessPolicy $access,
        private GuildProjectRules $rules,
        private BoostGrantService $boosts,
        private VacationCalendar $vacations,
    ) {
    }

    public function prepareList(PlayerUniverse $actor, ?Guild $guild, string $page, int $beforeId): array
    {
        $data = [
            'membership' => $actor, 'guild' => $guild, 'page' => $page,
            'pageTitle' => match ($page) {
                'available' => 'Projets disponibles', 'history' => 'Historique des projets', default => 'Projets en cours',
            },
            'enabled' => (bool) $this->catalog->forUniverse($actor->universe)['features']['guilds_enabled'],
            'rows' => [], 'available' => [], 'canStart' => false, 'readOnly' => false,
            'resources' => $this->catalog->rules()['resources'], 'beforeCursor' => $beforeId, 'nextCursor' => null,
        ];
        if (!$guild || !$data['enabled']) return $data;

        $items = $page === 'available'
            ? $this->repository->availableGuildProjectDefinitions($guild, $beforeId)
            : $this->repository->guildProjectsPage($guild, $page === 'history', $beforeId);
        if (count($items) > self::PAGE_SIZE) {
            $items = array_slice($items, 0, self::PAGE_SIZE);
            $data['nextCursor'] = $items[self::PAGE_SIZE - 1]->id;
        }
        $definitionIds = array_map(static fn($item): int => $page === 'available' ? $item->id : $item->definition->id, $items);
        $rewarded = $this->boosts->guildProjectRewardedDefinitions($actor, $definitionIds);
        if ($page === 'available') {
            $member = $this->repository->one(GuildMember::class, ['playerUniverse' => $actor, 'guild' => $guild]);
            $data['readOnly'] = $this->vacations->isActive($actor);
            $data['canStart'] = !$data['readOnly'] && $this->access->allows($member, 'projects.start');
            foreach ($items as $definition) $data['available'][] = ['definition' => $definition, 'rewarded' => isset($rewarded[$definition->id])];
        } else {
            $own = $this->repository->ownGuildProjectContributions($actor, array_column($items, 'id'));
            foreach ($items as $project) {
                $data['rows'][] = $this->projectRow($project, $own[$project->id] ?? [], isset($rewarded[$project->definition->id]));
            }
        }
        return $data;
    }

    public function prepareDetail(GuildProject $project, PlayerUniverse $actor, int $beforeId): array
    {
        $own = $this->repository->ownGuildProjectContributions($actor, [$project->id]);
        $row = $this->projectRow($project, $own[$project->id] ?? [], $this->boosts->hasGuildProjectReward($actor, $project->definition->id));
        $contributions = $this->repository->guildProjectContributionsPage($project, $beforeId);
        $next = null;
        if (count($contributions) > self::PAGE_SIZE) {
            $contributions = array_slice($contributions, 0, self::PAGE_SIZE);
            $next = $contributions[self::PAGE_SIZE - 1]->id;
        }
        $identities = [];
        foreach ($this->repository->membershipIdentities($actor->universe, array_column($contributions, 'playerUniverseId')) as $member) {
            $identities[$member->id] = $member;
        }
        foreach ($contributions as $contribution) {
            $row['contributions'][] = [
                'name' => ($identities[$contribution->playerUniverseId] ?? null)?->getDisplayName() ?? 'Ancien membre #'.$contribution->playerUniverseId,
                'resources' => $contribution->resources,
            ];
        }
        // Le don personnel reste complet même lorsqu’il n’appartient pas à la page des contributeurs affichée.
        return [
            'row' => $row, 'contributionCursor' => $beforeId, 'nextContributionCursor' => $next,
            'readOnly' => $project->status === 'active' && $this->vacations->isActive($actor),
            'planets' => $project->status === 'active' ? $this->repository->planets($actor->universe, $actor) : [],
            'resources' => $this->catalog->rules()['resources'],
        ];
    }

    /** @param array<string, int> $own */
    private function projectRow(GuildProject $project, array $own, bool $rewarded): array
    {
        return [
            'project' => $project,
            'remaining' => $project->status === 'active' ? $this->rules->remaining($project->snapshot['stages'][$project->stageIndex]['costs'], $project->progress) : [],
            'own' => $own, 'qualified' => $this->rules->eligible($own, $project->snapshot['minimums']),
            'rewarded' => $rewarded, 'contributions' => [],
        ];
    }
}
