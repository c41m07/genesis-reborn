<?php
namespace App\Service\Boost;

use App\Entity\{AdminAuditLog, BoostEvent, Player, Universe};
use App\Manager\GameManager;
use App\Service\Admin\AdminEventPlanner;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class WeeklyRotationService
{
    public const FORMATS=['individual'=>'Individuel','collective'=>'Collectif','competitive'=>'Compétitif'];
    public const REWARDS=['all_contributors'=>'Tous les contributeurs','threshold'=>'Seuil individuel','top3'=>'Top 3 et ex æquo'];
    public function __construct(private GameCatalog $catalog,private GameManager $manager,private EntityManagerInterface $em,private ClockInterface $clock) {}

    public function definitions(Universe $universe): array
    {
        if (array_key_exists('weekly_rotations',$universe->gameSettings)) return $universe->gameSettings['weekly_rotations'];
        return array_map(static fn(array $row):array=>$row+['objective'=>$row['key'],'enabled'=>true,'name'=>'Rotation · '.(AdminEventPlanner::OBJECTIVES[$row['key']]['label'] ?? $row['key']),
            'description'=>'','duration_seconds'=>604800],$this->catalog->all('boosts')['events']['weekly_rotation'] ?? []);
    }

    public function create(Player $actor,Universe $universe,array $input): string
    {
        $name=trim((string)($input['name'] ?? ''));$description=trim((string)($input['description'] ?? ''));
        $objective=(string)($input['objective'] ?? '');$format=(string)($input['format'] ?? '');$mode=(string)($input['reward_mode'] ?? '');
        if ($name==='' || mb_strlen($name)>120 || mb_strlen($description)>2000) throw new \DomainException('Nom obligatoire (120 caractères maximum), description limitée à 2 000 caractères.');
        if (!isset(AdminEventPlanner::OBJECTIVES[$objective],self::FORMATS[$format],self::REWARDS[$mode])) throw new \DomainException('Objectif, format ou mode de récompense inconnu.');
        $hours=$this->number($input['duration_hours'] ?? null,1,720,'Durée en heures');
        $goal=($input['global_goal'] ?? '')===''?null:$this->number($input['global_goal'],1,1e12,'Objectif global');
        $threshold=($input['threshold_value'] ?? '')===''?null:$this->number($input['threshold_value'],1,1e12,'Seuil individuel');
        if ($mode==='top3' && $goal===null) throw new \DomainException('Le Top 3 nécessite un objectif global.');
        if ($mode==='threshold' && $threshold===null) throw new \DomainException('Le mode seuil nécessite un seuil individuel.');
        if ($objective==='exploration' && (($goal!==null && floor($goal)!==$goal) || ($threshold!==null && floor($threshold)!==$threshold))) throw new \DomainException('Les découvertes utilisent des objectifs et seuils entiers.');
        $reason=$this->reason((string)($input['reason'] ?? ''));
        return $this->manager->transaction(function () use ($actor,$universe,$input,$name,$description,$objective,$format,$mode,$hours,$goal,$threshold,$reason): string {
            $this->authorize($actor);$this->manager->lock($universe);
            if ($universe->status!=='active') throw new \DomainException('Choisissez un univers ouvert.');
            $definitions=$this->definitions($universe);
            if (count($definitions)>=30) throw new \DomainException('Trente modèles au maximum par univers.');
            $key='rotation-'.bin2hex(random_bytes(8));
            $definition=['key'=>$key,'name'=>$name,'description'=>$description,'objective'=>$objective,'format'=>$format,'reward_mode'=>$mode,
                'contribution_metric'=>AdminEventPlanner::OBJECTIVES[$objective]['metric'],'duration_seconds'=>(int)round($hours*3600),
                'global_goal'=>$goal,'threshold_value'=>$threshold,'enabled'=>!empty($input['enabled'])];
            $definitions[]=$definition;$universe->gameSettings['weekly_rotations']=$definitions;
            $this->audit($actor,'weekly_rotation_created','universe',$universe->id,['reason'=>$reason,'definition'=>$definition]);
            return $key;
        });
    }

    public function setEnabled(Player $actor,Universe $universe,string $key,bool $enabled,string $reason): void
    {
        $reason=$this->reason($reason);
        $this->manager->transaction(function () use ($actor,$universe,$key,$enabled,$reason): void {
            $this->authorize($actor);$this->manager->lock($universe);$definitions=$this->definitions($universe);$found=false;
            foreach ($definitions as &$definition) if ($definition['key']===$key) { $definition['enabled']=$enabled;$found=true;break; }
            unset($definition);
            if (!$found) throw new \DomainException('Modèle de rotation introuvable.');
            $universe->gameSettings['weekly_rotations']=$definitions;
            $this->audit($actor,$enabled?'weekly_rotation_enabled':'weekly_rotation_disabled','universe',$universe->id,['reason'=>$reason,'key'=>$key]);
        });
    }

    public function cancel(Player $actor,BoostEvent $event,string $reason): void
    {
        $reason=$this->reason($reason);
        $this->manager->transaction(function () use ($actor,$event,$reason): void {
            $this->authorize($actor);$this->manager->lock($event->universe);$this->manager->lock($event);
            if (!str_starts_with($event->eventKey,'weekly-')) throw new \DomainException('Cette occurrence n’est pas une rotation hebdomadaire.');
            if ($event->status==='cancelled') return;
            if (!in_array($event->status,['scheduled','active'],true)) throw new \DomainException('Cette rotation est déjà terminée. Son historique est conservé.');
            if ($event->status==='active' && $event->endsAt && $event->endsAt<=$this->clock->now()) throw new \DomainException('Attendez la clôture et l’attribution des récompenses avant de consulter le résultat.');
            $event->status='cancelled';$event->closedAt=$this->clock->now();
            $event->configuration=array_replace($event->configuration ?? [],['cancelled_reason'=>$reason]);
            $this->audit($actor,'weekly_rotation_cancelled','boost_event',$event->id,['reason'=>$reason,'event_key'=>$event->eventKey]);
        });
    }

    private function authorize(Player $actor): void
    {
        $this->manager->lock($actor);
        if (!in_array('ROLE_ADMIN',$actor->getRoles(),true) || $actor->lockedAt) throw new \DomainException('Droits administrateur requis.');
    }
    private function reason(string $reason): string
    {
        $reason=trim($reason);if ($reason==='' || mb_strlen($reason)>1000) throw new \DomainException('Motif obligatoire, limité à 1 000 caractères.');return $reason;
    }
    private function number(mixed $value,float $min,float $max,string $label): float
    {
        if (!is_scalar($value) || !is_numeric($value) || !is_finite((float)$value) || $value<$min || $value>$max) throw new \DomainException($label.' : valeur attendue entre '.$min.' et '.$max.'.');return (float)$value;
    }
    private function audit(Player $actor,string $action,string $targetType,int $targetId,array $details): void
    {
        $log=new AdminAuditLog();$log->actor=$actor;$log->action=$action;$log->targetType=$targetType;$log->targetId=$targetId;$log->details=$details;$log->createdAt=$this->clock->now();$this->manager->save($log);
    }
}
