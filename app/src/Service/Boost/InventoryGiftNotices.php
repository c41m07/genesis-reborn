<?php

declare(strict_types=1);

namespace App\Service\Boost;

use App\Entity\PlayerUniverse;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class InventoryGiftNotices
{
    /** @param iterable<InventoryGiftNoticeSource> $sources */
    public function __construct(
        #[AutowireIterator('app.inventory_gift_notice_source')]
        private iterable $sources,
    ) {
    }

    /** @return array{count: int, through: array<string, int>} */
    public function summary(PlayerUniverse $membership): array
    {
        $count = 0;
        $through = [];
        foreach ($this->sources as $source) {
            $snapshot = $source->summary($membership);
            $count += $snapshot['count'];
            if ($snapshot['through'] !== null) {
                $through[$source->key()] = $snapshot['through'];
            }
        }

        return compact('count', 'through');
    }

    /** @param array<string, int> $through Limites authentifiées tirées du rendu initial. */
    public function acknowledge(PlayerUniverse $membership, array $through): void
    {
        foreach ($this->sources as $source) {
            // Une édition ouverte après l'affichage ne doit pas faire disparaître un nouveau cadeau.
            $last = $through[$source->key()] ?? null;
            if (is_int($last) && $last > 0) {
                $source->acknowledge($membership, $last);
            }
        }
    }
}
