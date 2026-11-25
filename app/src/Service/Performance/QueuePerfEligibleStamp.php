<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Messenger\Stamp\StampInterface;

#[Exclude]
final readonly class QueuePerfEligibleStamp implements StampInterface
{
    public function __construct(public int $eligibleAtMs)
    {
    }
}
