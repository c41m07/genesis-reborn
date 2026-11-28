<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHall, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class GuildAtelierDeliveryPlanning
{
    public function __construct(
        private GameManager $manager,
        private EntityManagerInterface $entityManager,
        private GameRepository $repository,
        private GuildHallAccess $access,
        private GuildAtelierDonations $atelier,
        private GuildAtelierStockStore $ships,
        private GuildHallStockStore $materials,
        private GuildHallScheduleProvider $schedules,
        private GameCatalog $catalog,
        private GuildHallFlight $flight,
        private ClockInterface $clock,
    ) {
    }

    /** @param array<string,array<string,int>> $composition */
    public function preview(PlayerUniverse $actor, int $guildId, int $hallId, int $destinationId, array $composition, float $ratio): GuildAtelierDeliveryPlan
    {
        return $this->manager->transaction(function () use ($actor, $guildId, $hallId, $destinationId, $composition, $ratio): GuildAtelierDeliveryPlan {
            $hall = $this->access->lockAtelierDelivery($actor, $guildId, $hallId);

            return $this->prepareLocked($actor, $hall, $destinationId, $composition, $ratio);
        });
    }

    /**
     * Appeler avec les verrous de lockAtelierDelivery ; le départ devra relire
     * ce même devis puis débiter les deux stocks dans la transaction en cours.
     *
     * @param array<string,array<string,int>> $composition
     */
    public function prepareLocked(PlayerUniverse $actor, GuildHall $hall, int $destinationId, array $composition, float $ratio): GuildAtelierDeliveryPlan
    {
        if ($this->schedules->schedule() === null || !$this->atelier->isBuiltLocked($hall)) {
            throw new \DomainException('Un Atelier opérationnel est requis pour livrer des vaisseaux.');
        }
        if (!is_finite($ratio) || $ratio < 0.1 || $ratio > 1.0) {
            throw new \DomainException('Vitesse de livraison invalide.');
        }
        $destination = $this->repository->find(Planet::class, $destinationId);
        if (!$destination instanceof Planet || $destination->solarSystem->galaxy->universe->id !== $actor->universe->id) {
            throw new \DomainException('Choisissez la colonie d’un autre membre de la guilde.');
        }
        $this->manager->lockCurrent($destination);
        $recipient = $destination->owner;
        if ($destination->npcPlanet || $recipient === null || $recipient->id === $actor->id || $recipient->isForgotten() || $recipient->universe->id !== $actor->universe->id
            || (int) $this->entityManager->getConnection()->fetchOne(
                'SELECT guild_id FROM guild_member WHERE player_universe_id = ? FOR UPDATE',
                [$recipient->id],
            ) !== $hall->guild->id) {
            throw new \DomainException('Choisissez la colonie d’un autre membre de la guilde.');
        }
        $config = $this->catalog->forUniverse($actor->universe);
        $remaining = (int) $config['limits']['max_fleet_size'];
        $units = [];
        $orders = [];
        ksort($composition);
        foreach ($composition as $slug => $ranks) {
            if (!is_array($ranks) || !isset($config['catalog']['ships'][$slug])) {
                throw new \DomainException('Composition de livraison invalide.');
            }
            ksort($ranks);
            foreach ($ranks as $tier => $quantity) {
                if (!in_array($tier, GameCatalog::TIERS, true) || !is_int($quantity) || $quantity <= 0 || $quantity > $remaining) {
                    throw new \DomainException('Composition de livraison invalide.');
                }
                $ship = $this->catalog->getTierForUniverse($actor->universe, 'ships', $slug, $tier);
                if (($ship['mobile'] ?? true) === false || isset($ship['economic_unit'])) {
                    throw new \DomainException('Seuls les vaisseaux mobiles non plafonnés peuvent être livrés.');
                }
                $orders[$slug][$tier] = $quantity;
                $units[] = array_replace($ship, ['slug' => $slug, 'tier' => $tier, 'quantity' => $quantity]);
                $remaining -= $quantity;
            }
        }
        if ($units === []) {
            throw new \DomainException('Choisissez au moins un vaisseau.');
        }

        // Le départ appartient à l’Atelier, pas à l’empire du gestionnaire :
        // utiliser le catalogue d’univers sans prélever un bonus personnel.
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $route = $this->flight->quoteDelivery($hall, $destination, $units, $ratio, $config['multipliers']['travel_time'], $this->catalog->rules()['fuel_ratio_policy'], $now);
        $quote = $route['movement'];
        if ($quote['total_fuel'] > $quote['capacity']) {
            throw new \DomainException('La soute ne peut pas contenir le carburant aller-retour.');
        }
        $this->ships->assertReservableLocked($hall, $orders);
        $this->materials->assertFuelAvailableLocked($hall, $quote['total_fuel']);

        return new GuildAtelierDeliveryPlan($destination, $orders, $quote['seconds'], $quote['total_fuel'], $ratio, $route['navigation'], $now);
    }
}
