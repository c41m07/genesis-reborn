<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Player, PlayerUniverse};
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Garde préparée pour le pilote local, non raccordée à une route ou à la navigation. */
final readonly class PiratePilotGate
{
    private const PILOT_UNIVERSE = 'omega-1';

    public function __construct(
        #[Autowire('%kernel.environment%')] private string $kernelEnvironment,
        private bool $localPilotEnabled = false,
        private int $localOmegaUniverseId = 0,
    ) {
    }

    /** Avant toute lecture privée : la branche Git, l’hôte HTTP et le slug ne prouvent rien. */
    public function isLocallyEnabled(): bool
    {
        return $this->kernelEnvironment === 'dev' && $this->localPilotEnabled && $this->localOmegaUniverseId > 0;
    }

    /**
     * Le compte provient de Security et la participation active d'une relecture propriétaire en base.
     * Ne jamais construire cet appel avec un compte, univers ou identifiant fourni par le formulaire.
     */
    public function admits(?Player $account, ?PlayerUniverse $membership, \DateTimeImmutable $at): bool
    {
        if (!$this->isLocallyEnabled() || ($account?->id ?? 0) < 1 || $account->lockedAt !== null
            || $account->emailVerifiedAt === null || $account->emailVerifiedAt > $at
            || ($membership?->id ?? 0) < 1 || $account->currentPlayerUniverse?->id !== $membership->id
            || !$membership->isPlayerControlled() || $membership->player?->id !== $account->id
            || $membership->player->currentPlayerUniverse?->id !== $membership->id
            || $membership->player->lockedAt !== null || $membership->player->emailVerifiedAt === null
            || $membership->player->emailVerifiedAt > $at) {
            return false;
        }

        $universe = $membership->universe;

        return $universe->id === $this->localOmegaUniverseId
            && $universe->slug === self::PILOT_UNIVERSE
            && $universe->isAccessible($at);
    }
}
