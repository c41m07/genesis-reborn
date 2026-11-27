<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\PlayerUniverse;
use App\Service\Boost\InventoryGiftNoticeSource;

/** Expose au badge les boîtes de l’Avent, sans confondre consultation et livraison. */
final readonly class AdventGiftNoticeSource implements InventoryGiftNoticeSource
{
    public function __construct(private AdventRewards $rewards)
    {
    }

    public function key(): string
    {
        return 'advent';
    }

    public function summary(PlayerUniverse $membership): array
    {
        $summary = $this->rewards->giftNoticeSummary($membership);

        return $summary + ['boxes' => $summary['count'], 'pending' => 0];
    }

    public function acknowledge(PlayerUniverse $membership, int $through, bool $single = false): void
    {
        $this->rewards->acknowledgeGiftNotices($membership, $through, $single);
    }
}
