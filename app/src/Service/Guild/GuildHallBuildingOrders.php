<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHallBuilding, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Player\DiplomacyAccessPolicy;

/** Point d’entrée métier des chantiers du Hall ; aucun devis client ne peut fixer le prix. */
final readonly class GuildHallBuildingOrders
{
    public function __construct(
        private GameManager $manager,
        private GuildHallAccess $access,
        private DiplomacyAccessPolicy $diplomacy,
        private GuildHallConstruction $construction,
        private GuildHallScheduleProvider $schedules,
    ) {
    }

    public function start(
        PlayerUniverse $actor,
        int $guildId,
        int $hallId,
        string $slug,
        int $expectedLevel,
    ): GuildHallBuilding {
        return $this->manager->transaction(function () use ($actor, $guildId, $hallId, $slug, $expectedLevel): GuildHallBuilding {
            $hall = $this->access->lockPlaced($actor, $guildId, $hallId);
            if (!$this->diplomacy->canCommand($actor, $hall->guild)) {
                throw new \DomainException('Seul le commandant actuel peut lancer un chantier du Hall.');
            }
            // Le niveau envoyé correspond à l’état que le commandant a confirmé.
            // Le service de chantier le compare au niveau verrouillé avant tout débit.
            $schedule = $this->schedules->schedule();
            if ($schedule === null) {
                throw new \DomainException('Les opérations du Hall ne sont pas encore configurées.');
            }
            $quote = $schedule->quote($slug, $expectedLevel);

            return $this->construction->startLocked(
                $hall, $slug, $expectedLevel, $quote['maxLevel'], $quote['cost'], $quote['duration'],
            );
        });
    }
}
