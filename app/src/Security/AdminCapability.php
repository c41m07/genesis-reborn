<?php

namespace App\Security;

/** Capacités du premier périmètre raccordé ; ne sont pas des rôles attribuables. */
final class AdminCapability
{
    public const PLAYER_VIEW = 'ADMIN_CAPABILITY_PLAYER_VIEW';
    public const RESOURCES_ADJUST = 'ADMIN_CAPABILITY_RESOURCES_ADJUST';
    public const FLEET_INTERVENE = 'ADMIN_CAPABILITY_FLEET_INTERVENE';
    public const SHIPS_GRANT = 'ADMIN_CAPABILITY_SHIPS_GRANT';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PLAYER_VIEW, self::RESOURCES_ADJUST, self::SHIPS_GRANT, self::FLEET_INTERVENE];
    }
}
