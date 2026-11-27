<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\GuildHallBuilding;
use App\Service\Catalog\GameCatalog;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Barème des opérations du Hall, indépendant des bâtiments personnels. */
#[Exclude]
final readonly class GuildHallBuildingSchedule
{
    /**
     * Les devis et capacités sont indexés par niveau obtenu (1 à N).
     * Le barème est fourni par le catalogue serveur, jamais par un formulaire.
     *
     * @param array<string, array<int, array{cost:array<string,int>,duration:int}>> $levels
     * @param array<int, array<string,int>> $capacities
     */
    public function __construct(private array $levels, private array $capacities)
    {
        foreach ($levels as $slug => $rows) {
            if (!in_array($slug, [GuildHallBuilding::WAREHOUSE, GuildHallBuilding::DOCK], true)
                || $rows === [] || array_keys($rows) !== range(1, count($rows))) {
                throw new \DomainException('Les niveaux du barème du Hall sont invalides.');
            }

            foreach ($rows as $row) {
                if ($row['duration'] < 1 || $row['duration'] > 2147483647) {
                    throw new \DomainException('La durée du chantier du Hall est invalide.');
                }
                $this->assertMaterials($row['cost']);
            }
        }
        $warehouses = $levels[GuildHallBuilding::WAREHOUSE] ?? [];
        if ($warehouses === [] || array_keys($warehouses) !== array_keys($capacities)) {
            throw new \DomainException('La capacité de chaque niveau d’entrepôt doit être renseignée.');
        }

        // Chaque chantier doit être finançable depuis le niveau précédent ; aucune
        // surcapacité implicite, y compris pour le Dock après le premier entrepôt.
        foreach ($capacities as $level => $capacity) {
            $this->assertMaterials($capacity);
            $prior = $level === 1 ? $warehouses[1]['cost'] : $capacities[$level - 1];
            $this->assertFits($warehouses[$level]['cost'], $prior);
            $this->assertFits($prior, $capacity);
        }
        foreach ($levels[GuildHallBuilding::DOCK] ?? [] as $row) {
            $this->assertFits($row['cost'], $capacities[count($capacities)]);
        }
    }

    public function maximumLevel(string $slug): int
    {
        return count($this->levels[$slug] ?? []);
    }

    /** @return array{cost:array<string,int>,duration:int,maxLevel:int} */
    public function quote(string $slug, int $currentLevel): array
    {
        $levels = $this->levels[$slug] ?? [];
        $quote = $currentLevel >= 0 ? ($levels[$currentLevel + 1] ?? null) : null;
        if ($quote === null) {
            throw new \DomainException('Il n’y a pas de niveau suivant pour ce chantier.');
        }

        return $quote + ['maxLevel' => count($levels)];
    }

    /** @return array<string,int> */
    public function capacity(int $warehouseLevel): array
    {
        if ($warehouseLevel < 0 || ($warehouseLevel > 0 && !isset($this->capacities[$warehouseLevel]))) {
            throw new \DomainException('Le niveau d’entrepôt ne figure pas au barème.');
        }

        $first = $this->levels[GuildHallBuilding::WAREHOUSE][1]['cost'];

        return GuildHallStockPolicy::capacity($warehouseLevel, $first, $this->capacities[$warehouseLevel] ?? []);
    }

    /** @param array<string,int> $materials */
    private function assertMaterials(array $materials): void
    {
        foreach ($materials as $kind => $amount) {
            if (!array_key_exists($kind, GameCatalog::MATERIAL_WEIGHTS)
                || !is_int($amount) || $amount < 0 || $amount > 2147483647) {
                throw new \DomainException('Le barème du Hall ne peut contenir que des matériaux valides.');
            }
        }
        if (array_sum($materials) <= 0) {
            throw new \DomainException('Le barème du Hall doit prévoir un montant positif.');
        }
    }

    /** @param array<string,int> $required @param array<string,int> $available */
    private function assertFits(array $required, array $available): void
    {
        foreach ($required as $kind => $amount) {
            if ($amount > ($available[$kind] ?? 0)) {
                throw new \DomainException('Le chantier ne peut pas tenir dans le stock du Hall.');
            }
        }
    }
}
