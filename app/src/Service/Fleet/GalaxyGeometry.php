<?php

namespace App\Service\Fleet;

/** Géographie commune aux devis, aux replis et aux représentations cartographiques. */
final class GalaxyGeometry
{
    public const SPIRAL = 'spiral-v1';
    public const GRID = 'grid-v1';
    public const UNITS_TO_AU = 100;
    public const EXTENT = 15.0;

    /** @return array{x: float, y: float} Coordonnées physiques, une unité vaut 100 UA. */
    public function position(int|float $gridX, int|float $gridY): array
    {
        if (!is_finite((float) $gridX) || !is_finite((float) $gridY)) {
            throw new \DomainException('Coordonnées de système invalides.');
        }

        // Les coordonnées persistées restent les identifiants historiques de la grille.
        // Les 80 systèmes de référence occupent huit bras de dix positions.
        $arm = $gridY / 3;
        $progress = .26 + ($gridX / 27) * .74;
        $radius = (.09 + $progress * .86) * self::EXTENT;
        // Les cartes historiques plus grandes restent distinctes après le huitième bras.
        // Le décalage d’or évite de superposer leurs systèmes à ceux du premier tour.
        $cycle = floor($arm / 8);
        $angle = $arm * M_PI / 4 + $progress * 1.8 + $cycle * M_PI * (3 - sqrt(5));

        return ['x' => cos($angle) * $radius, 'y' => sin($angle) * $radius];
    }

    public function distance(int|float $originX, int|float $originY, int|float $targetX, int|float $targetY): float
    {
        $origin = $this->position($originX, $originY);
        $target = $this->position($targetX, $targetY);

        return hypot($target['x'] - $origin['x'], $target['y'] - $origin['y']) * self::UNITS_TO_AU;
    }
}
