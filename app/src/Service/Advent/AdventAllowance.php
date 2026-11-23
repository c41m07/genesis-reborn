<?php

declare(strict_types=1);

namespace App\Service\Advent;

final class AdventAllowance
{
    private const TWICE_METAL_WEIGHTS = ['metal' => 2, 'nano' => 4, 'antimatter' => 8, 'hydrogen' => 1];

    /**
     * Les coûts passés sont déjà ceux du devis jeu. La remise de l’Avent
     * ne modifie ni l’électricité ni la durée et ne refait aucun calcul de file.
     * @param array<string, int> $costs
     * @return array{paid: array<string,int>, discount: array<string,int>}
     */
    public function payment(array $costs, int $budgetMetal, bool $research): array
    {
        if ($budgetMetal < 0 || $budgetMetal > 384000) {
            throw new \DomainException('Plafond de remise de l’Avent invalide.');
        }
        $maximum = [];
        foreach (self::TWICE_METAL_WEIGHTS as $resource => $weight) {
            $cost = $costs[$resource] ?? 0;
            if (!is_int($cost) || $cost < 0 || $cost > 2147483647) {
                throw new \DomainException('Quantité du devis incompatible avec la remise de l’Avent.');
            }
            $maximum[$resource] = $research ? intdiv($cost, 2) : $cost;
        }
        $value = AdventSeasonPlan::twiceMetal($maximum);
        $available = 2 * $budgetMetal;
        $discount = $maximum;
        if ($available < $value) {
            // Répartir de façon proportionnelle en unités entières. Distribuer
            // au plus une unité restante par poste dont l’arrondi a été tronqué.
            $remainders = [];
            foreach ($maximum as $resource => $amount) {
                $scaled = $amount * $available;
                $discount[$resource] = intdiv($scaled, $value);
                $remainders[$resource] = $scaled % $value;
            }
            arsort($remainders, SORT_NUMERIC);
            $available -= AdventSeasonPlan::twiceMetal($discount);
            foreach ($remainders as $resource => $remainder) {
                $weight = self::TWICE_METAL_WEIGHTS[$resource];
                if ($remainder > 0 && $weight <= $available && $discount[$resource] < $maximum[$resource]) {
                    ++$discount[$resource];
                    $available -= $weight;
                }
            }
        }
        $paid = $costs;
        foreach ($discount as $resource => $quantity) {
            if ($quantity > 0) {
                $paid[$resource] -= $quantity;
            }
        }

        return ['paid' => $paid, 'discount' => array_filter($discount, static fn (int $amount): bool => $amount > 0)];
    }

    /**
     * Le plafond protège les deux matériaux séparément. Le droit initial
     * n’est jamais réduit ; seul le versement présent est fractionné.
     * @param array<string,int> $remaining
     * @param array<string,int> $previousInWindow
     * @return array<string,int>
     */
    public function resourcePortion(array $remaining, array $previousInWindow, \DateTimeImmutable $joinedAt, \DateTimeImmutable $now): array
    {
        $limited = $now->getTimestamp() - $joinedAt->getTimestamp() < AdventSeasonPlan::FIRST_WALLET_SECONDS;
        $portion = [];
        foreach (AdventSeasonPlan::FIRST_WALLET as $resource => $cap) {
            $amount = max(0, $remaining[$resource] ?? 0);
            if ($limited) {
                $amount = min($amount, max(0, $cap - ($previousInWindow[$resource] ?? 0)));
            }
            if ($amount > 0) {
                $portion[$resource] = $amount;
            }
        }
        return $portion;
    }
}
