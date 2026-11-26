<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Première date à laquelle un journal peut couvrir les opérations d'une source. */
#[ORM\Entity]
final class ExpenseCollection
{
    #[ORM\Id]
    #[ORM\Column(length: 16)]
    public string $source;

    #[ORM\Column]
    public \DateTimeImmutable $availableSince;
}
