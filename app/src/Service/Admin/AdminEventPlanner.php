<?php
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog, BoostEvent, GameEvent, Player, Universe};
use App\Manager\GameManager;
use App\Service\Boost\ResourceBoxPolicy;
use App\Service\Event\GameEventService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class AdminEventPlanner
{
    public const MODELS=['bonus'=>'Bonus temporaire de production','announcement'=>'Annonce','challenge'=>'Défi collectif'];
    public const OBJECTIVES=[
        'exploration'=>['label'=>'Découvrir de nouveaux systèmes','metric'=>'new_systems_discovered','help'=>'Les nouvelles découvertes effectuées pendant le défi sont comptées.'],
        'combat'=>['label'=>'Infliger des dégâts en combat','metric'=>'damage_dealt','help'=>'Les dégâts réellement enregistrés par les combats pendant le défi sont comptés.'],
        'transport'=>['label'=>'Livrer des ressources entre ses colonies','metric'=>'freight_delivered','help'=>'Les quantités effectivement livrées entre ses propres colonies sont comptées. Les dons entre joueurs sont exclus.'],
    ];
    public function __construct(private EntityManagerInterface $em,private GameManager $manager,private ResourceBoxPolicy $boxes,private GameEventService $events,private ClockInterface $clock) {}

    public function prepare(string $model,array $input): array
    {
        if (!isset(self::MODELS[$model])) throw new \DomainException('Modèle inconnu.');
        $name=trim((string)($input['name'] ?? ''));$description=trim((string)($input['description'] ?? ''));
        if ($name==='' || mb_strlen($name)>120 || mb_strlen($description)>5000) throw new \DomainException('Nom obligatoire (120 caractères maximum), description limitée à 5 000 caractères.');
        $start=$this->date((string)($input['starts_at'] ?? ''));$end=$this->date((string)($input['ends_at'] ?? ''));
        if ($start <= $this->clock->now() || $end <= $start) throw new \DomainException('Le début doit être à venir et la fin postérieure au début.');
        $universeId=filter_var($input['universe_id'] ?? 0,FILTER_VALIDATE_INT);
        $universe=$universeId>0 ? $this->em->find(Universe::class,$universeId) : null;
        if ($universeId===false || $universeId<0 || ($universeId>0 && !$universe)) throw new \DomainException('Univers introuvable.');
        $draft=['model'=>$model,'name'=>$name,'description'=>$description,'starts_at'=>$start->format('Y-m-d\TH:i'),'ends_at'=>$end->format('Y-m-d\TH:i'),'universe_id'=>$universeId,'universe_label'=>$universe?->name ?? 'Tous les univers'];
        if ($model==='bonus') $draft['percentage']=$this->number($input['percentage'] ?? null,.01,500,'Bonus de production');
        if ($model==='challenge') {
            if (!$universe || $universe->status!=='active') throw new \DomainException('Choisissez un univers ouvert pour ce défi.');
            $duration=$end->getTimestamp()-$start->getTimestamp();
            if ($duration<60 || $duration>2592000) throw new \DomainException('Un défi doit durer entre une minute et trente jours.');
            if (($universe->startsAt && $start<$universe->startsAt) || ($universe->endsAt && $end>$universe->endsAt)) throw new \DomainException('Les dates doivent rester dans le calendrier de l’univers.');
            $objective=(string)($input['objective'] ?? '');if (!isset(self::OBJECTIVES[$objective])) throw new \DomainException('Objectif inconnu.');
            $draft+=['objective'=>$objective,'goal'=>$this->number($input['goal'] ?? null,1,1e12,'Objectif collectif'),
                'minimum_contribution'=>$this->number($input['minimum_contribution'] ?? 0,0,1e12,'Contribution minimale')];
            if ($objective==='exploration' && (floor($draft['goal'])!==$draft['goal'] || floor($draft['minimum_contribution'])!==$draft['minimum_contribution'])) throw new \DomainException('Les objectifs de découverte de systèmes utilisent des nombres entiers.');
            $kind=(string)($input['reward_kind'] ?? 'boost');
            if (!in_array($kind,['boost','resource_box'],true)) throw new \DomainException('Choisissez un boost ou une boîte.');
            $draft['reward_kind']=$kind;$draft['reward']=['kind'=>$kind];
            if ($kind==='resource_box') {
                $format=(string)($input['box_format'] ?? '');$draft['box_format']=$format;$draft['reward']['box']=$this->boxes->snapshot($universe,$format);
                $draft['box_signature']=hash('sha256',json_encode($this->boxes->settings($universe)));
            }
        }
        return $draft;
    }

    public function publish(Player $actor,array $draft,string $key): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$key)) throw new \DomainException('Aperçu invalide.');
        return $this->manager->transaction(function () use ($actor,$draft,$key): array {
            $this->authorize($actor);
            $existing=$this->em->getRepository(BoostEvent::class)->findOneBy(['eventKey'=>'guided-'.$key]);
            if ($existing) return ['kind'=>'challenge','id'=>$existing->id];
            $existingId=$this->em->getConnection()->fetchOne("SELECT id FROM game_event WHERE JSON_UNQUOTE(JSON_EXTRACT(parameters,'$.guided_key')) = :key LIMIT 1",['key'=>$key]);
            if ($existingId) return ['kind'=>'event','id'=>(int)$existingId];
            $universe=($draft['universe_id'] ?? 0)>0 ? $this->em->find(Universe::class,(int)$draft['universe_id']) : null;
            if ($universe) $this->manager->lock($universe);
            $prepared=$this->prepare((string)($draft['model'] ?? ''),$draft);
            if (($prepared['box_signature'] ?? null)!==($draft['box_signature'] ?? null)) throw new \DomainException('Les modèles de boîtes ont changé. Recréez un aperçu avant de planifier.');
            if ($prepared['model']!=='challenge') {
                $event=$this->events->save($actor,null,['name'=>$prepared['name'],'description'=>$prepared['description'],'type'=>$prepared['model']==='bonus'?'production_bonus':'announcement','status'=>'planned',
                    'starts_at'=>$prepared['starts_at'],'ends_at'=>$prepared['ends_at'],'universe_id'=>$prepared['universe_id'],'visible'=>true,'parameters'=>['guided_key'=>$key]+($prepared['model']==='bonus'?['percentage'=>$prepared['percentage']]:[])]);
                return ['kind'=>'event','id'=>$event->id];
            }
            $event=new BoostEvent();$event->eventKey='guided-'.$key;$event->templateKey=$prepared['objective'];$event->universe=$universe;
            $event->status='scheduled';$event->format='collective';$event->rewardMode='all_contributors';$event->contributionMetric=self::OBJECTIVES[$prepared['objective']]['metric'];
            $event->scheduledAt=$this->date($prepared['starts_at']);$event->endsAt=$this->date($prepared['ends_at']);$event->durationSeconds=$event->endsAt->getTimestamp()-$event->scheduledAt->getTimestamp();$event->globalGoal=$prepared['goal'];
            $event->configuration=['guided'=>true,'name'=>$prepared['name'],'description'=>$prepared['description'],'reward'=>$prepared['reward'],'minimum_contribution'=>$prepared['minimum_contribution']];
            $this->em->persist($event);$this->em->flush();
            $reward=$prepared['reward_kind']==='boost' ? 'un boost' : $prepared['reward']['box']['label'];
            $this->events->save($actor,null,['name'=>$prepared['name'],'description'=>$prepared['description']."\nDéfi collectif : ".self::OBJECTIVES[$prepared['objective']]['label'].'. Récompense par participant éligible : '.$reward.'.',
                'type'=>'announcement','status'=>'planned','starts_at'=>$this->clock->now()->format(DATE_ATOM),'ends_at'=>$prepared['ends_at'],'universe_id'=>$universe->id,'visible'=>true,'parameters'=>['guided_key'=>$key,'challenge_id'=>$event->id]]);
            $this->audit($actor,'guided_challenge_created',$event,['goal'=>$event->globalGoal,'reward'=>$prepared['reward_kind']]);
            return ['kind'=>'challenge','id'=>$event->id];
        });
    }

    public function cancelChallenge(Player $actor,BoostEvent $event,string $reason): void
    {
        $reason=trim($reason);if ($reason==='' || mb_strlen($reason)>1000) throw new \DomainException('Motif obligatoire, limité à 1 000 caractères.');
        $this->manager->transaction(function () use ($actor,$event,$reason): void {
            $this->authorize($actor);$this->manager->lock($event->universe);$this->manager->lock($event);
            if (!($event->configuration['guided'] ?? false) || !in_array($event->status,['scheduled','active'],true) || ($event->endsAt && $event->endsAt<=$this->clock->now())) throw new \DomainException('Ce défi ne peut plus être annulé ; aucune récompense déjà attribuée ne sera retirée.');
            $event->status='cancelled';$event->closedAt=$this->clock->now();
            foreach ($this->em->getRepository(GameEvent::class)->findBy(['type'=>'announcement','universe'=>$event->universe]) as $announcement) if (($announcement->parameters['challenge_id'] ?? null)===$event->id) $this->events->cancel($actor,$announcement->id,$reason,true);
            $this->audit($actor,'guided_challenge_cancelled',$event,['reason'=>$reason]);
        });
    }

    private function date(string $raw): \DateTimeImmutable
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\TH:i')!==$raw) throw new \DomainException('Date invalide. Renseignez une date et une heure en UTC.');
        return $date;
    }
    private function number(mixed $raw,float $minimum,float $maximum,string $label): float
    {
        if (!is_scalar($raw) || !is_numeric($raw) || !is_finite((float)$raw) || $raw<$minimum || $raw>$maximum) throw new \DomainException($label.' : nombre attendu entre '.$minimum.' et '.$maximum.'.');
        return (float)$raw;
    }
    private function authorize(Player $actor): void
    {
        $this->manager->lock($actor);if (!in_array('ROLE_ADMIN',$actor->getRoles(),true) || $actor->lockedAt) throw new \DomainException('Droits administrateur requis.');
    }
    private function audit(Player $actor,string $action,BoostEvent $event,array $details): void
    {
        $entry=new AdminAuditLog();$entry->actor=$actor;$entry->action=$action;$entry->targetType='boost_event';$entry->targetId=$event->id;$entry->createdAt=$this->clock->now();$entry->details=$details;$this->em->persist($entry);
    }
}
