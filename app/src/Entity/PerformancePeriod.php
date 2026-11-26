<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'performance_period')]
final class PerformancePeriod
{
    #[ORM\Id]
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $startsAt;
}
