<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'treaty_pair_history', columns: ['universe_id','guild_a_id','guild_b_id','id'])]
class GuildTreatyEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column]
    public int $universeId;
    #[ORM\Column(name: 'guild_a_id')]
    public int $guildAId;
    #[ORM\Column(name: 'guild_b_id')]
    public int $guildBId;
    #[ORM\Column]
    public int $actorId;
    #[ORM\Column]
    public int $actorGuildId;
    #[ORM\Column(length: 24)]
    public string $action;
    #[ORM\Column(length: 20)]
    public string $previousStatus;
    #[ORM\Column(length: 20)]
    public string $status;
    #[ORM\Column(length: 20, nullable: true)]
    public ?string $proposal = null;
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $occurredAt;
}
