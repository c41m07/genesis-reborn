<?php

declare(strict_types=1);

namespace App\Message;

final readonly class AdventCaptureDue
{
    public const KIND = 'advent_capture';

    public function __construct(public int $editionId, public bool $acceptLate) {}
}
