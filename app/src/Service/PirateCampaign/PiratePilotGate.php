<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Player, PlayerUniverse, Universe};
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Admission au dossier privé via le réglage persistant de l’univers du joueur. */
final readonly class PiratePilotGate
{
    public function __construct(
        #[Autowire('%kernel.environment%')] private string $kernelEnvironment,
    ) {
    }

    /** Une image en mode prod peut également tourner sur Alpha : aucune déduction d'hôte ou de slug. */
    public function isAvailable(): bool
    {
        return in_array($this->kernelEnvironment, ['dev', 'prod'], true);
    }

    /**
     * Le compte provient de Security et la participation active d'une relecture propriétaire en base.
     * Ne jamais construire cet appel avec un compte, univers ou identifiant fourni par le formulaire.
     */
    public function admits(?Player $account, ?PlayerUniverse $membership, \DateTimeImmutable $at): bool
    {
        if (!$this->isAvailable() || ($account?->id ?? 0) < 1 || $account->lockedAt !== null
            || $account->emailVerifiedAt === null || $account->emailVerifiedAt > $at
            || ($membership?->id ?? 0) < 1 || $account->currentPlayerUniverse?->id !== $membership->id
            || !$membership->isPlayerControlled() || $membership->player?->id !== $account->id
            || $membership->player->currentPlayerUniverse?->id !== $membership->id
            || $membership->player->lockedAt !== null || $membership->player->emailVerifiedAt === null
            || $membership->player->emailVerifiedAt > $at) {
            return false;
        }

        return $this->matchesWorld($membership->universe, $at);
    }

    /** Utiliser après le verrou de la participation et de l'univers dans les départs natifs. */
    public function matchesWorld(Universe $universe, \DateTimeImmutable $at): bool
    {
        return $this->isAvailable() && ($universe->id ?? 0) > 0 && $universe->isAccessible($at)
            && $universe->pirateCampaignEnabled();
    }
}
