<?php
namespace App\Service\Universe;
use App\Entity\{Player,PlayerUniverse};
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Service\Player\EmpireActivityService;
final class UniverseContext
{
    public function __construct(private Security $security,private ClockInterface $clock,private GameCatalog $catalog,private RequestStack $requests,private EmpireActivityService $activity) {}
    public function membership(): PlayerUniverse
    {
        $p=$this->security->getUser();
        $m=$p instanceof Player ? $p->currentPlayerUniverse : null;
        if (!$m || !$m->isPlayerControlled() || $m->player->id !== $p->id || !$m->universe->isAccessible($this->clock->now())) throw new UniverseNotAccessible();
        $this->requests->getCurrentRequest()?->attributes->set(EmpireActivityService::REQUEST_ATTRIBUTE, $m);
        return $m;
    }
    public function config(): array { return $this->catalog->forUniverse($this->membership()->universe); }
}
