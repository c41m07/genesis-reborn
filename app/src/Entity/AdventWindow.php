<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'edition_id', columns: ['edition_id'])]
#[ORM\Index(name: 'advent_window_lookup', columns: ['universe_id', 'visible_until', 'visible_from'])]
class AdventWindow
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public AdventEdition $edition;

    #[ORM\Column(length: 120)]
    public string $name;

    #[ORM\Column]
    public \DateTimeImmutable $visibleFrom;

    #[ORM\Column]
    public \DateTimeImmutable $visibleUntil;
}
