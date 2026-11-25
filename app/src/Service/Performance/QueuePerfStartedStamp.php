<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

#[Exclude]
final readonly class QueuePerfStartedStamp implements NonSendableStampInterface
{
    public function __construct(public int $started, public ?int $waitMs)
    {
    }
}
