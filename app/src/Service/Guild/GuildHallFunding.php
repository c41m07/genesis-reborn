<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\GuildProjectRules;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class GuildHallFunding
{
    // Une unité de force correspond à un hydrogène. L’électricité n’a pas
    // d’équivalence dans la grille des quatre ressources finançant le Hall.
    private const WEIGHTS = ['hydrogen' => 1, 'metal' => 2, 'nano' => 4, 'antimatter' => 8];

    public function __construct(
        private GuildProjectRules $rules,
        private GameCatalog $catalog,
        #[Autowire('%env(int:GUILD_HALL_REQUIRED_STRENGTH)%')] private int $requiredStrength,
        #[Autowire('%env(int:GUILD_HALL_REQUIRED_DONORS)%')] public int $requiredDonors,
        #[Autowire('%env(int:GUILD_HALL_MINIMUM_DONOR_STRENGTH)%')] public int $minimumDonorStrength,
        #[Autowire('%env(bool:GUILD_HALL_ENABLED)%')] private bool $enabled,
    ) {
    }

    public function target(): ?int
    {
        return $this->enabled && $this->requiredStrength > 0 && $this->requiredStrength <= GuildProjectRules::MAX_STAGE_AMOUNT
            && $this->requiredDonors > 0 && $this->requiredDonors <= 100
            && $this->minimumDonorStrength > 0
            && $this->minimumDonorStrength <= intdiv($this->requiredStrength, $this->requiredDonors)
                ? $this->requiredStrength : null;
    }

    /**
     * @param list<int> $otherStrengths Au plus le nombre de donateurs requis, par ordre décroissant.
     */
    public function maximumDonation(int $remaining, int $personal, array $otherStrengths, int $requiredDonors, int $minimumEach): int
    {
        $reserve = $requiredDonors * $minimumEach;
        foreach (array_slice($otherStrengths, 0, $requiredDonors) as $previous) {
            $reserve -= min($minimumEach, $previous);
        }
        $last = min($minimumEach, $otherStrengths[$requiredDonors - 1] ?? 0);
        // Un nouveau point ne réduit le déficit réservé qu'après entrée du
        // donateur parmi les mieux placés, et seulement jusqu'au seuil personnel.
        // La dépense + le futur déficit restent donc monotones : chercher le plafond.
        $low = 0;
        $high = max(0, $remaining);
        while ($low < $high) {
            $candidate = intdiv($low + $high + 1, 2);
            $coverage = max(0, min($minimumEach, $personal + $candidate) - $last);
            if ($candidate + $reserve - $coverage <= $remaining) {
                $low = $candidate;
            } else {
                $high = $candidate - 1;
            }
        }

        return $low;
    }

    /** @return array<string, array{label: string, maximum: int}> */
    public function fields(int $maximum): array
    {
        $fields = [];
        foreach (['hydrogen' => 'Hydrogène', 'metal' => 'Métal', 'nano' => 'Nano', 'antimatter' => 'Antimatière'] as $resource => $label) {
            $fields[$resource] = ['label' => $label, 'maximum' => intdiv($maximum, self::WEIGHTS[$resource])];
        }

        return $fields;
    }

    public function availableIn(Universe $universe): bool
    {
        return $this->target() !== null && (bool) $this->catalog->forUniverse($universe)['features']['guilds_enabled'];
    }

    /** @param array<string, mixed> $input @return array{cost: array<string, int>, strength: int} */
    public function donation(array $input, int $remaining): array
    {
        $cost = $this->rules->quantities(
            $input,
            ['hydrogen' => 'Hydrogène', 'metal' => 'Métal', 'nano' => 'Nano', 'antimatter' => 'Antimatière'],
            GuildProjectRules::MAX_STAGE_AMOUNT,
        );
        $strength = 0;
        foreach ($cost as $resource => $amount) {
            $strength += $amount * self::WEIGHTS[$resource];
        }
        if ($strength > $remaining) {
            throw new \DomainException(sprintf('Ce don dépasse la force autorisée (%s points). La place requise pour les autres donateurs est réservée. Aucun stock n’est débité.', number_format($remaining, 0, ',', ' ')));
        }

        return ['cost' => $cost, 'strength' => $strength];
    }
}
