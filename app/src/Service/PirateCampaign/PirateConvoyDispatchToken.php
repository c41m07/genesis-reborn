<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use Symfony\Component\Uid\Uuid;

/** UUID privé d'une longueur compatible avec l'outbox, lié à une génération monotone du droit. */
final class PirateConvoyDispatchToken
{
    public static function generate(int $generation): string
    {
        return self::prefix($generation).substr(Uuid::v4()->toRfc4122(), 8);
    }

    public static function matches(string $token, int $generation): bool
    {
        return Uuid::isValid($token) && str_starts_with($token, self::prefix($generation).'-');
    }

    private static function prefix(int $generation): string
    {
        if ($generation < 1 || $generation > 2147483647) {
            throw new \DomainException('Génération de transport invalide.');
        }

        return sprintf('%08x', $generation);
    }
}
