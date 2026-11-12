<?php
namespace App\Service\Fleet;

use App\Entity\Fleet;

/** État privé de la demande ; mutations sous les verrous de la flotte et de l’univers. */
final class OrbitRequestState
{
    public function read(Fleet $fleet): ?array
    {
        $state=$fleet->navigation['orbit_request']??null;
        return is_array($state) && ($state['version']??null)===1 ? $state : null;
    }

    public function open(Fleet $fleet,bool $automatic): void
    {
        if($fleet->mission!=='orbit' || $fleet->status!=='traveling' || !$fleet->destination?->owner
            || !$fleet->departureAt || !$fleet->arrivalAt || $fleet->arrivalAt<=$fleet->departureAt
            || $fleet->navigation===null || $this->read($fleet)!==null) {
            throw new \DomainException('La demande d’orbite ne peut pas être créée.');
        }
        $fleet->navigation['orbit_request']=[
            'version'=>1,'key'=>bin2hex(random_bytes(16)),
            'recipient_id'=>$fleet->destination->owner->id,'planet_id'=>$fleet->destination->id,
            'created_at'=>$fleet->departureAt->format(DATE_ATOM),
            'expires_at'=>$fleet->arrivalAt->format(DATE_ATOM),
            'status'=>$automatic?'accepted_auto':'pending',
            'decision_at'=>$automatic?$fleet->departureAt->format(DATE_ATOM):null,
            'decision_by'=>null,
        ];
    }

    public function reschedule(Fleet $fleet): void
    {
        $state=$this->read($fleet);
        if($state===null || $fleet->status!=='traveling' || !$fleet->arrivalAt || !$this->active($state)) return;
        $state['expires_at']=$fleet->arrivalAt->format(DATE_ATOM);
        $fleet->navigation['orbit_request']=$state;
    }

    public function assertDecision(Fleet $fleet,int $recipientId,string $key,\DateTimeImmutable $at): void
    {
        $state=$this->read($fleet);
        if($state===null || ($state['status']??null)!=='pending' || $fleet->status!=='traveling'
            || $fleet->mission!=='orbit' || !$fleet->arrivalAt || $fleet->arrivalAt<=$at
            || ($state['recipient_id']??null)!==$recipientId || $fleet->destination?->owner?->id!==$recipientId
            || ($state['planet_id']??null)!==$fleet->destination?->id
            || !is_string($state['key']??null) || !hash_equals($state['key'],$key)) {
            throw new \DomainException('Cette demande d’orbite a expiré ou n’est plus accessible.');
        }
    }

    public function decide(Fleet $fleet,int $recipientId,string $key,bool $accept,\DateTimeImmutable $at): void
    {
        $this->assertDecision($fleet,$recipientId,$key,$at);
        $state=$this->read($fleet);
        $state['status']=$accept?'accepted_manual':'refused';
        $state['decision_at']=$at->format(DATE_ATOM);$state['decision_by']=$recipientId;
        $fleet->navigation['orbit_request']=$state;
    }

    public function close(Fleet $fleet,string $status,\DateTimeImmutable $at): void
    {
        if(!in_array($status,['withdrawn','expired','invalid','arrived'],true)) throw new \InvalidArgumentException('État de clôture inconnu.');
        $state=$this->read($fleet);
        if($state===null || !$this->active($state)) return;
        $state['status']=$status;$state['closed_at']=$at->format(DATE_ATOM);
        $fleet->navigation['orbit_request']=$state;
    }

    private function active(array $state): bool
    {
        return in_array($state['status']??null,['pending','accepted_auto','accepted_manual'],true);
    }
}
