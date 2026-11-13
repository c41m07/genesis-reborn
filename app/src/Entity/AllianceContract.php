<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'alliance_pair_unique', columns: ['player_a_id','player_b_id'])]
class AllianceContract
{
    public const HOSTILE_STATUSES = ['enemies', 'peace_pending'];
    public const ACTIVE_STATUSES = ['pending', 'accepted', 'enemies', 'peace_pending'];
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $playerA;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $playerB;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $proposer;
    #[ORM\Column(length: 20)]
    public string $status = 'pending';
}
