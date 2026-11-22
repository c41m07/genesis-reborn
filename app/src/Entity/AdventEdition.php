<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'advent_edition_season_unique', columns: ['season'])]
#[ORM\Index(name: 'advent_edition_schedule_idx', columns: ['closes_at', 'starts_at'])]
class AdventEdition
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public int $season;

    #[ORM\Column(length: 120)]
    public string $name;

    #[ORM\Column]
    public \DateTimeImmutable $startsAt;

    #[ORM\Column]
    public \DateTimeImmutable $closesAt;

    /** @var array<int, array{opens_at: string, options: list<array<string, int>>}> */
    #[ORM\Column(type: 'json')]
    public array $doors;

    #[ORM\Column]
    public \DateTimeImmutable $publishedAt;
}
