<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHall, GuildHallConvoy, SolarSystem};

/** Crédit partagé et preuve d’arrivée sous la transaction du traitement de flotte. */
final readonly class GuildHallConvoyArrival
{
    public function __construct(private GuildHallStockStore $stock)
    {
    }

    /**
     * L’appelant a verrouillé la participation, la guilde et le Hall exact (s’il
     * existe), puis la flotte et son reçu. Il a vérifié l’adhésion et choisit la
     * destination de retour ; un Hall dissous, remplacé ou interdit arrive ici à null.
     * La soute doit être celle après consommation du carburant aller.
     * Retourne false pour un message obsolète ou précoce ; ne programme pas le retour.
     */
    public function settleLocked(
        GuildHallConvoy $receipt,
        string $token,
        \DateTimeImmutable $now,
        ?GuildHall $hall,
        GuildHallBuildingSchedule $schedule,
    ): bool {
        $fleet = $receipt->fleet;
        if ($fleet->status !== 'traveling' || !hash_equals($fleet->dispatchToken, $token)
            || $fleet->arrivalAt === null || $fleet->arrivalAt > $now || !$receipt->canSettle($token)) {
            return false;
        }

        $system = $fleet->destinationSystem;
        $delivered = [];
        if ($hall !== null && $hall->id === $receipt->hallId && $hall->guild->id === $receipt->guildId
            && $hall->guild->universe->id === $fleet->playerUniverse->universe->id
            && $system instanceof SolarSystem && $system->id !== null && $hall->solarSystem?->id === $system->id
            && $hall->placedAt !== null && $hall->guild->archivedAt === null) {
            $result = $this->stock->receiveConvoyLocked($hall, $schedule, $fleet->cargo, $fleet->reservedFuel);
            $fleet->cargo = $result['retained'];
            $delivered = $result['delivered'];
        }

        // L'acquittement du dépôt, la soute et le prochain envoi devront être
        // persistés ensemble par le traitement de flotte, jamais en deux transactions.
        return $receipt->settle($token, $now, $delivered);
    }
}
