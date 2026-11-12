<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_name_unique', columns: ['universe_id','name'])]
#[ORM\UniqueConstraint(name: 'guild_tag_unique', columns: ['universe_id','tag'])]
class Guild
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Universe $universe;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $leader;
    #[ORM\Column(length: 60)]
    public string $name;
    #[ORM\Column(length: 5, nullable: true)]
    public ?string $tag = null;
    #[ORM\Column(nullable:true)]
    public ?\DateTimeImmutable $archivedAt=null;
}
