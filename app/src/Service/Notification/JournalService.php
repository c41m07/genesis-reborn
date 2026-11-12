<?php
namespace App\Service\Notification;

use App\Entity\{Notification,PlayerUniverse};
use App\Manager\GameManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;

final class JournalService
{
    public const KINDS=['queue','arrival','combat','transport','spy','conquest'];
    public const PAGE_SIZE=50;

    public function __construct(private EntityManagerInterface $em,private GameManager $manager,private ClockInterface $clock) {}

    public function open(PlayerUniverse $membership): void
    {
        $this->manager->transaction(function()use($membership): void {
            $this->manager->lock($membership);
            $this->assertAccessible($membership);
            $now=$this->clock->now();
            if($membership->journalStartedAt!==null) {
                $this->em->createQueryBuilder()->update(Notification::class,'n')->set('n.journalAvailableAt',':now')
                    ->where('n.recipient = :member')->andWhere('n.journalKind IN (:kinds)')
                    ->andWhere('n.consultedAt IS NULL')->andWhere('n.journalAvailableAt IS NULL')
                    ->setParameter('member',$membership)->setParameter('kinds',self::KINDS)->setParameter('now',$now)->getQuery()->execute();
            } else {
                // La reprise exclut les notifications déjà présentes, sans les consulter ni les supprimer.
                $this->em->createQueryBuilder()->update(Notification::class,'n')->set('n.journalKind','NULL')
                    ->where('n.recipient = :member')->andWhere('n.journalAvailableAt IS NULL')
                    ->setParameter('member',$membership)->getQuery()->execute();
                $membership->journalStartedAt=$now;
            }
            $membership->overviewVisitedAt=$now;
        });
    }

    public function data(PlayerUniverse $membership): array
    {
        if($membership->journalStartedAt===null) return ['notifications'=>[],'unreadCount'=>0,'availableCount'=>0,'startedAt'=>null];
        $unread=(int)$this->pending($membership)->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();
        $available=$this->pending($membership)->andWhere('n.journalAvailableAt IS NOT NULL');
        $count=(int)(clone $available)->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();
        $notifications=$available->orderBy('n.id','DESC')->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult();
        return ['notifications'=>$notifications,'unreadCount'=>$unread,'availableCount'=>$count,'startedAt'=>$membership->journalStartedAt];
    }

    public function consult(PlayerUniverse $membership,array $ids): void
    {
        if(!$ids || count($ids)>self::PAGE_SIZE) throw new \DomainException('Sélection de journal invalide.');
        $ids=array_values(array_unique($ids));
        $this->manager->transaction(function()use($membership,$ids): void {
            $this->manager->lock($membership);$this->assertAccessible($membership);
            $query=$this->em->createQueryBuilder()->select('COUNT(n.id)')->from(Notification::class,'n')
                ->where('n.recipient = :member')->andWhere('n.id IN (:ids)')->andWhere('n.journalAvailableAt IS NOT NULL')
                ->andWhere('n.journalKind IN (:kinds)')->setParameter('member',$membership)->setParameter('ids',$ids)->setParameter('kinds',self::KINDS);
            if((int)$query->getQuery()->getSingleScalarResult()!==count($ids)) throw new \DomainException('Événements indisponibles dans ce journal.');
            $this->em->createQueryBuilder()->update(Notification::class,'n')->set('n.consultedAt',':now')
                ->where('n.recipient = :member')->andWhere('n.id IN (:ids)')->andWhere('n.consultedAt IS NULL')
                ->setParameter('member',$membership)->setParameter('ids',$ids)->setParameter('now',$this->clock->now())->getQuery()->execute();
        });
    }

    private function pending(PlayerUniverse $membership): QueryBuilder
    {
        return $this->em->createQueryBuilder()->select('n')->from(Notification::class,'n')
            ->where('n.recipient = :member')->andWhere('n.journalKind IN (:kinds)')->andWhere('n.consultedAt IS NULL')
            ->setParameter('member',$membership)->setParameter('kinds',self::KINDS);
    }

    private function assertAccessible(PlayerUniverse $membership): void
    {
        if(!$membership->isPlayerControlled() || !$membership->universe->isAccessible($this->clock->now())) throw new \DomainException('Journal inaccessible.');
    }
}
