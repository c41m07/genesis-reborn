<?php
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog, AdminCase, AdminCaseNote, Fleet, Player, PlayerUniverse, Universe};
use App\Manager\GameManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class AdminCaseService
{
    public const STATUSES = ['open'=>'Ouvert', 'investigating'=>'En cours d’examen', 'closed'=>'Clos'];
    public function __construct(private EntityManagerInterface $em, private GameManager $manager, private ClockInterface $clock, private AdminSurveillanceService $surveillance) {}

    public function create(Player $actor, string $title, string $description, int $universeId, int $membershipId, int $fleetId, string $alertKey, string $requestKey): AdminCase
    {
        $title=trim($title);$description=trim($description);$this->requestKey($requestKey);
        if ($title === '' || mb_strlen($title)>120 || mb_strlen($description)>10000) throw new \DomainException('Indiquez un titre de 1 à 120 caractères et une description de 10 000 caractères au maximum.');
        return $this->manager->transaction(function () use ($actor,$title,$description,$universeId,$membershipId,$fleetId,$alertKey,$requestKey): AdminCase {
            $this->authorize($actor);
            $existing=$this->em->getRepository(AdminCase::class)->findOneBy(['creationKey'=>$requestKey]);
            if ($existing) return $existing;
            $fleet=$fleetId>0 ? $this->em->find(Fleet::class,$fleetId) : null;
            $membership=$membershipId>0 ? $this->em->find(PlayerUniverse::class,$membershipId) : $fleet?->playerUniverse;
            $universe=$universeId>0 ? $this->em->find(Universe::class,$universeId) : $membership?->universe;
            if (($fleetId>0 && !$fleet) || ($membershipId>0 && !$membership) || ($universeId>0 && !$universe)) throw new \DomainException('Le contexte du dossier est introuvable.');
            if (($fleet && $fleet->playerUniverse->id !== $membership?->id) || ($membership && $membership->universe->id !== $universe?->id)) throw new \DomainException('Le joueur, la flotte et l’univers doivent correspondre.');
            if ($universe) $this->manager->lock($universe);
            if ($alertKey !== '') {
                if (!$universe) throw new \DomainException('Univers de l’alerte requis.');
                $alert=null;
                foreach ($this->surveillance->alerts($universe) as $candidate) if ($candidate['key'] === $alertKey) $alert=$candidate;
                if (!$alert) throw new \DomainException('Cette alerte n’est plus présente. Actualisez la surveillance.');
                $existing=$this->em->getRepository(AdminCase::class)->findOneBy(['alertKey'=>$alertKey]);
                if ($existing) return $existing;
                $membership=$alert['membership'];$fleet=$alert['fleet'];
                $description.="\n\nSignal observé : ".$alert['title']."\n".$alert['description'];
            }
            $case=new AdminCase();$case->universe=$universe;$case->membership=$membership;$case->fleet=$fleet;$case->createdBy=$actor;
            $case->title=$title;$case->description=$description;$case->alertKey=$alertKey !== '' ? $alertKey : null;$case->creationKey=$requestKey;
            $case->createdAt=$case->updatedAt=$this->clock->now();$this->em->persist($case);$this->em->flush();
            $this->audit($actor,'admin_case_create',$case,['universe_id'=>$universe?->id,'membership_id'=>$membership?->id,'fleet_id'=>$fleet?->id]);
            return $case;
        });
    }

    public function update(Player $actor, AdminCase $case, string $status, string $body, string $requestKey, string $expectedStatus): bool
    {
        $body=trim($body);$this->requestKey($requestKey);
        if (!isset(self::STATUSES[$status]) || mb_strlen($body)>10000) throw new \DomainException('État invalide ou note supérieure à 10 000 caractères.');
        return $this->manager->transaction(function () use ($actor,$case,$status,$body,$requestKey,$expectedStatus): bool {
            $this->authorize($actor);$this->manager->lock($case);
            if ($this->em->getRepository(AdminCaseNote::class)->findOneBy(['requestKey'=>$requestKey])) return false;
            if ($case->status !== $expectedStatus) throw new \DomainException('L’état du dossier a changé. Actualisez avant de poursuivre.');
            if ($body === '' && $case->status === $status) throw new \DomainException('Ajoutez une note ou changez l’état du dossier.');
            $before=$case->status;$case->status=$status;$case->updatedAt=$this->clock->now();
            $note=new AdminCaseNote();$note->caseFile=$case;$note->author=$actor;$note->body=$body !== '' ? $body : 'Statut : '.self::STATUSES[$status];
            $note->requestKey=$requestKey;$note->createdAt=$case->updatedAt;$this->em->persist($note);
            $this->audit($actor,'admin_case_update',$case,['before'=>$before,'after'=>$status,'note_added'=>true]);
            return true;
        });
    }

    public function saveThresholds(Player $actor, Universe $universe, array $input): void
    {
        $settings=[];
        foreach (AdminSurveillanceService::DEFAULTS as $key=>$default) {
            $value=filter_var($input[$key] ?? null,FILTER_VALIDATE_INT);
            if ($value === false || $value<1 || $value>($key==='late_minutes' ? 10080 : 100000)) throw new \DomainException('Seuil invalide : retard entre 1 et 10 080 minutes ; compteurs entre 1 et 100 000.');
            $settings[$key]=$value;
        }
        $this->manager->transaction(function () use ($actor,$universe,$settings): void {
            $this->authorize($actor);$this->manager->lock($universe);
            $before=$this->surveillance->settings($universe);$universe->gameSettings['admin_surveillance']=$settings;
            $entry=new AdminAuditLog();$entry->actor=$actor;$entry->action='admin_surveillance_settings';$entry->targetType='universe';$entry->targetId=$universe->id;
            $entry->createdAt=$this->clock->now();$entry->details=['before'=>$before,'after'=>$settings];$this->em->persist($entry);
        });
    }

    public function search(array $filters, int $page): array
    {
        $query=$this->em->createQueryBuilder()->select('c','u','m','p')->from(AdminCase::class,'c')->leftJoin('c.universe','u')->leftJoin('c.membership','m')->leftJoin('m.player','p');
        if ($filters['status']==='active') $query->andWhere('c.status IN (:states)')->setParameter('states',['open','investigating']);
        elseif ($filters['status']!=='') $query->andWhere('c.status=:status')->setParameter('status',$filters['status']);
        foreach (['universe'=>'c.universe','membership'=>'c.membership','fleet'=>'c.fleet'] as $key=>$field) if ($filters[$key]>0) $query->andWhere($field.'=:'.$key)->setParameter($key,$filters[$key]);
        if ($filters['q']!=='') $query->andWhere('LOWER(c.title) LIKE :q OR LOWER(c.description) LIKE :q OR LOWER(p.username) LIKE :q')->setParameter('q','%'.mb_strtolower($filters['q']).'%');
        $total=(int)(clone $query)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();$pages=max(1,(int)ceil($total/50));$page=min($pages,max(1,$page));
        return ['cases'=>$query->orderBy('c.updatedAt','DESC')->addOrderBy('c.id','DESC')->setFirstResult(($page-1)*50)->setMaxResults(50)->getQuery()->getResult(),'total'=>$total,'page'=>$page,'pages'=>$pages,'filters'=>$filters,'statuses'=>self::STATUSES];
    }

    public function activeCount(): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(c.id)')->from(AdminCase::class,'c')->where('c.status IN (:states)')->setParameter('states',['open','investigating'])->getQuery()->getSingleScalarResult();
    }

    private function authorize(Player $actor): void
    {
        $this->manager->lock($actor);
        if (!in_array('ROLE_ADMIN',$actor->getRoles(),true) || $actor->lockedAt) throw new \DomainException('Droits administrateur requis.');
    }
    private function requestKey(string $key): void { if (!preg_match('/^[a-f0-9]{32}$/D',$key)) throw new \DomainException('Identifiant du formulaire invalide.'); }
    private function audit(Player $actor,string $action,AdminCase $case,array $details): void
    {
        $entry=new AdminAuditLog();$entry->actor=$actor;$entry->action=$action;$entry->targetType='admin_case';$entry->targetId=$case->id;$entry->createdAt=$this->clock->now();$entry->details=$details;$this->em->persist($entry);
    }
}
