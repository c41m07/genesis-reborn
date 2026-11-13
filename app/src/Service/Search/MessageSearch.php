<?php
namespace App\Service\Search;

use App\Entity\{PlayerUniverse,SocialMessage};
use App\Service\Player\RelationResolver;
use Doctrine\ORM\EntityManagerInterface;

final class MessageSearch
{
    public function __construct(private EntityManagerInterface $em,private RelationResolver $relations) {}

    public function page(PlayerUniverse $viewer,SearchCriteria $criteria,string $channel,int $peer,int $page): array
    {
        if(!$viewer->isPlayerControlled()) throw new \DomainException('Empire inaccessible.');
        if(!in_array($channel,['all','private','guild','global'],true) || $peer<0) throw new \DomainException('Conversation invalide.');
        $guild=$this->relations->guild($viewer);
        $private='m.guild_id IS NULL AND m.recipient_id IS NOT NULL AND recipient.universe_id=:universe AND (m.sender_id=:viewer OR m.recipient_id=:viewer)';
        $global='m.guild_id IS NULL AND m.recipient_id IS NULL';
        $guildSql=$guild?'m.guild_id=:guild AND m.recipient_id IS NULL AND EXISTS (SELECT 1 FROM guild_member gm WHERE gm.guild_id=m.guild_id AND gm.player_universe_id=:viewer)':'1=0';
        $scope=match($channel){'private'=>$private,'guild'=>$guildSql,'global'=>$global,default=>'('.$private.') OR ('.$global.') OR ('.$guildSql.')'};
        $sql=' FROM social_message m JOIN player_universe sender ON sender.id=m.sender_id LEFT JOIN player_universe recipient ON recipient.id=m.recipient_id LEFT JOIN player sp ON sp.id=sender.player_id LEFT JOIN player rp ON rp.id=recipient.player_id WHERE sender.universe_id=:universe AND ('.$scope.')';
        $params=['universe'=>$viewer->universe->id];
        if(str_contains($sql,':viewer')) $params['viewer']=$viewer->id;
        if(str_contains($sql,':guild')) $params['guild']=$guild->id;
        if($peer) {$sql.=' AND m.guild_id IS NULL AND m.recipient_id IS NOT NULL AND (m.sender_id=:peer OR m.recipient_id=:peer)';$params['peer']=$peer;}
        if($criteria->values['q']!=='') {$sql.=" AND LOWER(m.content) LIKE :query ESCAPE '!'";$params['query']=SearchCriteria::pattern($criteria->values['q']);}
        if($criteria->values['participant']!=='') {$sql.=" AND (LOWER(COALESCE(sp.username,'Empire oublié')) LIKE :person ESCAPE '!' OR (m.recipient_id IS NOT NULL AND LOWER(COALESCE(rp.username,'Empire oublié')) LIKE :person ESCAPE '!'))";$params['person']=SearchCriteria::pattern($criteria->values['participant']);}
        if($criteria->since) {$sql.=' AND m.created_at>=:since';$params['since']=$criteria->since;}
        if($criteria->before) {$sql.=' AND m.created_at<:before';$params['before']=$criteria->before;}
        $db=$this->em->getConnection();$count=(int)$db->fetchOne('SELECT COUNT(*)'.$sql,$params);$pages=max(1,(int)ceil($count/50));$page=max(1,min($page,$pages));
        $ids=array_map('intval',$db->fetchFirstColumn('SELECT m.id'.$sql.' ORDER BY m.created_at DESC,m.id DESC LIMIT 50 OFFSET '.(($page-1)*50),$params));
        $entries=[];
        if($ids) {
            $rows=$this->em->createQueryBuilder()->select('m','s','p','fp','r','rp','rfp')->from(SocialMessage::class,'m')->join('m.sender','s')->leftJoin('s.player','p')->leftJoin('s.formerPlayer','fp')->leftJoin('m.recipient','r')->leftJoin('r.player','rp')->leftJoin('r.formerPlayer','rfp')->where('m.id IN (:ids)')->setParameter('ids',$ids)->getQuery()->getResult();
            $byId=[];foreach($rows as $row) $byId[$row->id]=$row;
            foreach($ids as $id) $entries[]=$byId[$id];
        }
        return ['entries'=>$entries,'count'=>$count,'page'=>$page,'pages'=>$pages];
    }
}
