<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'guild_hall_convoy')]
final class GuildHallConvoy
{
    public const MISSION = 'guild_hall_convoy';

    #[ORM\Id, ORM\OneToOne]
    #[ORM\JoinColumn(name: 'fleet_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public Fleet $fleet;

    // IDs figés : la dissolution ne supprime pas la preuve ni la soute personnelle.
    #[ORM\Column]
    public int $hallId;

    #[ORM\Column]
    public int $guildId;

    #[ORM\Column(length: 36)]
    public string $outboundToken = '';

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $settledAt = null;

    #[ORM\Column(type: 'json')]
    public array $delivered = [];

    #[ORM\Column(options: ['default' => false])]
    public bool $shipDonation = false;

    /** @var array<string,array<string,int>>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $deliveredShips = null;

    public function scheduleOutbound(string $previousToken, string $token): void
    {
        if ($token === '' || $this->settledAt !== null || !hash_equals($this->outboundToken, $previousToken)) {
            throw new \LogicException('Impossible de reprogrammer ce convoi de guilde.');
        }
        $this->outboundToken = $token;
    }

    public function canSettle(string $token): bool
    {
        return $token !== '' && $this->settledAt === null && hash_equals($this->outboundToken, $token);
    }

    /** @param array<string,int> $delivered */
    public function settle(string $token, \DateTimeImmutable $now, array $delivered): bool
    {
        if (!$this->canSettle($token)) {
            return false;
        }
        $this->settledAt = $now->setTimezone(new \DateTimeZone('UTC'));
        $this->delivered = $delivered;

        return true;
    }
}
