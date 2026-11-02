<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'system_unique', columns: ['galaxy_id', 'coord_x', 'coord_y'])]
class SolarSystem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Galaxy $galaxy;
    #[ORM\Column(length: 80)]
    public string $name;
    #[ORM\Column]
    public int $coordX;
    #[ORM\Column]
    public int $coordY;
    public function number(): int
    {
        // Le générateur place les systèmes sur une grille de 10 colonnes espacées de 3 unités.
        return intdiv($this->coordY, 3) * 10 + intdiv($this->coordX, 3) + 1;
    }
}
