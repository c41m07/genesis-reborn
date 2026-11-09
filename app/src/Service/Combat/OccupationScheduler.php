<?php
namespace App\Service\Combat;

use App\Entity\{OutboxMessage,PlanetOccupation};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OccupationScheduler
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private ClockInterface $clock,
        #[Autowire('%env(bool:FORGOTTEN_EMPIRES_ENABLED)%')] private bool $enabled) {}

    public function isEnabled(): bool { return $this->enabled; }

    /** L'appelant détient le verrou d'occupation ; l'envoi reste dans sa transaction. */
    public function scheduleLocked(PlanetOccupation $occupation): string
    {
        if(!$this->enabled) return 'disabled';
        if(!$occupation->reservesSlot()) return 'inactive';
        if(!$occupation->occupant->universe->isAccessible($this->clock->now())) return 'universe_closed';
        if($this->pending($occupation)!==null) return 'already_queued';
        $outbox=new OutboxMessage();$outbox->kind='occupation';$outbox->targetId=$occupation->id;
        $outbox->token=$occupation->dispatchToken;$outbox->dueAt=max($this->clock->now(),$occupation->captureAt);
        $this->manager->save($outbox);return 'scheduled';
    }

    public function enqueue(int $id,string $token): string
    {
        if(!$this->enabled) return 'disabled';
        return $this->manager->transaction(function()use($id,$token): string {
            $occupation=$this->repo->find(PlanetOccupation::class,$id);
            if($occupation===null) return 'not_found';
            $this->manager->lock($occupation);
            if(!hash_equals($occupation->dispatchToken,$token)) return 'stale';
            return $this->scheduleLocked($occupation);
        });
    }

    public function resume(bool $apply=false,int $limit=100,int $afterId=0): array
    {
        if($limit<1 || $limit>500 || $afterId<0) throw new \InvalidArgumentException('Limite attendue : 1 à 500 ; curseur positif ou zéro.');
        if($apply && !$this->enabled) throw new \DomainException('Les occupations sont désactivées ; aucune reprise appliquée.');
        $occupations=$this->repo->occupationResumeCandidates($limit,$afterId);$rows=[];
        foreach($occupations as $occupation) {
            $accessible=$occupation->occupant->universe->isAccessible($this->clock->now());
            $status=$accessible?($this->pending($occupation)!==null?'already_queued':($this->clock->now()<$occupation->captureAt?'future':'overdue')):'universe_closed';
            if($apply) $status=$this->enqueue($occupation->id,$occupation->dispatchToken);
            $rows[]=['id'=>$occupation->id,'universe'=>$occupation->occupant->universe->slug,'capture_at'=>$occupation->captureAt->format(DATE_ATOM),'status'=>$status];
        }
        return ['preview'=>!$apply,'occupations'=>$rows,'batch_full'=>count($occupations)===$limit,
            'last_scanned_id'=>$occupations===[]?$afterId:$occupations[array_key_last($occupations)]->id];
    }

    private function pending(PlanetOccupation $occupation): ?OutboxMessage
    {
        return $this->repo->one(OutboxMessage::class,['kind'=>'occupation','targetId'=>$occupation->id,'token'=>$occupation->dispatchToken]);
    }
}
