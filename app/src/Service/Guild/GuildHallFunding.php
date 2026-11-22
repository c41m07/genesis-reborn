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

    /** @return array{ordinary: int, qualifiesFrom: ?int, maximum: int} */
    public function donationLimits(int $remaining, int $personal, int $qualified, int $requiredDonors, int $minimumEach): array
    {
        $missing = max(0, $requiredDonors - $qualified);
        $ordinary = max(0, $remaining - $missing * $minimumEach);

        // Réserver une part entière tant que la qualification n'est pas acquise.
        // Ainsi le départ d'un donateur partiel ne retire jamais la possibilité
        // qu'une autre personne finance sa propre part, sans dépasser le prix.
        if ($missing > 0 && $personal < $minimumEach) {
            $from = $minimumEach - $personal;
            $qualifyingMaximum = $remaining - ($missing - 1) * $minimumEach;
            if ($qualifyingMaximum >= $from) {
                return ['ordinary' => $ordinary, 'qualifiesFrom' => $from, 'maximum' => $qualifyingMaximum];
            }
        }

        return ['ordinary' => $ordinary, 'qualifiesFrom' => null, 'maximum' => $ordinary];
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

    /**
     * @param array<string, mixed> $input
     * @param array{ordinary: int, qualifiesFrom: ?int, maximum: int} $limits
     * @return array{cost: array<string, int>, strength: int}
     */
    public function donation(array $input, array $limits): array
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
        if ($strength > $limits['maximum']) {
            throw new \DomainException(sprintf('Ce don dépasse la force autorisée (%s points). La place requise pour les autres donateurs est réservée. Aucun stock n’est débité.', number_format($limits['maximum'], 0, ',', ' ')));
        }
        if ($strength > $limits['ordinary'] && $limits['qualifiesFrom'] !== null && $strength < $limits['qualifiesFrom']) {
            throw new \DomainException(sprintf('Donnez au moins %s points pour atteindre votre minimum personnel, ou restez dans la limite libre de %s points. Aucun stock n’est débité.', number_format($limits['qualifiesFrom'], 0, ',', ' '), number_format($limits['ordinary'], 0, ',', ' ')));
        }

        return ['cost' => $cost, 'strength' => $strength];
    }
}
