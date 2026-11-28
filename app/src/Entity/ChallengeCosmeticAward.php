<?php
declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ChallengeCosmeticAward
{
    #[ORM\Id, ORM\ManyToOne, ORM\JoinColumn(nullable:false,onDelete:'CASCADE')]
    public BoostEvent $event;

    #[ORM\Id, ORM\ManyToOne, ORM\JoinColumn(nullable:false,onDelete:'CASCADE')]
    public PlayerUniverse $membership;

    public string $kind = 'cosmetics';
    public string $status = 'awarded';
}
