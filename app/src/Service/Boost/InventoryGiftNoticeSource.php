<?php

declare(strict_types=1);

namespace App\Service\Boost;

use App\Entity\PlayerUniverse;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.inventory_gift_notice_source')]
interface InventoryGiftNoticeSource
{
    public function key(): string;

    /** @return array{count: int, through: ?int} Nombre et dernier identifiant visible au rendu. */
    public function summary(PlayerUniverse $membership): array;

    public function acknowledge(PlayerUniverse $membership, int $through): void;
}
