<?php
namespace App\Service\Fleet;

use App\Entity\{Planet,PlayerUniverse};
use App\Service\Player\{RelationResolver,VacationCalendar};

final class OrbitPolicy
{
    public function __construct(private RelationResolver $relations,private VacationCalendar $vacations) {}

    public function canRequest(PlayerUniverse $sender,?Planet $target): bool
    {
        $host=$target?->owner;
        return $sender->isPlayerControlled() && $sender->player->lockedAt===null
            && $host!==null && $host->isPlayerControlled() && $host->player->lockedAt===null
            && $host->id!==$sender->id
            && $host->universe->id===$sender->universe->id
            && $target->solarSystem->galaxy->universe->id===$sender->universe->id
            && !$this->vacations->isActive($sender) && !$this->vacations->isActive($host)
            && !$this->relations->enemy($sender,$host);
    }

    public function automatic(PlayerUniverse $sender,?Planet $target): bool
    {
        return $this->canRequest($sender,$target) && $this->relations->allied($sender,$target->owner);
    }

    public function consentValid(PlayerUniverse $sender,?Planet $target,array $request): bool
    {
        if(!$this->canRequest($sender,$target) || ($request['recipient_id']??null)!==$target->owner->id
            || ($request['planet_id']??null)!==$target->id) return false;
        return ($request['status']??null)==='accepted_manual'
            || (in_array($request['status']??null,['pending','accepted_auto'],true) && $this->automatic($sender,$target));
    }
}
