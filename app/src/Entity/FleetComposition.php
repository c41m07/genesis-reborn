<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'IDX_COMPOSITION_OWNER', columns: ['owner_id'])]
#[ORM\UniqueConstraint(name: 'composition_request_unique', columns: ['owner_id', 'request_key'])]
class FleetComposition
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $owner;
    #[ORM\Column(length: 80)]
    public string $name;
    #[ORM\Column(type: 'json')]
    public array $ships = [];
    #[ORM\Column(length: 32)]
    public string $requestKey;
    #[ORM\Version, ORM\Column(type: 'integer')]
    public int $version = 1;
}
