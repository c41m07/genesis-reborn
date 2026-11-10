<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AdminCaseNote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne(targetEntity: AdminCase::class), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public AdminCase $caseFile;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Player $author = null;
    #[ORM\Column(type: 'text')]
    public string $body;
    #[ORM\Column(length: 32, unique: true)]
    public string $requestKey;
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
}
