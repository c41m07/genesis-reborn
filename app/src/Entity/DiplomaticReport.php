<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'diplomatic_report_recipient_date_idx', columns: ['recipient_id', 'created_at'])]
#[ORM\Index(name: 'diplomatic_report_counterpart_idx', columns: ['counterpart_id'])]
class DiplomaticReport
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public readonly PlayerUniverse $recipient,
        #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
        public readonly ?PlayerUniverse $counterpart,
        #[ORM\Column(length: 24)]
        public readonly string $subject,
        #[ORM\Column(length: 12)]
        public readonly string $outcome,
        #[ORM\Column(length: 80, nullable: true)]
        public readonly ?string $location,
        #[ORM\Column(length: 60, nullable: true)]
        public readonly ?string $guildName,
        #[ORM\Column]
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public function getSubjectLabel(): string
    {
        return match ($this->subject) {
            'orbit' => 'Autorisation d’orbite',
            'personal_alliance' => 'Alliance personnelle',
            'personal_peace' => 'Paix personnelle',
            'guild_alliance' => 'Alliance de guildes',
            'guild_non_aggression' => 'Pacte de non-agression',
            'guild_defense' => 'Pacte de défense',
            'guild_peace' => 'Paix entre guildes',
        };
    }

    public function getOutcomeLabel(): string
    {
        return match ($this->outcome) {
            'requested' => 'Demande reçue',
            'accepted' => 'Demande acceptée',
            'refused' => 'Demande refusée',
        };
    }
}
