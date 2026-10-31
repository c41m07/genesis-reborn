<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
#[ORM\Entity]
class Player implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(length: 180, unique: true)]
    public string $email;
    #[ORM\Column(length: 40, unique: true)]
    public string $username;
    #[ORM\Column(length: 255)]
    public string $password;
    #[ORM\ManyToOne(targetEntity: PlayerUniverse::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?PlayerUniverse $currentPlayerUniverse = null;
    public function getUserIdentifier(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function getRoles(): array { return ['ROLE_USER']; }
    public function eraseCredentials(): void {}
}
