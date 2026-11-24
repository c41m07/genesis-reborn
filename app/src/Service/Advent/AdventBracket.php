<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Service\Requirement\{CatalogAccess, RequirementChecker};

final readonly class AdventBracket
{
    private const COMBAT_HULLS = [
        'fighter', 'interceptor', 'heavy_fighter', 'bomber', 'corvette',
        'stealth_fighter', 'frigate', 'destroyer', 'cruiser', 'battleship', 'capital',
    ];
    private const VETERAN_HULLS = ['cruiser', 'battleship', 'capital'];

    public function __construct(private RequirementChecker $requirements) {}

    /**
     * Même déblocage que le chantier : prérequis de la colonie et de
     * l’appartenance ou acquis pérenne du catalogue courant. Le stock, les
     * vaisseaux possédés, les files et les bonus ne font pas partie de l’entrée.
     * @param array<int,array<string,array<string,int>>> $ownedColonies Niveaux par colonie possédée.
     * @param array<string,array<string,int>> $technologies
     * @param array<string,mixed> $unlockKeys
     * @param array<string,mixed> $catalog Catalogue résolu dans la vue du relevé.
     */
    public function classify(array $ownedColonies, array $technologies, array $unlockKeys, array $catalog): ?string
    {
        if (($unlockKeys['_requirements_version'] ?? null) !== CatalogAccess::VERSION) {
            // Une ancienne grille ne prouve ni un rang avancé ni un débutant.
            return null;
        }
        $combat = false;
        foreach ($ownedColonies as $id => $levels) {
            foreach (self::COMBAT_HULLS as $slug) {
                $definition = $catalog['ships'][$slug] ?? null;
                if ($definition === null || isset($definition['economic_unit'])) {
                    continue;
                }
                foreach (['superior', 'intermediate'] as $tier) {
                    $key = 'v'.CatalogAccess::VERSION.':planet:'.$id.':ships:'.$slug.':'.$tier;
                    if (!isset($unlockKeys[$key]) && $this->requirements->check(
                        $this->requirements->forTier($definition, $tier), $levels, $technologies, [], $catalog,
                    ) !== []) {
                        continue;
                    }
                    if ($tier === 'superior' && in_array($slug, self::VETERAN_HULLS, true)) {
                        return 'veteran';
                    }
                    $combat = true;
                }
            }
        }

        return $combat ? 'intermediate' : 'beginner';
    }
}
