<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** État privé coté worker : débute seulement après le résultat et ne vient pas du client. */
#[Exclude]
final readonly class PirateFundedReturn
{
    public const TRAVELING = 'traveling';
    public const ORIGIN_UNAVAILABLE = 'origin_unavailable';
    public const INSUFFICIENT_HYDROGEN = 'insufficient_hydrogen';

    private function __construct(
        public string $decision,
        public float $hydrogen,
        public ?\DateTimeImmutable $dueAt,
    ) {
    }

    public static function atResolution(
        PirateAttackFuelReceipt $receipt,
        array $survivors,
        \DateTimeImmutable $observedAt,
        bool $originAvailable,
    ): self {
        if ($survivors === []) {
            throw new \LogicException('Une expédition sans survivant ne commence pas un retour.');
        }

        $fuel = $receipt->budget;
        $carried = $fuel->afterCombat($survivors, $receipt->survivorPorters($survivors));
        if (!$originAvailable) {
            return new self(self::ORIGIN_UNAVAILABLE, $carried, null);
        }
        if ($fuel->arrivalHydrogen($carried) === null) {
            return new self(self::INSUFFICIENT_HYDROGEN, $carried, null);
        }

        return new self(self::TRAVELING, $carried, $observedAt->setTimestamp($observedAt->getTimestamp() + $fuel->legSeconds));
    }

    public function checkpoint(int $attempt, \DateTimeImmutable $observedAt): array
    {
        return [
            'version' => 1, 'attempt' => $attempt, 'state' => $this->decision,
            'observed_at' => $observedAt->format(DATE_ATOM), 'due_at' => $this->dueAt?->format(DATE_ATOM),
            'carried' => $this->hydrogen,
        ];
    }
}
