<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Planet, PlayerUniverse};
use App\Repository\GameRepository;
use Symfony\Component\DependencyInjection\Attribute\When;

/** Les identifiants de planètes ne sont ni exposés ni acceptés en tant qu'ordres. */
#[When(env: 'dev')]
#[When(env: 'prod')]
final readonly class PiratePilotDestinationSelection
{
    public function __construct(private GameRepository $repository)
    {
    }

    /** @return array{PiratePilotCommandOptions, array<int,Planet>}|null */
    public function forOwner(PlayerUniverse $member, PiratePilotCommandAction $action, \Closure $eligible): ?array
    {
        $planets = [];
        foreach ($this->repository->planets($member->universe, $member) as $planet) {
            if ($planet->id !== null && !$planet->npcPlanet && $planet->owner?->id === $member->id && $eligible($planet)) {
                $planets[$planet->id] = $planet;
            }
        }
        if ($planets === [] || count($planets) > PiratePilotCommandOptions::MAX_OPTIONS) {
            return null;
        }
        ksort($planets, SORT_NUMERIC);

        return [PiratePilotCommandOptions::destinations($action, array_keys($planets)), $planets];
    }

    /** @return list<array{ordinal:int,label:string}> */
    public function labels(array $planets): array
    {
        $labels = [];
        foreach ($planets as $planet) {
            $labels[] = ['ordinal' => count($labels) + 1, 'label' => $planet->name];
        }

        return $labels;
    }
}
