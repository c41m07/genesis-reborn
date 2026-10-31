<?php
namespace App\Message;

final readonly class UniverseClosureDue
{
    public function __construct(public int $universeId, public string $scheduleToken) {}
}
