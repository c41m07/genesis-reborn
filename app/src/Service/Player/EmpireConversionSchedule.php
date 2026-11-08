<?php
namespace App\Service\Player;

/** Calendrier civil parisien ; les échéances persistées restent en UTC. */
final class EmpireConversionSchedule
{
    public const TIMEZONE='Europe/Paris';

    public static function atOrAfter(\DateTimeImmutable $threshold): \DateTimeImmutable
    {
        $local=$threshold->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $midnight=$local->setTime(0,0);
        if($local>$midnight) $midnight=$midnight->modify('+1 day');
        return $midnight->setTimezone(new \DateTimeZone('UTC'));
    }

    public static function latestAt(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->setTimezone(new \DateTimeZone(self::TIMEZONE))->setTime(0,0)->setTimezone(new \DateTimeZone('UTC'));
    }
}
