<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name:'guild_profile_guild_unique',columns:['guild_id'])]
class GuildProfile
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column]
    public ?int $id=null;
    #[ORM\ManyToOne,ORM\JoinColumn(nullable:false,onDelete:'CASCADE')]
    public Guild $guild;
    #[ORM\Column(type:'text')]
    public string $description='';
    #[ORM\Column(length:64,nullable:true)]
    public ?string $logoFilename=null;
    #[ORM\Column(length:64,nullable:true)]
    public ?string $bannerFilename=null;
    #[ORM\Column]
    public int $version=1;
}
