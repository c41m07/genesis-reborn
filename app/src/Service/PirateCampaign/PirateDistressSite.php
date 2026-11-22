<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PlayerUniverse, SolarSystem};

/** Ancre privée dans un système existant, sans planète créée ni case réservée. */
final readonly class PirateDistressSite
{
    private function __construct(
        private int $membershipId,
        private int $playerId,
        private int $universeId,
        private int $systemId,
    ) {
    }

    public static function inExistingSystem(PlayerUniverse $membership, SolarSystem $system): self
    {
        $playerId = $membership->player?->id;
        $universeId = $membership->universe->id;
        if (!$membership->isPlayerControlled() || ($membership->id ?? 0) < 1 || ($playerId ?? 0) < 1
            || ($universeId ?? 0) < 1 || ($system->id ?? 0) < 1 || $system->galaxy->universe->id !== $universeId) {
            throw new \DomainException('Le poste civil exige un empire actif et un système existant de cet univers.');
        }

        return new self($membership->id, $playerId, $universeId, $system->id);
    }

    /** Pour la future mission dédiée : le poste n'est ni une planète voisine ni un système d'un autre univers. */
    public function matchesSystem(SolarSystem $system): bool
    {
        return $system->id === $this->systemId && $system->galaxy->universe->id === $this->universeId;
    }

    /** @return array{title: string, message: string}|null */
    public function signalFor(PlayerUniverse $viewer): ?array
    {
        if ($viewer->id !== $this->membershipId || !$viewer->isPlayerControlled()
            || $viewer->player?->id !== $this->playerId || $viewer->universe->id !== $this->universeId) {
            return null;
        }

        // Ce message ne révèle ni le nom d'un système inconnu ni la flotte ennemie.
        // Le futur parcours expose une destination une fois ses conditions de découverte vérifiées.
        return [
            'title' => 'Signal de détresse civil',
            'message' => 'Ici le relais civil. Des pirates nous encerclent. Envoyez une sonde vers notre poste pour identifier les assaillants.',
        ];
    }
}
