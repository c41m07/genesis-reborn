<?php

declare(strict_types=1);

namespace App\Service\Advent;

final class AdventGifts
{
    public const RESOURCES = ['metal' => 'Métal', 'nano' => 'Nano', 'hydrogen' => 'Hydrogène', 'antimatter' => 'Antimatière', 'electricity' => 'Électricité'];
    public const OPTIONS_PER_DOOR = 3;

    /** @return array<int, list<array<string, int>>> */
    public function validate(array $submitted): array
    {
        if (count($submitted) !== AdventCalendar::DOORS) {
            throw new \DomainException('Configurez les 24 portes dans le même aperçu.');
        }

        $doors = [];
        for ($door = 1; $door <= AdventCalendar::DOORS; ++$door) {
            $options = $submitted[$door] ?? null;
            if (!is_array($options) || !array_is_list($options) || count($options) > self::OPTIONS_PER_DOOR) {
                throw new \DomainException('Les choix de la porte '.$door.' sont invalides.');
            }
            foreach ($options as $option) {
                $amounts = $this->amounts($option, $door);
                if ($amounts === []) {
                    continue;
                }
                if (in_array($amounts, $doors[$door] ?? [], true)) {
                    throw new \DomainException('Deux choix de la porte '.$door.' sont identiques.');
                }
                $doors[$door][] = $amounts;
            }
            if (empty($doors[$door])) {
                throw new \DomainException('Configurez au moins un lot de ressources pour la porte '.$door.'.');
            }
        }

        return $doors;
    }

    /** @return array<string, int> */
    private function amounts(mixed $option, int $door): array
    {
        if (!is_array($option) || array_diff_key($option, self::RESOURCES) !== []) {
            throw new \DomainException('Ressource invalide à la porte '.$door.'.');
        }
        $amounts = [];
        foreach (self::RESOURCES as $resource => $name) {
            $amount = $option[$resource] ?? '';
            if ($amount === '') {
                continue;
            }
            // Borne technique, pas de barème imposé : aucun montant de jeu n’est prérempli.
            if ((!is_int($amount) && !is_string($amount)) || !preg_match('/\A(?:0|[1-9]\d{0,8})\z/', (string) $amount)) {
                throw new \DomainException('Quantité invalide ('.$name.', porte '.$door.').');
            }
            if ((int) $amount > 0) {
                $amounts[$resource] = (int) $amount;
            }
        }

        return $amounts;
    }
}
