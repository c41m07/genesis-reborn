<?php
namespace App\Message;

final readonly class CombatEventDue
{
    public function __construct(public int $combatEventId, public string $dispatchToken) {}
}
