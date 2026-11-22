<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]

class Universe
{
    public const PIRATE_CAMPAIGN_FEATURE = 'pirate_campaign_enabled';

    /** L’option est active pour les univers existants et nouveaux, sauf désactivation explicite. */
    public function pirateCampaignEnabled(): bool
    {
        $features = $this->gameSettings['features'] ?? [];

        return is_array($features)
            && (!array_key_exists(self::PIRATE_CAMPAIGN_FEATURE, $features) || $features[self::PIRATE_CAMPAIGN_FEATURE] === true);
    }

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(length: 64, unique: true)]
    public string $slug;
    #[ORM\Column(length: 100)]
    public string $name;
    #[ORM\Column(length: 16)]
    public string $status = 'active';
    #[ORM\Column(type: 'json')]
    public array $gameSettings = [];
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $startsAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $endsAt = null;
    public function isAccessible(\DateTimeImmutable $now): bool
    {
        return $this->status === 'active' && (!$this->startsAt || $now >= $this->startsAt) && (!$this->endsAt || $now < $this->endsAt);
    }
}
