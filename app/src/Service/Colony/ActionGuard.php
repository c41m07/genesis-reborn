<?php
namespace App\Service\Colony;
use App\Entity\{PlayerUniverse,Planet,ActionReceipt};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
final class ActionGuard
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private ClockInterface $clock,private \App\Service\Player\EmpireActivityService $activity) {}
    // Every caller is inside a database transaction. Membership serializes research across planets.
    public function lock(PlayerUniverse $m,Planet $planet): void
    {
        $this->lockMembership($m);$this->manager->lock($planet);
        if($planet->owner?->id!==$m->id || $planet->solarSystem->galaxy->universe->id!==$m->universe->id) throw new \DomainException('Cette planète ne vous appartient pas dans cet univers.');
    }
    public function lockMembership(PlayerUniverse $m): void
    {
        $this->lockMembershipForProcessing($m);
        $this->activity->assertCanControl($m);
    }
    // Les traitements automatiques peuvent terminer les commandes déjà payées d'un empire oublié.
    public function lockMembershipForProcessing(PlayerUniverse $m): void
    {
        $this->manager->lock($m);$this->manager->lock($m->universe);
        if(!$m->universe->isAccessible($this->clock->now())) throw new \DomainException('Univers inaccessible.');
    }
    public function lockForCompletion(PlayerUniverse $m,Planet $planet): void
    {
        $this->lockMembershipForProcessing($m);$this->manager->lock($planet);
        if($planet->owner?->id!==$m->id || $planet->solarSystem->galaxy->universe->id!==$m->universe->id) throw new \DomainException('Cette planète ne correspond plus à la commande.');
    }
    public function receipt(PlayerUniverse $m,string $key): bool
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$key)) throw new \DomainException('Identifiant de requête invalide.');
        if($this->repo->one(ActionReceipt::class,['playerUniverse'=>$m,'requestKey'=>$key])) return false;
        $r=new ActionReceipt();$r->playerUniverse=$m;$r->requestKey=$key;$this->manager->save($r);return true;
    }
}
