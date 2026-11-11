<?php
namespace App\Service\Notification;
use App\Entity\Notification;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Integration\Mercure\PublisherInterface;
use App\Message\{QueueItemFinished,FleetArrived,CombatEventDue,UniverseClosureDue,OccupationDue,GuildProjectRewardsDue};
use Symfony\Component\Messenger\{MessageBusInterface,Stamp\DelayStamp};
use Psr\Clock\ClockInterface;
final class OutboxRelay
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private MessageBusInterface $bus,private PublisherInterface $publisher,private ClockInterface $clock) {}
    public function run(bool $notifications=false): int
    {
        $count=0;
        foreach($this->repo->outbox($notifications) as $o) {
            $this->manager->transaction(function()use($o,$notifications){
                $this->manager->lock($o);
                if($notifications) {
                    $n=$this->repo->find(Notification::class,$o->targetId);
                    if($n) $this->publisher->publish(NotificationService::topic($n->recipient),['message'=>$n->message,'id'=>$n->id],'notification-'.$n->id);
                } else {
                    $message=match($o->kind){'queue'=>new QueueItemFinished($o->targetId,$o->token),'fleet'=>new FleetArrived($o->targetId,$o->token),'combat'=>new CombatEventDue($o->targetId,$o->token),'guild_project'=>new GuildProjectRewardsDue($o->targetId,$o->token),'occupation'=>new OccupationDue($o->targetId,$o->token),'universe'=>new UniverseClosureDue($o->targetId,$o->token),default=>throw new \LogicException('Événement inconnu.')};
                    $delay=max(0,($o->dueAt->getTimestamp()-$this->clock->now()->getTimestamp())*1000);
                    $this->bus->dispatch($message,[new DelayStamp($delay)]);
                }
                // A crash after sending can duplicate an event; handlers and notification IDs are idempotent.
                $this->manager->remove($o);
            });++$count;
        }
        $this->manager->clear();return $count;
    }
}
