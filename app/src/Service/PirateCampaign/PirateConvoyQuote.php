<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatSimulator;

/** Montants d'un convoi privé, sans dépôt ni choix d'arrondi. */
final readonly class PirateConvoyQuote
{
    private const RESOURCES = ['metal', 'nano', 'antimatter', 'hydrogen'];

    private function __construct(private array $parts)
    {
    }

    /**
     * À la préparation de la reconnaissance, figer le coût catalogue effectif de chaque famille/rang.
     * Ne pas utiliser une facture de joueur : aucun modificateur de commande, électricité ou prix à l'arrivée.
     * @param array<string,array<string,int>> $composition
     */
    public static function captureCatalogue(GameCatalog $catalog, Universe $universe, array $composition): PirateEncounterSnapshot
    {
        $totals = array_fill_keys(self::RESOURCES, 0);
        // Le normaliseur de rencontre contrôle déjà la forme et les quantités.
        $encounter = PirateEncounterSnapshot::freeze($composition, $totals);
        $ships = $encounter->composition();
        if (array_sum(array_map('array_sum', $ships)) > CombatSimulator::MAX_UNITS) {
            throw new \DomainException('La rencontre dépasse la capacité du moteur de combat.');
        }
        foreach ($ships as $slug => $ranks) {
            foreach ($ranks as $tier => $quantity) {
                $definition = $catalog->getTierForUniverse($universe, 'ships', $slug, $tier);
                $costs = $catalog->costs($definition, 1, $quantity, 'ships');
                foreach (self::RESOURCES as $resource) {
                    if ($costs[$resource] > PHP_INT_MAX - $totals[$resource]) {
                        throw new \DomainException('Le coût catalogue figé du convoi est trop grand.');
                    }
                    $totals[$resource] += $costs[$resource];
                }
            }
        }

        return PirateEncounterSnapshot::freeze($encounter->composition(), $totals);
    }

    public static function halfOfFrozenCatalogue(PirateEncounterSnapshot $catalogueSnapshot): self
    {
        $parts = [];
        $costs = $catalogueSnapshot->buildCosts();
        foreach (self::RESOURCES as $resource) {
            $amount = $costs[$resource] ?? 0;
            // Par exemple 83 devient exactement 41 + ½, pas 41 ni 42.
            $parts[$resource] = ['whole' => intdiv($amount, 2), 'extra_half' => $amount % 2 === 1];
        }

        return new self($parts);
    }

    /** @return array<string,array{whole:int,extra_half:bool}> Proposition, jamais un ordre de crédit. */
    public function exactParts(): array
    {
        return $this->parts;
    }

    /** @return array<string,float> Quantités représentables exactement dans les stocks réels (DOUBLE). */
    public function depositAmounts(): array
    {
        $amounts = [];
        foreach ($this->parts as $resource => $part) {
            $whole = $part['whole'];
            $half = $part['extra_half'];
            // Au-delà de la précision du stockage, ne jamais arrondir discrètement le droit.
            if ($whole > ($half ? 4503599627370495 : 9007199254740991)) {
                throw new \DomainException('Le stock ne peut pas représenter exactement le montant du convoi.');
            }
            $amounts[$resource] = (float) $whole + ($half ? 0.5 : 0.0);
        }

        return $amounts;
    }
}
