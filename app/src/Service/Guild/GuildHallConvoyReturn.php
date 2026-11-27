<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\Fleet;
use App\Service\Fleet\FleetHold;

/** Restitution des biens personnels d’un convoi de guilde, distincte du transport standard. */
final readonly class GuildHallConvoyReturn
{
    public function __construct(private FleetHold $hold)
    {
    }

    /**
     * L'appelant atteste la mission Hall et tient les verrous participation, flotte et
     * planète de retour dans la même transaction. Un false conserve le convoi intact
     * pour rechercher un repli atteignable ou signaler l’attente au propriétaire.
     */
    public function settleLocked(Fleet $fleet, string $token, \DateTimeImmutable $now): bool
    {
        if ($fleet->status !== 'returning' || !hash_equals($fleet->dispatchToken, $token)
            || $fleet->arrivalAt === null || $fleet->arrivalAt > $now) {
            return false;
        }

        // Ne pas créditer la colonie d’un tiers, ni recalculer un trajet silencieusement.
        $planet = $fleet->returnPlanet ?? $fleet->homePlanet;
        if ($planet === null || $planet->owner?->id !== $fleet->playerUniverse->id
            || $planet->solarSystem->galaxy->universe->id !== $fleet->playerUniverse->universe->id) {
            return false;
        }

        // Le trajet retour est arrivé : sa réserve est dépensée, pas déposée en
        // carburant dormant. Le fret dépassant le stock reste dans la flotte au sol.
        $this->consumeReturnReserveLocked($fleet);
        $this->hold->install($fleet, $planet);

        return true;
    }

    /** Le trajet est accompli même si son point d’arrivée n’est plus accessible au propriétaire. */
    public function consumeReturnReserveLocked(Fleet $fleet): void
    {
        $reserve = $fleet->reservedFuel;
        $hydrogen = (float) ($fleet->cargo['hydrogen'] ?? 0);
        $tolerance = max(1e-9, abs($reserve) * PHP_FLOAT_EPSILON * 64);
        if (!is_finite($reserve) || !is_finite($hydrogen) || $reserve < 0 || $hydrogen < 0
            || $reserve > $hydrogen + $tolerance) {
            throw new \DomainException('Réserve de retour du convoi incohérente. Aucun transfert effectué.');
        }

        $fleet->cargo['hydrogen'] = max(0.0, $hydrogen - $reserve);
        $fleet->reservedFuel = 0.0;
    }
}
