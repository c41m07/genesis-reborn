<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'admin_case_state_idx', columns: ['status', 'updated_at'])]
class AdminCase
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Universe $universe = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?PlayerUniverse $membership = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Fleet $fleet = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Player $createdBy = null;
    #[ORM\Column(length: 120)]
    public string $title;
    #[ORM\Column(type: 'text')]
    public string $description = '';
    #[ORM\Column(length: 20)]
    public string $status = 'open';
    #[ORM\Column(length: 120, unique: true, nullable: true)]
    public ?string $alertKey = null;
    #[ORM\Column(length: 32, unique: true)]
    public string $creationKey;
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
    #[ORM\Column]
    public \DateTimeImmutable $updatedAt;
}
