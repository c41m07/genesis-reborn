<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'admin_audit_created_idx', columns: ['created_at'])]
class AdminAuditLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Player $actor = null;
    #[ORM\Column(length: 80)]
    public string $action;
    #[ORM\Column(length: 80)]
    public string $targetType;
    #[ORM\Column]
    public int $targetId;
    #[ORM\Column(type: 'json')]
    public array $details = [];
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
}
