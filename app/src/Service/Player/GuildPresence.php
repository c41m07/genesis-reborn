<?php
namespace App\Service\Player;

use App\Entity\{GuildMember,PlayerActivity,PlayerUniverse};
use Doctrine\ORM\EntityManagerInterface;

final class GuildPresence
{
    public function __construct(private EntityManagerInterface $em,private PlayerActivityService $activity) {}

    public function forMember(PlayerUniverse $viewer): array
    {
        if(!$viewer->isPlayerControlled() || $viewer->player->lockedAt!==null) return [];
        // La requête vérifie l’appartenance au moment de la lecture, sans agréger les univers.
        $activities=$this->em->createQueryBuilder()
            ->select('a','m','u','p')->from(PlayerActivity::class,'a')
            ->join('a.membership','m')->join('m.universe','u')->join('m.player','p')
            ->join(GuildMember::class,'gm','WITH','gm.playerUniverse=m')->join('gm.guild','g')
            ->where('m.universe=:universe')->andWhere('g.universe=:universe')->andWhere('g.archivedAt IS NULL')
            ->andWhere('EXISTS (SELECT viewerMember.id FROM '.GuildMember::class.' viewerMember WHERE viewerMember.guild=g AND viewerMember.playerUniverse=:viewer)')
            ->setParameter('universe',$viewer->universe)->setParameter('viewer',$viewer)
            ->getQuery()->getResult();
        $result=[];
        foreach($activities as $activity) $result[$activity->membership->id]=(bool)$this->activity->snapshot($activity)['online'];
        return $result;
    }
}
