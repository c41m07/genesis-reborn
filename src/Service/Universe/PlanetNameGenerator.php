<?php

declare(strict_types=1);

namespace App\Service\Universe;

use App\Entity\PlayerUniverse;
use App\Repository\GameRepository;

final class PlanetNameGenerator
{
    private const PREFIXES = [
        'Astra', 'Aster', 'Bel', 'Calyp', 'Cassi', 'Celes', 'Dara', 'Elar', 'Ely', 'Fara',
        'Gala', 'Helia', 'Ilya', 'Jora', 'Kaela', 'Luma', 'Lyra', 'Mira', 'Nera', 'Nova',
        'Orea', 'Phae', 'Quora', 'Rhea', 'Sola', 'Talia', 'Vega', 'Vesper', 'Xeno', 'Zora',
    ];

    private const SUFFIXES = [
        'dora', 'lia', 'nara', 'thea', 'ria', 'sion', 'thys', 'mera', 'phos', 'vane',
        'ora', 'eon', 'aris', 'une', 'ara', 'iel', 'ion', 'yss', 'eth', 'os',
    ];

    public function __construct(private readonly GameRepository $repo)
    {
    }

    public function generate(PlayerUniverse $owner): string
    {
        $existing = [];
        $planets = $owner->id === null ? [] : $this->repo->planets($owner->universe, $owner);
        foreach ($planets as $planet) {
            $existing[mb_strtolower($planet->name)] = true;
        }

        for ($attempt = 0; $attempt < 40; ++$attempt) {
            $name = self::PREFIXES[array_rand(self::PREFIXES)].self::SUFFIXES[array_rand(self::SUFFIXES)];
            if (!isset($existing[mb_strtolower($name)])) {
                return $name;
            }
        }

        $base = self::PREFIXES[array_rand(self::PREFIXES)].self::SUFFIXES[array_rand(self::SUFFIXES)];
        for ($suffix = 2; ; ++$suffix) {
            $name = $base.' '.$suffix;
            if (!isset($existing[mb_strtolower($name)])) {
                return $name;
            }
        }
    }
}
