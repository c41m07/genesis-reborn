<?php
namespace App\Service\Universe;
use App\Entity\{Player,PlayerUniverse,Universe,Planet,PlayerDiscovery};
use App\Repository\GameRepository;
use App\Manager\GameManager;
use App\Service\Colony\ResourceService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
final class UniverseService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private ResourceService $resources,private ClockInterface $clock,private PlanetNameGenerator $planetNames,private \App\Service\Player\EmpireActivityService $activity,private EntityManagerInterface $em,private \App\Service\Advent\AdventParticipation $advent) {}
    public function select(Player $player,int $universeId): void
    {
        $this->manager->transaction(function()use($player,$universeId){
            // Préserver univers → compte, sans ouvrir un instantané avant d’avoir
            // les deux verrous : une autre première sélection peut attendre ici.
            $u = $this->em->find(Universe::class, $universeId, LockMode::PESSIMISTIC_WRITE);
            if (!$u) throw new \DomainException('Univers introuvable.');
            $this->manager->lockCurrent($player);
            if (!$u->isAccessible($this->clock->now())) throw new \DomainException('Cet univers est fermé ou pas encore ouvert.');
            $m=$this->repo->one(PlayerUniverse::class,['player'=>$player,'universe'=>$u,'forgottenAt'=>null]);
            if($m!==null) $this->activity->assertCanControl($m);
            if (!$m) {
                $planets=array_values(array_filter($this->repo->planets($u,null,true), static fn (Planet $planet): bool => !$planet->npcPlanet));
                if (!$planets) throw new \DomainException('Cet univers ne dispose plus de planète libre.');
                $m=new PlayerUniverse();$m->player=$player;$m->universe=$u;$m->joinedAt=$this->clock->now();$m->lastActivityAt=$m->joinedAt;$this->manager->save($m);
                shuffle($planets);
                $planet = null;
                foreach ($planets as $candidate) {
                    $this->manager->lock($candidate);
                    if ($candidate->owner === null) {
                        $planet = $candidate;
                        break;
                    }
                }
                if ($planet === null) {
                    throw new \DomainException('Cet univers ne dispose plus de planète libre.');
                }
                $planet->name = $this->planetNames->generate($m);
                $planet->acquireBy($m,$this->clock->now());
                // Consommer l’éligibilité par le résultat de l’écriture SQL, pas l’instantané
                // Doctrine : deux premières sélections concurrentes portent sur le même compte.
                $firstAccountPlanet = $this->em->getConnection()->executeStatement(
                    'UPDATE player SET first_planet_starter_pending = 0 WHERE id = ? AND first_planet_starter_pending = 1',
                    [$player->id],
                ) === 1;
                $this->resources->initialize($planet, firstAccountPlanet: $firstAccountPlanet);
                $player->firstPlanetStarterPending = false;
                $d=new PlayerDiscovery();$d->playerUniverse=$m;$d->solarSystem=$planet->solarSystem;$this->manager->save($d);
                $this->advent->observeJoinLocked($m, $planet);
            }
            $player->currentPlayerUniverse=$m;
        });
    }
}
