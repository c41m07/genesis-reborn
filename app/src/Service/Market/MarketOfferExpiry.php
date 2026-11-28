<?php
declare(strict_types=1);
namespace App\Service\Market;

use App\Entity\{Planet,PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Colony\ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class MarketOfferExpiry
{
    public function __construct(private EntityManagerInterface $em, private GameManager $manager, private PlayerMarket $market, private ResourceService $resources, private ClockInterface $clock) {}

    public function tick(): void
    {
        $db = $this->em->getConnection();
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $due = $db->fetchAllAssociative('SELECT id,seller_id FROM market_offer WHERE expires_at <= ? ORDER BY expires_at,id LIMIT 50',[$now]);
        foreach ($due as $row) {
            $this->manager->transaction(function () use ($row,$now,$db): void {
                $member = $this->em->find(PlayerUniverse::class,(int)$row['seller_id']);
                if (!$member) return;
                $this->manager->lockCurrent($member);
                $this->manager->lockCurrent($member->universe);
                // Un achat ou un autre relais peut avoir soldé le lot avant la prise du verrou.
                $offer = $db->fetchAssociative('SELECT resource,remaining,return_planet_id FROM market_offer WHERE id=? AND seller_id=? AND universe_id=? AND expires_at<=? FOR UPDATE',[$row['id'],$member->id,$member->universe->id,$now]);
                if (!$offer) return;
                $planet = $this->em->find(Planet::class,(int)$offer['return_planet_id']);
                if ($planet) $this->manager->lockCurrent($planet);
                if ($planet && $planet->owner?->id === $member->id && $planet->solarSystem->galaxy->universe->id === $member->universe->id) {
                    $this->resources->deposit($planet,[$offer['resource']=>(int)$offer['remaining']]);
                } else {
                    // Une colonie perdue ne reçoit pas les biens de son ancien commandant.
                    $this->market->accountLocked($member)->moveGoods($offer['resource'],(int)$offer['remaining']);
                }
                $db->delete('market_offer',['id'=>$row['id']]);
            });
        }
    }
}
