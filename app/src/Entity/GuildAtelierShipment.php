<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Les vaisseaux du Hall voyagent sans appartenir à la flotte personnelle du gestionnaire. */
#[ORM\Entity]
#[ORM\Table(name: 'guild_atelier_shipment')]
#[ORM\Index(name: 'guild_atelier_shipment_history', columns: ['hall_id', 'guild_id', 'universe_id', 'id'])]
#[ORM\Index(name: 'guild_atelier_shipment_due', columns: ['status', 'arrival_at'])]
final class GuildAtelierShipment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    // Instantanés d’identité : ne jamais substituer un autre Hall ou un autre propriétaire.
    #[ORM\Column]
    public int $hallId;

    #[ORM\Column]
    public int $guildId;

    #[ORM\Column]
    public int $universeId;

    #[ORM\Column]
    public int $dispatchedById;

    #[ORM\Column]
    public int $recipientId;

    #[ORM\Column]
    public int $destinationId;

    /** @var array<string,array<string,int>> */
    #[ORM\Column(type: 'json')]
    public array $ships = [];

    /** @var array<string,mixed> */
    #[ORM\Column(type: 'json')]
    public array $navigation = [];

    #[ORM\Column]
    public float $reservedFuel;

    #[ORM\Column]
    public \DateTimeImmutable $departureAt;

    #[ORM\Column]
    public \DateTimeImmutable $arrivalAt;

    #[ORM\Column(length: 20)]
    public string $status = 'outbound';

    #[ORM\Column(length: 36)]
    public string $arrivalToken;

    public function isDue(string $token, \DateTimeImmutable $now): bool
    {
        return in_array($this->status, ['outbound', 'returning'], true)
            && $token !== '' && hash_equals($this->arrivalToken, $token)
            && $this->arrivalAt <= $now;
    }
}
