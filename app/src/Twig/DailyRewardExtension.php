<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\{Planet, Player};
use App\Security\DailyRewardLoginPrompt;
use App\Service\Player\DailyRewardService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class DailyRewardExtension extends AbstractExtension
{
    public function __construct(
        private DailyRewardService $rewards,
        private DailyRewardLoginPrompt $loginPrompt,
        private RequestStack $requests,
    ) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('daily_reward_header', $this->header(...))];
    }

    /** @return array{available:bool,autoOpen:bool} */
    public function header(?Player $player, ?Planet $planet, bool $gamePage): array
    {
        $membership = $player?->currentPlayerUniverse;
        if ($player?->id === null || $player->lockedAt !== null || $player->emailVerifiedAt === null || $planet === null
            || $membership?->id === null || !$membership->isPlayerControlled()
            || $planet->owner?->id !== $membership->id || $membership->player?->id !== $player->id
            || $planet->solarSystem->galaxy->universe->id !== $membership->universe->id) {
            return ['available' => false, 'autoOpen' => false];
        }

        // Le shell a déjà sélectionné et vérifié l’univers : ne pas recharger les colonies ou leurs stocks.
        $request = $this->requests->getCurrentRequest();
        $available = !$this->rewards->status($player)['claimed'];
        $autoOpen = $request !== null && $request === $this->requests->getMainRequest()
            && $this->loginPrompt->consumeOnGamePage($request, $player->id, $gamePage, $available);

        return ['available' => $available, 'autoOpen' => $autoOpen];
    }
}
