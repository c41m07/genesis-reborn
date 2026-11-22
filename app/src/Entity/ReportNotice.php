<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'report_notice_combat_unique', columns: ['player_universe_id', 'combat_log_id'])]
#[ORM\UniqueConstraint(name: 'report_notice_espionage_unique', columns: ['player_universe_id', 'espionage_report_id'])]
#[ORM\UniqueConstraint(name: 'report_notice_transport_unique', columns: ['player_universe_id', 'transport_report_id'])]
#[ORM\Index(name: 'report_notice_unread_idx', columns: ['player_universe_id', 'consulted_at'])]
#[ORM\Index(name: 'report_notice_combat_idx', columns: ['combat_log_id'])]
#[ORM\Index(name: 'report_notice_espionage_idx', columns: ['espionage_report_id'])]
#[ORM\Index(name: 'report_notice_transport_idx', columns: ['transport_report_id'])]
class ReportNotice
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PlayerUniverse $playerUniverse;

    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?CombatLog $combatLog = null;

    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?EspionageReport $espionageReport = null;

    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?TransportReport $transportReport = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consultedAt = null;

    public function __construct(PlayerUniverse $playerUniverse, CombatLog|EspionageReport|TransportReport $report)
    {
        $this->playerUniverse = $playerUniverse;
        // Le type assure une seule référence. Doctrine peut l’écrire au même flush que le rapport.
        match (true) {
            $report instanceof CombatLog => $this->combatLog = $report,
            $report instanceof EspionageReport => $this->espionageReport = $report,
            $report instanceof TransportReport => $this->transportReport = $report,
        };
    }
}
