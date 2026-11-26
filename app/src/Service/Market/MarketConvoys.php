<?php

declare(strict_types=1);

namespace App\Service\Market;

use App\Entity\{Fleet, FleetShip, MarketTrade, OutboxMessage, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Message\MarketConvoyDue;
use App\Repository\{GameRepository, ResourceMarketRepository};
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Player\VacationCalendar;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final readonly class MarketConvoys
{
    public function __construct(
        private GameRepository $repo,
        private ResourceMarketRepository $markets,
        private GameManager $manager,
        private ActionGuard $guard,
        private ResourceService $resources,
        private MarketTerms $terms,
        private VacationCalendar $vacations,
        private ClockInterface $clock,
    ) {
    }

    public function preview(PlayerUniverse $member, Planet $planet, array $input): array
    {
        $this->vacations->assertCanSendFleet($member);
        $market = $this->markets->find($member->universe) ?? throw new \DomainException('La station n’est pas ouverte.');
        return $this->calculate($member, $planet, $market, $input);
    }

    /** Les montants proviennent du devis conservé par le serveur, jamais d’un champ de confirmation. */
    public function depart(PlayerUniverse $member, Planet $planet, array $quote, string $key): void
    {
        $this->manager->transaction(function () use ($member, $planet, $quote, $key): void {
            // Ordre commun avec la gestion économique : participation, univers, planète.
            // L’univers protège aussi toutes les réservations réparties entre les convois.
            $this->guard->lock($member, $planet, currentReads: true);
            $this->vacations->assertCanSendFleet($member);
            if (!$this->guard->receipt($member, $key)) {
                return;
            }
            $market = $this->markets->find($member->universe) ?? throw new \DomainException('La station n’est pas ouverte.');
            $this->manager->lockCurrent($market);
            $idle = $this->terms->idle($member, $planet) ?? throw new \DomainException('Les cargos ne sont plus disponibles.');
            $this->manager->lockCurrent($idle);
            $current = $this->calculate($member, $planet, $market, $quote);
            if ($current !== $quote) {
                throw new \DomainException('Le devis a changé. Vérifiez un nouveau devis avant de partir.');
            }

            $cost = [$quote['pay'] => $quote['quantity']];
            $cost['hydrogen'] = ($cost['hydrogen'] ?? 0) + $quote['planetFuel'];
            $this->resources->consume($planet, $cost, lockStocks: true);
            $idle->storedFuel -= $quote['storedFuel'];
            $stock = $this->repo->one(FleetShip::class, ['fleet' => $idle, 'slug' => 'transport', 'tier' => $quote['rank']]);
            $stock->quantity -= $quote['cargos'];

            $trade = new MarketTrade();
            $trade->universe = $member->universe;
            $trade->membershipId = $member->id;
            $trade->originId = $trade->returnPlanetId = $planet->id;
            $trade->requestKey = $key;
            $trade->payResource = $quote['pay'];
            $trade->payQuantity = $quote['quantity'];
            $trade->receiveResource = $quote['receive'];
            $trade->receiveQuantity = $quote['received'];
            $trade->ships = [$quote['rank'] => $quote['cargos']];
            $trade->oneWayFuel = $quote['oneWayFuel'];
            $trade->reservedFuel = $trade->oneWayFuel * 2;
            $trade->departedAt = $this->now();
            $this->manager->save($trade);
            $this->manager->flush();
            $this->schedule($trade, $trade->departedAt, MarketTerms::LEG_SECONDS);
        });
    }

    public function recall(PlayerUniverse $member, int $id): void
    {
        $this->manager->transaction(function () use ($member, $id): void {
            $this->guard->lockMembership($member, currentReads: true);
            $trade = $this->ownedTrade($member, $id);
            $now = $this->now();
            if ($trade->status !== 'outbound' || !$trade->dueAt || $trade->dueAt <= $now) {
                throw new \DomainException('Le rappel est possible avant l’arrivée à la station seulement.');
            }
            $elapsed = min(MarketTerms::LEG_SECONDS, max(1, $now->getTimestamp() - $trade->departedAt->getTimestamp()));
            $trade->recalled = true;
            // Le statut libère la réservation station immédiatement ; le fret initial revient en soute.
            $trade->status = 'returning';
            $trade->reservedFuel -= $trade->oneWayFuel * $elapsed / MarketTerms::LEG_SECONDS;
            $this->schedule($trade, $now, $elapsed);
        });
    }

    public function reroute(PlayerUniverse $member, int $id, Planet $planet): void
    {
        $this->manager->transaction(function () use ($member, $id, $planet): void {
            $this->guard->lock($member, $planet, currentReads: true);
            $trade = $this->ownedTrade($member, $id);
            if ($trade->status !== 'awaiting_colony') {
                throw new \DomainException('Ce convoi n’attend pas de nouvelle destination.');
            }
            $trade->returnPlanetId = $planet->id;
            $trade->status = 'returning';
            $this->schedule($trade, $this->now(), MarketTerms::LEG_SECONDS);
        });
    }

    /** @return ?int Délai de nouvelle livraison si Messenger est en avance. */
    public function processDue(int $id, string $token): ?int
    {
        // Résoudre des identifiants hors transaction. Les lectures et décisions dans
        // la transaction sont réactualisées sous verrou avant le premier mouvement.
        $trade = $this->repo->find(MarketTrade::class, $id);
        if ($trade === null) {
            return null;
        }
        $member = $this->repo->find(PlayerUniverse::class, $trade->membershipId);
        return $this->manager->transaction(function () use ($trade, $member, $token): ?int {
            if ($member) {
                $this->manager->lockCurrent($member);
            }
            $this->manager->lockCurrent($trade->universe);
            $this->manager->lockCurrent($trade);
            if ($trade->token !== $token || !$trade->dueAt
                || !in_array($trade->status, ['outbound', 'returning'], true)) {
                return null;
            }
            $now = $this->now();
            if ($now < $trade->dueAt) {
                return max(1000, ($trade->dueAt->getTimestamp() - $now->getTimestamp()) * 1000);
            }
            if (!$member || $member->universe->id !== $trade->universe->id) {
                // Une suppression de compte ne peut créditer une autre participation.
                // L’historique des échanges réellement exécutés reste conservé.
                $this->finish($trade, 'abandoned');
                return null;
            }
            if ($trade->status === 'outbound') {
                $market = $this->markets->find($trade->universe) ?? throw new \LogicException('Station historique introuvable.');
                $this->manager->lockCurrent($market);
                if (($market->stock[$trade->receiveResource] ?? 0) < $trade->receiveQuantity) {
                    throw new \LogicException('Le stock réservé de la station est incohérent.');
                }
                $market->stock[$trade->payResource] = ($market->stock[$trade->payResource] ?? 0) + $trade->payQuantity;
                $market->stock[$trade->receiveResource] -= $trade->receiveQuantity;
                $market->pressure[$trade->payResource] = ($market->pressure[$trade->payResource] ?? 0) - $trade->payQuantity;
                $market->pressure[$trade->receiveResource] = ($market->pressure[$trade->receiveResource] ?? 0) + $trade->receiveQuantity;
                // Seul ce reçu participe aux volumes du vendredi. Aucun crédit
                // à la planète avant la deuxième arrivée réelle.
                $trade->exchangedAt = $now;
                $trade->reservedFuel -= $trade->oneWayFuel;
                $trade->status = 'returning';
                $this->schedule($trade, $now, MarketTerms::LEG_SECONDS);
                return null;
            }

            $planet = $this->repo->find(Planet::class, $trade->returnPlanetId);
            if ($planet !== null) {
                $this->manager->lockCurrent($planet);
            }
            if (!$planet || $planet->owner?->id !== $member->id || $planet->solarSystem->galaxy->universe->id !== $trade->universe->id) {
                $this->finish($trade, 'awaiting_colony');
                return null;
            }
            $this->land($trade, $member, $planet);
            return null;
        });
    }

    private function calculate(PlayerUniverse $member, Planet $planet, \App\Entity\ResourceMarket $market, array $input): array
    {
        return $this->terms->quote($member, $planet, $market, $this->markets->reserved($member->universe),
            $input['pay'], $input['receive'], $input['quantity'], $input['rank'], $input['cargos']);
    }

    private function ownedTrade(PlayerUniverse $member, int $id): MarketTrade
    {
        $trade = $this->repo->find(MarketTrade::class, $id);
        if (!$trade || $trade->membershipId !== $member->id || $trade->universe->id !== $member->universe->id) {
            throw new \DomainException('Convoi inaccessible.');
        }
        $this->manager->lockCurrent($trade);

        return $trade;
    }

    private function schedule(MarketTrade $trade, \DateTimeImmutable $from, int $seconds): void
    {
        $trade->dueAt = $from->modify('+'.$seconds.' seconds');
        $trade->token = Uuid::v4()->toRfc4122();
        $message = new OutboxMessage();
        $message->kind = MarketConvoyDue::KIND;
        $message->targetId = $trade->id;
        $message->token = $trade->token;
        $message->dueAt = $trade->dueAt;
        $this->manager->save($message);
    }

    private function finish(MarketTrade $trade, string $status): void
    {
        $trade->status = $status;
        $trade->dueAt = null;
        $trade->token = '';
    }

    private function land(MarketTrade $trade, PlayerUniverse $member, Planet $planet): void
    {
        $resource = $trade->recalled ? $trade->payResource : $trade->receiveResource;
        $quantity = $trade->recalled ? $trade->payQuantity : $trade->receiveQuantity;
        $deposit = $this->resources->deposit($planet, [$resource => $quantity]);
        $idle = $this->terms->idle($member, $planet);
        if (!$idle) {
            $idle = new Fleet();
            $idle->homePlanet = $planet;
            $idle->playerUniverse = $member;
            $this->manager->save($idle);
            $this->manager->flush();
        } else {
            $this->manager->lockCurrent($idle);
        }
        $stock = $this->repo->all(FleetShip::class, ['fleet' => $idle, 'slug' => 'transport']);
        $byRank = [];
        foreach ($stock as $ship) {
            $byRank[$ship->tier] = $ship;
        }
        foreach ($trade->ships as $rank => $amount) {
            $ship = $byRank[$rank] ?? new FleetShip();
            $ship->fleet = $idle;
            $ship->slug = 'transport';
            $ship->tier = $rank;
            $ship->quantity += $amount;
            $this->manager->save($ship);
        }
        if (($overflow = $deposit['overflow'][$resource] ?? 0) > 0) {
            $idle->cargo[$resource] = ($idle->cargo[$resource] ?? 0) + $overflow;
        }
        if ($trade->recalled) {
            $usedOutbound = 2 * $trade->oneWayFuel - $trade->reservedFuel;
            $idle->storedFuel += max(0.0, $trade->reservedFuel - $usedOutbound);
        }
        $trade->reservedFuel = 0;
        $this->finish($trade, 'completed');
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
