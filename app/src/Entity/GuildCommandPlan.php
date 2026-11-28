<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** La cible et la guilde adverse ne peuvent pas être remplacées après l’accord des propriétaires. */
#[ORM\Entity]
#[ORM\Index(name: 'guild_command_plan_hall', columns: ['hall_id', 'closed_at'])]
final class GuildCommandPlan
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $launchedAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $closedAt = null;

    public function __construct(
        #[ORM\Column] public readonly int $universeId,
        #[ORM\Column] public readonly int $hallId,
        #[ORM\Column] public readonly int $guildId,
        #[ORM\Column] public readonly int $enemyGuildId,
        #[ORM\Column] public readonly int $targetColonyId,
        #[ORM\Column] public readonly int $targetOwnerId,
        #[ORM\Column] public readonly int $createdById,
        #[ORM\Column] public readonly \DateTimeImmutable $createdAt,
    ) {
        if (min($universeId, $hallId, $guildId, $enemyGuildId, $targetColonyId, $targetOwnerId, $createdById) < 1
            || $guildId === $enemyGuildId) {
            throw new \InvalidArgumentException('Identité du plan du Centre invalide.');
        }
    }

    public function acceptsConsent(): bool
    {
        return $this->launchedAt === null && $this->closedAt === null;
    }
}
