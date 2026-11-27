<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Boost\InventoryGiftNotices;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class InventoryGiftNoticeExtension extends AbstractExtension
{
    public function __construct(private readonly InventoryGiftNotices $notices)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('inventory_gift_notices', [$this->notices, 'summary'])];
    }
}
