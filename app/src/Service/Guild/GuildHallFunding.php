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
    ) {
    }

    public function target(): ?int
    {
        return $this->requiredStrength > 0 && $this->requiredStrength <= GuildProjectRules::MAX_STAGE_AMOUNT
            ? $this->requiredStrength : null;
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
            throw new \DomainException('Ce don dépasse la force de construction restante. Aucun stock n’est débité.');
        }

        return ['cost' => $cost, 'strength' => $strength];
    }
}
