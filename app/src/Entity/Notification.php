<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]

class Notification
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $recipient;
    #[ORM\Column(length: 240)]
    public string $message;
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
}
