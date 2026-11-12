<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'report_archive_unique', columns: ['player_universe_id', 'kind', 'report_id'])]
class ReportArchive
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $playerUniverse;
    #[ORM\Column(length: 16)]
    public string $kind;
    #[ORM\Column]
    public int $reportId;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $archivedAt = null;
}
