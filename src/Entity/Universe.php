<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]

class Universe
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(length: 64, unique: true)]
    public string $slug;
    #[ORM\Column(length: 100)]
    public string $name;
    #[ORM\Column(length: 16)]
    public string $status = 'active';
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $startsAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $endsAt = null;
    public function isAccessible(\DateTimeImmutable $now): bool
    {
        return $this->status === 'active' && (!$this->startsAt || $now >= $this->startsAt) && (!$this->endsAt || $now < $this->endsAt);
    }
}
