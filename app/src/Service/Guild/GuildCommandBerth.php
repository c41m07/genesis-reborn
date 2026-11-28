<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHall, GuildHallBuilding, GuildHallConvoy};
use App\Repository\GameRepository;

/** Une flotte privée garde sa colonie d'origine et sa réserve de retour. */
final class GuildCommandBerth
{
    public const DOCKED = 'hall_stationed';

    public static function built(GameRepository $repository, GuildHall $hall): bool
    {
        $building = $repository->one(GuildHallBuilding::class, ['hall' => $hall, 'slug' => GuildHallBuilding::COMMAND]);

        return $building instanceof GuildHallBuilding && $building->level > 0;
    }

    /** Contrôle métier après les verrous, l’adhésion courante et le débit du trajet aller. */
    public static function arrive(GuildHallConvoy $receipt, GuildHall $hall, string $token, \DateTimeImmutable $now): bool
    {
        $fleet = $receipt->fleet;
        if (!$receipt->commandStation || $receipt->shipDonation || $hall->id === null || $hall->id !== $receipt->hallId
            || $hall->placedAt === null || $hall->guild->id !== $receipt->guildId || $hall->guild->archivedAt !== null
            || $fleet->status !== 'traveling' || $fleet->arrivalAt === null || $fleet->arrivalAt > $now
            || !hash_equals($fleet->dispatchToken, $token)
            || $hall->solarSystem === null || $hall->solarSystem->id === null
            || $fleet->destinationSystem?->id !== $hall->solarSystem->id
            || !$receipt->settle($token, $now, [])) {
            return false;
        }

        $fleet->status = self::DOCKED;
        $fleet->arrivalAt = null;

        return true;
    }
}
