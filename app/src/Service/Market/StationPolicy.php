<?php
declare(strict_types=1);
namespace App\Service\Market;

use App\Entity\Universe;

final class StationPolicy
{
    public const DEFAULTS = ['fee'=>2,'days'=>7,'capacity'=>0,'merchant'=>50];
    public const MAX = ['fee'=>100,'days'=>365,'capacity'=>1000000000,'merchant'=>100];
    public const LABELS = ['fee'=>'Taxe à la mise en vente (%)','days'=>'Durée des annonces (jours)','capacity'=>'Capacité du dépôt par empire (0 = illimitée)','merchant'=>'Part des ventes réapprovisionnée le vendredi (%)'];

    public static function read(Universe $universe): array
    {
        return ($universe->gameSettings['station_policy'] ?? []) + self::DEFAULTS;
    }

    public static function validate(array $input): array
    {
        $settings = [];
        foreach (self::DEFAULTS as $key=>$default) {
            $value = filter_var($input[$key] ?? null,FILTER_VALIDATE_INT);
            if ($value === false || $value < ($key==='days' ? 1 : 0) || $value>self::MAX[$key]) throw new \DomainException(self::LABELS[$key].' : valeur hors limites.');
            $settings[$key] = $value;
        }
        return $settings;
    }
}
