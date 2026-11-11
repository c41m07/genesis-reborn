<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_relation_unique', columns: ['guild_a_id','guild_b_id'])]
class GuildRelation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Guild $guildA;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Guild $guildB;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Guild $proposer;
    #[ORM\Column(length: 20)]
    public string $status;
    #[ORM\Column(length: 20, nullable: true)]
    public ?string $proposal = null;
    #[ORM\Version, ORM\Column(type: 'integer')]
    public int $version = 1;
}
