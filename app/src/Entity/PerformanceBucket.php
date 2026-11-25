<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'performance_bucket')]
#[ORM\Index(name: 'performance_scope_hour_idx', columns: ['scope', 'hour_utc'])]
final class PerformanceBucket
{
    #[ORM\Id, ORM\Column(name: 'hour_utc', type: 'datetime_immutable')]
    public \DateTimeImmutable $hour;

    #[ORM\Id, ORM\Column(length: 40)]
    public string $scope;

    #[ORM\Id, ORM\Column(length: 8)]
    public string $device;

    #[ORM\Id, ORM\Column(length: 32)]
    public string $metric;

    #[ORM\Id, ORM\Column(name: 'ceiling_ms')]
    public int $ceiling;

    #[ORM\Column(type: 'bigint')]
    public int $observations;
}
