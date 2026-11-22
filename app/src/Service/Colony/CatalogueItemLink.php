<?php

declare(strict_types=1);

namespace App\Service\Colony;

use App\Service\Catalog\GameCatalog;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Lien partagé vers la fiche et son rang, selon la navigation native du catalogue. */
final readonly class CatalogueItemLink
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    public function focus(int $planetId, string $group, string $slug, string $tier): ?string
    {
        if ($planetId < 1 || !in_array($group, ['buildings', 'technologies', 'ships', 'defenses'], true)
            || preg_match('/^[a-z0-9_]+$/D', $slug) !== 1 || !in_array($tier, GameCatalog::TIERS, true)) {
            return null;
        }

        // Le catalogue choisit le bon rang, rend la fiche visible, la souligne et replace le focus.
        return $this->urls->generate('app_colony', [
            'id' => $planetId, 'section' => $group, 'showLockedItems' => 1, 'focusItem' => 1,
            '_fragment' => 'item-'.$slug.'-'.$tier,
        ]);
    }
}
