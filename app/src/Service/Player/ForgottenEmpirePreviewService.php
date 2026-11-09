<?php
namespace App\Service\Player;

use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;

/** Prévisualisation uniquement : ne transforme aucun empire et ne modifie aucune activité. */
final class ForgottenEmpirePreviewService
{
    public function __construct(private GameRepository $repo,private EmpireActivityService $activity,private ClockInterface $clock) {}

    public function preview(int $limit=100,int $afterId=0): array
    {
        if($limit<1 || $limit>500) throw new \InvalidArgumentException('La limite doit être comprise entre 1 et 500.');
        if($afterId<0) throw new \InvalidArgumentException('Le curseur doit être un identifiant positif ou zéro.');
        $now=$this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $memberships=$this->repo->forgottenEmpirePreviewCandidates($now,EmpireActivityService::CONVERSION_AFTER_SECONDS,$limit,$afterId,EmpireConversionSchedule::latestAt($now));
        $empires=[];
        foreach($memberships as $membership) {
            $activity=$this->activity->snapshot($membership,$now);
            if(!$activity['conversion_due']) continue;
            $inventory=$this->repo->forgottenEmpirePreviewInventory($membership);
            $attention=[];
            foreach(['open_combats','deployed_fleets','incoming_or_stationed_fleets','queued_orders','alliances','guild_memberships','led_guilds'] as $key) {
                if($inventory[$key]>0) $attention[]=$key;
            }
            if($membership->player->emailVerifiedAt===null) $attention[]='unverified_account';
            if($membership->player->lockedAt!==null) $attention[]='locked_account';
            $empires[]=['membership_id'=>$membership->id,'universe'=>$membership->universe->slug,
                'due_at'=>$activity['conversion_due_at'],'inventory'=>$inventory,'attention'=>$attention];
        }
        return ['preview'=>true,'observed_at'=>$now->format(DATE_ATOM),'empires'=>$empires,
            'last_scanned_id'=>$memberships===[]?$afterId:$memberships[array_key_last($memberships)]->id,
            'batch_full'=>count($memberships)===$limit];
    }
}
