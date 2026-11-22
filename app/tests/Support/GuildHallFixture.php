<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Catalog\GameCatalog;
use App\Service\Guild\{GuildHallFunding, GuildHallService};
use App\Service\Player\GuildProjectRules;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class GuildHallFixture
{
    // Le pilote est installé dans ce seul noyau de recette, sans modifier
    // l'environnement du processus ni activer le Hall pour les autres tests.
    public static function enable(ContainerInterface $container, int $target = 32, int $donors = 1, int $minimum = 1): GuildHallService
    {
        $container->set(GuildHallFunding::class, new GuildHallFunding(
            $container->get(GuildProjectRules::class), $container->get(GameCatalog::class), $target, $donors, $minimum, true,
        ));

        return $container->get(GuildHallService::class);
    }
}
