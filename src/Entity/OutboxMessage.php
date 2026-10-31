<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]

class OutboxMessage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(length: 30)]
    public string $kind;
    #[ORM\Column]
    public int $targetId;
    #[ORM\Column(length: 36)]
    public string $token;
    #[ORM\Column]
    public \DateTimeImmutable $dueAt;
}
