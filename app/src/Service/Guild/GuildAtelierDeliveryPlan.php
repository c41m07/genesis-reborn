<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\Planet;

/** Le devis n’emporte aucune réservation, même quand il est validé côté serveur. */
final readonly class GuildAtelierDeliveryPlan
{
    /**
     * @param array<string,array<string,int>> $composition
     * @param array<string,mixed> $navigation
     */
    public function __construct(
        public Planet $destination,
        public array $composition,
        public int $seconds,
        public float $fuel,
        public float $speedRatio,
        public array $navigation,
        public \DateTimeImmutable $preparedAt,
    ) {
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->destination->id, $this->destination->owner?->id,
            $this->composition, $this->seconds, $this->fuel, $this->speedRatio,
            $this->navigation['origin'], $this->navigation['target'],
        ], JSON_THROW_ON_ERROR));
    }
}
