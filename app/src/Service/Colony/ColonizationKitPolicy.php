<?php
namespace App\Service\Colony;

use App\Entity\{AdminAuditLog,Planet,PlayerUniverse};
use App\Repository\GameRepository;

final class ColonizationKitPolicy
{
    public function __construct(private GameRepository $repo) {}

    public function multiplier(PlayerUniverse $member,Planet $planet): float
    {
        if(!$member->isPlayerControlled() || $planet->solarSystem->galaxy->universe->id!==$member->universe->id) throw new \DomainException('Colonisation inaccessible dans cet univers.');
        return $this->repo->one(AdminAuditLog::class,[
            'action'=>'player_colony_abandoned','targetType'=>'planet','targetId'=>$planet->id,'actor'=>$member->player,
        ])===null?1.0:0.0;
    }
}
