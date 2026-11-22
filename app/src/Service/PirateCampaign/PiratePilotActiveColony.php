<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Planet, Player};
use App\Repository\{GameRepository, PirateCampaignReadRepository};
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Request;

/** Résolution de lecture, sans requête d’activité ni modification de la sélection existante. */
#[When(env: 'dev')]
#[When(env: 'prod')]
final readonly class PiratePilotActiveColony
{
    public function __construct(
        private PiratePilotGate $gate,
        private Security $security,
        private ClockInterface $clock,
        private PirateCampaignReadRepository $campaigns,
        private GameRepository $repository,
    ) {
    }

    /** Une planète issue d'un paramètre de requête ne peut pas devenir la colonie active. */
    public function forRequest(Request $request): ?Planet
    {
        if (!$this->gate->isAvailable()) {
            return null;
        }
        $account = $this->security->getUser();
        if (!$account instanceof Player || $account->id === null || $account->currentPlayerUniverse?->id === null) {
            return null;
        }
        $at = $this->clock->now();
        $member = $this->campaigns->activeMembership($account->id, $account->currentPlayerUniverse->id, $at);
        if ($member === null || !$this->gate->admits($account, $member, $at)) {
            return null;
        }
        $key = 'selected_planet_'.$member->id;
        if ($request->hasSession() && $request->getSession()->has($key)) {
            $chosen = $request->getSession()->get($key);
            // Une sélection explicite devenue invalide reste neutre ; ne pas la remplacer au moment d'un clic.
            $planet = is_int($chosen) && $chosen > 0 ? $this->repository->find(Planet::class, $chosen) : null;
        } else {
            // Même choix initial que les pages du jeu, sans écriture en session. Le nom est affiché
            // avant confirmation et l'intention native lie la colonie exacte, pas le réglage par défaut.
            $planet = $this->repository->planets($member->universe, $member)[0] ?? null;
        }

        return $planet instanceof Planet && !$planet->npcPlanet && $planet->owner?->id === $member->id
            && $planet->owner->player?->id === $account->id
            && $planet->solarSystem->galaxy->universe->id === $member->universe->id ? $planet : null;
    }
}
