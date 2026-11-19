<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,PlayerTechnology,PlayerUniverse,Universe};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

final class AttackDetection
{
    public function __construct(private GameRepository $repo,private GameCatalog $catalog) {}

    public static function warningSeconds(int $level, int $travelSeconds): int
    {
        $travelSeconds = max(0, $travelSeconds);
        // Calculer les trois quarts en secondes entières, même pour le trajet
        // maximal utilisé pour afficher le préavis théorique dans le catalogue.
        $travelCap = intdiv($travelSeconds, 4) * 3 + intdiv(($travelSeconds % 4) * 3, 4);
        $secondsPerLevel = 300;

        // Au-delà de ce niveau, le préavis atteint déjà le plafond du trajet.
        $usefulLevel = min(max(0, $level), intdiv($travelCap, $secondsPerLevel));

        return min(1800 + $secondsPerLevel * $usefulLevel, $travelCap);
    }

    public function level(PlayerUniverse $member): int
    {
        $levels=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$member])['tactical_intelligence']??[];
        return array_sum(array_map(static fn($tier)=>max(0,(int)($levels[$tier]??0)),GameCatalog::TIERS));
    }

    public function maxLevels(Universe $universe): array
    {
        $definition=$this->catalog->getForUniverse($universe,'technologies','tactical_intelligence');$caps=[];
        foreach(GameCatalog::TIERS as $tier) $caps[$tier]=max(0,(int)($definition['effect']['tier_max_levels'][$tier]??$definition['max_level']??PHP_INT_MAX));
        return $caps;
    }

    public function detected(Fleet $fleet,\DateTimeImmutable $now,int $level): bool
    {
        if($fleet->status!=='traveling' || !in_array($fleet->mission,['attack','conquer'],true) || !$fleet->departureAt || !$fleet->arrivalAt
            || $fleet->departureAt>$now || $fleet->arrivalAt<=$fleet->departureAt) return false;
        $seconds=self::warningSeconds($level,$fleet->arrivalAt->getTimestamp()-$fleet->departureAt->getTimestamp());
        return $now >= $fleet->arrivalAt->modify('-'.$seconds.' seconds');
    }
}
