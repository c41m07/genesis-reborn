<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Installation attachée à une colonie et à une adhésion précise, pas au compte entier. */
#[ORM\Entity]
#[ORM\Index(name: 'guild_jump_hall', columns: ['hall_id'])]
#[ORM\Index(name: 'guild_jump_member', columns: ['member_id'])]
#[ORM\UniqueConstraint(name: 'guild_jump_colony', columns: ['planet_id'])]
final class GuildJumpPoint
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public GuildHall $hall;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public GuildMember $member;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Planet $planet;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $acquisitionAt = null;

    #[ORM\Column]
    public \DateTimeImmutable $readyAt;

    public function isUsableAt(\DateTimeImmutable $now): bool
    {
        return $this->readyAt <= $now
            && $this->hall->placedAt !== null
            && $this->hall->solarSystem !== null
            && $this->hall->guild->archivedAt === null
            && $this->hall->guild->id === $this->member->guild->id
            && $this->planet->owner !== null
            && $this->planet->owner->id === $this->member->playerUniverse->id
            && $this->planet->solarSystem->galaxy->universe->id === $this->hall->guild->universe->id
            && !$this->planet->npcPlanet
            && $this->planet->acquiredAt?->getTimestamp() === $this->acquisitionAt?->getTimestamp();
    }
}
