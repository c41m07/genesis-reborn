<?php

declare(strict_types=1);

namespace App\Service\Universe;

use App\Entity\Galaxy;
use App\Entity\Planet;
use App\Entity\SolarSystem;
use App\Entity\Universe;
use App\Manager\GameManager;
use App\Repository\GameRepository;

final class UniverseCreationService
{
    public function __construct(
        private readonly GameManager $manager,
        private readonly GameRepository $repo,
    ) {
    }

    public function create(string $name, string $slug, string $profile, int $galaxyCount, int $systemsPerGalaxy): Universe
    {
        $name = trim($name);
        $slug = strtolower(trim($slug));

        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            throw new \DomainException('Le nom de l’univers doit contenir entre 3 et 100 caractères.');
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || mb_strlen($slug) > 64) {
            throw new \DomainException('Le code doit contenir uniquement des lettres minuscules, chiffres et tirets.');
        }
        if ($this->repo->one(Universe::class, ['slug' => $slug]) !== null) {
            throw new \DomainException('Un univers utilise déjà ce code.');
        }
        if ($galaxyCount < 1 || $galaxyCount > 5 || $systemsPerGalaxy < 1 || $systemsPerGalaxy > 100) {
            throw new \DomainException('La carte doit contenir de 1 à 5 galaxies et de 1 à 100 systèmes par galaxie.');
        }

        $profileConfig = null;
        foreach (self::profiles() as $availableProfile) {
            if ($availableProfile['slug'] === $profile) {
                $profileConfig = $availableProfile;
                break;
            }
        }
        if ($profileConfig === null) {
            throw new \DomainException('Profil de règles inconnu.');
        }

        $speed = $profileConfig['speed'];
        $universe = new Universe();
        $universe->name = $name;
        $universe->slug = $slug;
        $universe->status = 'active';
        $universe->gameSettings = [
            'features' => ['guilds_enabled' => true, 'planet_conquest' => true],
            'multipliers' => [
                'building_time' => 1 / $speed,
                'research_time' => 1 / $speed,
                'ship_build_time' => 1 / $speed,
                'resource_production' => $speed,
                'travel_time' => 1 / $speed,
            ],
            'limits' => ['max_planets' => 5, 'max_fleet_size' => 100],
            'catalog_overrides' => [],
        ];

        $this->manager->save($universe);
        $this->manager->flush();

        for ($galaxyIndex = 0; $galaxyIndex < $galaxyCount; ++$galaxyIndex) {
            $galaxy = new Galaxy();
            $galaxy->universe = $universe;
            $galaxy->name = $this->galaxyName($galaxyIndex);
            $this->manager->save($galaxy);

            for ($systemIndex = 0; $systemIndex < $systemsPerGalaxy; ++$systemIndex) {
                $systemNumber = $systemIndex + 1;
                $system = new SolarSystem();
                $system->galaxy = $galaxy;
                $system->name = sprintf('Système %03d', $systemNumber);
                $system->coordX = ($systemIndex % 10) * 3;
                $system->coordY = intdiv($systemIndex, 10) * 3;
                $this->manager->save($system);

                $npcPositions = range(1, 9);
                shuffle($npcPositions);
                $npcPositions = array_flip(array_slice($npcPositions, 0, random_int(2, 3)));

                for ($position = 1; $position <= 9; ++$position) {
                    $planet = new Planet();
                    $planet->solarSystem = $system;
                    $planet->name = $system->name.' · '.$position;
                    $planet->position = $position;
                    $planet->npcPlanet = isset($npcPositions[$position]);
                    if ($planet->npcPlanet) {
                        $planet->conquestLevel = (int) ceil($position / 2);
                    }
                    $this->manager->save($planet);
                }
            }
        }

        return $universe;
    }

    /** @return list<array{slug:string,name:string,speed:float}> */
    public static function profiles(): array
    {
        $profiles = [['slug' => 'normal', 'name' => 'Normal (×1)', 'speed' => 1.0]];
        for ($speed = 2; $speed <= 10; ++$speed) {
            $profiles[] = ['slug' => 'x'.$speed, 'name' => 'x'.$speed, 'speed' => (float) $speed];
        }
        $profiles[] = ['slug' => 'alpha', 'name' => 'Alpha (×40)', 'speed' => 40.0];
        $profiles[] = ['slug' => 'beta', 'name' => 'Beta (×20)', 'speed' => 20.0];

        return $profiles;
    }

    private function galaxyName(int $index): string
    {
        $name = '';
        do {
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return 'Galaxie '.$name;
    }
}
