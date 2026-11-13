<?php
namespace App\Service\Notification;
use App\Entity\{PlayerUniverse,Notification,OutboxMessage};
use App\Manager\GameManager;
use Psr\Clock\ClockInterface;
final class NotificationService
{
    public function __construct(private GameManager $manager,private ClockInterface $clock) {}
    public static function topic(PlayerUniverse $m): string { return 'https://genesis-reborn.localhost/players/'.$m->id.'/events'; }
    // Called in the same transaction as the completed action.
    public function enqueue(PlayerUniverse $recipient,string $message,?string $journalKind=null): void
    {
        if($journalKind!==null && !in_array($journalKind,JournalService::KINDS,true)) throw new \InvalidArgumentException('Catégorie de journal inconnue.');
        $n=new Notification();$n->journalKind=$journalKind;$n->recipient=$recipient;$n->message=$message;$n->createdAt=$this->clock->now();$this->manager->save($n);$this->manager->flush();
        $o=new OutboxMessage();$o->kind='notification';$o->targetId=$n->id;$o->token='notification-'.$n->id;$o->dueAt=$this->clock->now();$this->manager->save($o);
    }
}
