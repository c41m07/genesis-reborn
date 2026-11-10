<?php
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog, BoostEvent, BoostEventContribution, GameEvent, Player, PlayerBoost, Universe};
use App\Manager\GameManager;
use App\Service\Event\GameEventService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class AdminEventRemovalService
{
    public function __construct(private EntityManagerInterface $em, private GameManager $manager, private GameEventService $events, private AdminEventPlanner $planner, private ClockInterface $clock) {}

    /** Retourne true uniquement si l'enregistrement a réellement été supprimé. */
    public function remove(Player $actor, GameEvent|BoostEvent $event, string $reason): bool
    {
        $reason=trim($reason);
        if ($reason==='' || mb_strlen($reason)>1000) throw new \DomainException('Motif obligatoire, limité à 1 000 caractères.');
        return $this->manager->transaction(function () use ($actor,$event,$reason): bool {
            $this->manager->lock($actor);
            if (!in_array('ROLE_ADMIN',$actor->getRoles(),true) || $actor->lockedAt) throw new \DomainException('Droits administrateur requis.');
            $universes=$event->universe ? [$event->universe] : $this->em->getRepository(Universe::class)->findBy([],['id'=>'ASC']);
            foreach ($universes as $universe) $this->manager->lock($universe);
            $this->manager->lock($event);
            if ($event instanceof BoostEvent) {
                if (!($event->configuration['guided'] ?? false)) throw new \DomainException('Les rotations hebdomadaires s’annulent depuis leur fiche et ne sont jamais supprimées.');
                if (!empty($event->configuration['archived_at'])) return false;
                $unused=$event->startedAt===null && $event->totalContribution==0
                    && $this->em->getRepository(BoostEventContribution::class)->count(['event'=>$event])===0
                    && !$this->em->createQueryBuilder()->select('b.id')->from(PlayerBoost::class,'b')->where('b.sourceKey LIKE :prefix')
                        ->setParameter('prefix','event-'.$event->eventKey.'-player-%')->setMaxResults(1)->getQuery()->getOneOrNullResult();
                $announcements=array_filter($this->em->getRepository(GameEvent::class)->findBy(['type'=>'announcement','universe'=>$event->universe]),
                    static fn(GameEvent $announcement):bool=>($announcement->parameters['challenge_id'] ?? null)===$event->id);
                if (!$unused && in_array($event->status,['scheduled','active'],true) && $event->endsAt>$this->clock->now()) {
                    $this->planner->cancelChallenge($actor,$event,$reason);
                } elseif (!$unused && $event->status==='scheduled') {
                    $event->status='cancelled';$event->closedAt=$this->clock->now();
                } elseif (!$unused && $event->status==='active') {
                    throw new \DomainException('La clôture et les récompenses doivent être traitées avant de retirer ce défi.');
                }
                foreach ($announcements as $announcement) {
                    if ($unused) $this->manager->remove($announcement);
                    else { $announcement->visibleToPlayers=false;$announcement->parameters['archived_at']=$this->clock->now()->format(DATE_ATOM); }
                }
                if (!$unused) $event->configuration['archived_at']=$this->clock->now()->format(DATE_ATOM);
            } else {
                if (!empty($event->parameters['challenge_id'])) throw new \DomainException('Supprimez cette annonce depuis la fiche de son défi.');
                if (!empty($event->parameters['archived_at'])) return false;
                $unused=($event->status==='draft' || ($event->startsAt>$this->clock->now() && $event->status!=='active')) && empty($event->parameters['production_history']);
                if (!$unused) {
                    if (in_array($event->status,['planned','active'],true) && $event->endsAt>$this->clock->now()) {
                        if (empty($event->parameters['guided_key']) && $event->type==='production_bonus' && $event->startsAt<$this->clock->now()) {
                            $event->parameters['production_history']=[['from'=>$event->startsAt->format(DATE_ATOM),'until'=>$this->clock->now()->format(DATE_ATOM),
                                'percentage'=>(float)$event->parameters['percentage'],'universe_id'=>$event->universe?->id]];
                            $this->manager->flush();
                        }
                        $this->events->cancel($actor,$event->id,$reason,true);
                    }
                    $event->visibleToPlayers=false;$event->parameters['archived_at']=$this->clock->now()->format(DATE_ATOM);
                }
            }
            $log=new AdminAuditLog();$log->actor=$actor;$log->targetType=$event instanceof BoostEvent?'boost_event':'game_event';
            $log->targetId=$event->id;$log->action=$unused?'admin_event_deleted':'admin_event_archived';
            $log->details=['reason'=>$reason,'name'=>$event instanceof BoostEvent?($event->configuration['name'] ?? ''):$event->name];
            $log->createdAt=$this->clock->now();$this->manager->save($log);
            if ($unused) $this->manager->remove($event);
            return $unused;
        });
    }
}
