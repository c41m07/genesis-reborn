<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'boost_event_source_unique', columns: ['event_id', 'source_key'])]
#[ORM\Index(name: 'boost_event_score_idx', columns: ['event_id', 'player_universe_id', 'id'])]
class BoostEventContribution
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BoostEvent $event;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $playerUniverse;

    #[ORM\Column(length: 120)]
    public string $sourceKey;

    #[ORM\Column]
    public float $value;
}
