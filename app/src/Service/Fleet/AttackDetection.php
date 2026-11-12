<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,PlayerTechnology,PlayerUniverse,Universe};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

final class AttackDetection
{
    public function __construct(private GameRepository $repo,private GameCatalog $catalog) {}

    public static function warningSeconds(int $level,int $travelSeconds): int
    {
        // Dès 23 niveaux cumulés, le plafond de deux heures est atteint ; ce n’est pas un plafond de recherche.
        return min(300+300*max(0,min(23,$level)),7200,intdiv(max(0,$travelSeconds),2));
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
