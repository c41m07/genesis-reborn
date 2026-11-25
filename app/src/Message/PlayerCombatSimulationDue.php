<?php

namespace App\Message;

final readonly class PlayerCombatSimulationDue
{
    public const KIND = 'combat_sim_trial';
    public const EXPIRY = 'combat_sim_expiration';

    public function __construct(public int $id, public string $token, public bool $expire = false)
    {
    }
}
