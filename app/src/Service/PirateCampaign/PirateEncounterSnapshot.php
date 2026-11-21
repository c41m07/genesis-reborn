<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Service\Catalog\GameCatalog;

/** Instantané métier interne, à ne pas sérialiser dans l'offre ou exposer avant reconnaissance. */
final readonly class PirateEncounterSnapshot
{
    /**
     * @param array<string,array<string,int>> $composition
     * @param array<string,int> $buildCosts
     */
    private function __construct(
        private array $composition,
        private array $buildCosts,
    ) {
    }

    /**
     * Le futur générateur serveur remet composition et coûts calculés dans le même contexte.
     * Ici, aucun barème, aucun calcul de gain ni aucune relecture du catalogue au rejeu.
     *
     * @param array<string,array<string,int>> $composition Famille, rang, effectif.
     * @param array<string,int> $buildCosts Coût total de construction par ressource.
     */
    public static function freeze(array $composition, array $buildCosts): self
    {
        if ($composition === [] || $buildCosts === []) {
            throw new \DomainException('Composition adverse ou coût de construction absent.');
        }

        $sanitizedComposition = [];
        foreach ($composition as $slug => $ranks) {
            if (!is_string($slug) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $slug) !== 1 || !is_array($ranks) || $ranks === []) {
                throw new \DomainException('Famille de vaisseau pirate invalide.');
            }
            foreach ($ranks as $tier => $quantity) {
                if (!in_array($tier, GameCatalog::TIERS, true) || !is_int($quantity) || $quantity < 1) {
                    throw new \DomainException('Rang ou quantité de la flotte pirate invalide.');
                }
                // Copie profonde par valeur, y compris si l'appelant a construit un tableau de références.
                $sanitizedComposition[$slug][$tier] = (int) $quantity;
            }
            ksort($sanitizedComposition[$slug]);
        }
        ksort($sanitizedComposition);

        $sanitizedCosts = [];
        foreach ($buildCosts as $resource => $cost) {
            if (!in_array($resource, ['metal', 'nano', 'antimatter', 'hydrogen', 'electricity'], true) || !is_int($cost) || $cost < 0) {
                throw new \DomainException('Coût de construction de la flotte pirate invalide.');
            }
            $sanitizedCosts[$resource] = (int) $cost;
        }
        ksort($sanitizedCosts);

        return new self($sanitizedComposition, $sanitizedCosts);
    }

    /** @return array<string,array<string,int>> */
    public function composition(): array
    {
        return $this->composition;
    }

    /** @return array<string,int> */
    public function buildCosts(): array
    {
        return $this->buildCosts;
    }
}
