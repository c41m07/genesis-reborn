<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\{Planet, Player, PlayerUniverse};
use App\Service\Player\FirstStepsView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FirstStepsExtension extends AbstractExtension
{
    public function __construct(private FirstStepsView $view)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('first_steps_preview', $this->preview(...))];
    }

    /** @return array{next:string,guide:array,count:int,total:int,validation:string}|null */
    public function preview(?Player $player, ?PlayerUniverse $membership, ?Planet $planet, bool $exerciseInResponse = false): ?array
    {
        // Réutiliser la colonie du shell, jamais les données de la fiche consultée par un tiers.
        if ($player?->id === null || $player->lockedAt !== null || $player->emailVerifiedAt === null
            || !($player->preferences['firstStepsGuide'] ?? true) || $membership?->id === null
            || $membership->player?->id !== $player->id || $planet?->id === null
            || $planet->owner?->id !== $membership->id
            || $planet->solarSystem->galaxy->universe->id !== $membership->universe->id) {
            return null;
        }

        return $this->view->preview($membership, $planet, $exerciseInResponse);
    }
}
