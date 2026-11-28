<?php

declare(strict_types=1);

namespace App\Service\Market;

use App\Entity\{MarketAccount, MarketOffer, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\ResourceMarketRepository;
use App\Service\Colony\ActionGuard;
use App\Service\Player\VacationCalendar;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PlayerMarket
{
    public function __construct(
        private EntityManagerInterface $em,
        private GameManager $manager,
        private ResourceMarketRepository $markets,
        private ActionGuard $guard,
        private VacationCalendar $vacations,
        private \Psr\Clock\ClockInterface $clock,
    ) {}

    public function account(PlayerUniverse $member): ?MarketAccount
    {
        return $this->em->find(MarketAccount::class, $member->id);
    }

    /** Toutes les écritures des comptes et des offres passent par le verrou d’univers. */
    public function accountLocked(PlayerUniverse $member): MarketAccount
    {
        $account = $this->account($member);
        if ($account !== null) {
            $this->manager->lockCurrent($account);
            return $account;
        }
        $account = new MarketAccount();
        $account->membership = $member;
        $this->manager->save($account);
        return $account;
    }

    /** Les offres et les convois déjà engagés réservent aussi leur place. */
    public function assertRoom(PlayerUniverse $member,int $quantity): void
    {
        $limit = StationPolicy::read($member->universe)['capacity'];
        if (!$limit) return;
        $db = $this->em->getConnection();
        $offers = $db->fetchOne('SELECT COALESCE(SUM(remaining),0) FROM market_offer WHERE seller_id=? AND universe_id=?',[$member->id,$member->universe->id]);
        $convoys = $db->fetchOne("SELECT COALESCE(SUM(CASE WHEN operation='deposit' THEN pay_quantity ELSE receive_quantity END),0) FROM market_trade WHERE membership_id=? AND universe_id=? AND status='outbound' AND operation IN ('deposit','collect')",[$member->id,$member->universe->id]);
        if (array_sum($this->account($member)?->goods ?? []) + $offers + $convoys + $quantity > $limit) throw new \DomainException('Capacité du dépôt de la station insuffisante.');
    }

    public static function parsePrice(string $price): int
    {
        if (!preg_match('/^(\d{1,8})(?:[.,](\d{1,2}))?$/D', $price, $match)) {
            throw new \DomainException('Indiquez un prix positif avec deux décimales au maximum.');
        }
        $cents = 100 * (int) $match[1] + (int) str_pad($match[2] ?? '', 2, '0');
        if ($cents < 1 || $cents > 2147483647) {
            throw new \DomainException('Le prix doit être compris entre 0,01 et 21 474 836,47 crédits.');
        }
        return $cents;
    }

    /** Le dépôt de l’offre détruit la taxe sur la valeur totale, même si le lot est ensuite retiré. */
    public static function listingTerms(int $quantity, string $price, int $fee = MarketPricing::FEE_PERCENT): array
    {
        if ($quantity < 1 || $quantity > MarketPricing::MAX_LOT) {
            throw new \DomainException('Quantité de lot invalide.');
        }
        $unit = self::parsePrice($price);
        $gross = $unit * $quantity;
        return ['quantity' => $quantity, 'unit' => $unit, 'gross' => $gross,
            'fee' => intdiv($gross * $fee + 99, 100)];
    }

    public function order(PlayerUniverse $member, string $key, string $action, string $resource, int $quantity, string $price, int $id, ?array $draft = null): void
    {
        $this->manager->transaction(function () use ($member, $key, $action, $resource, $quantity, $price, $id, $draft): void {
            $this->guard->lockMembership($member, currentReads: true);
            $this->vacations->assertCanSendFleet($member);
            $market = $this->markets->find($member->universe) ?? throw new \DomainException('Station inaccessible.');
            $this->manager->lockCurrent($market);
            if (!$market->enabled || !isset(MarketPricing::WEIGHTS[$resource])) {
                throw new \DomainException('Marché indisponible.');
            }
            if (!$this->guard->receipt($member, $key)) {
                return;
            }
            $account = $this->accountLocked($member);
            if ($action !== 'cancel' && ($quantity < 1 || $quantity > MarketPricing::MAX_LOT)) {
                throw new \DomainException('Quantité de lot invalide.');
            }
            if ($action === 'list') {
                $offer = new MarketOffer();
                $offer->seller = $member;
                $offer->universe = $member->universe;
                $offer->resource = $resource;
                $offer->remaining = $quantity;
                $policy = StationPolicy::read($member->universe);
                $terms = self::listingTerms($quantity, $price,$policy['fee']);
                if ($draft !== null && ($draft['fee'] !== $terms['fee'] || ($draft['days'] ?? null) !== $policy['days'])) throw new \DomainException('Les conditions ont changé. Recalculez le devis.');
                $planet = $this->em->getRepository(\App\Entity\Planet::class)->findOneBy($draft === null ? ['owner'=>$member] : ['id'=>$draft['destination'],'owner'=>$member],['id'=>'ASC']);
                if (!$planet || $planet->solarSystem->galaxy->universe->id !== $member->universe->id) throw new \DomainException('Colonie de retour indisponible.');
                $offer->returnPlanetId = $planet->id;
                $offer->expiresAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify('+'.$policy['days'].' days');
                $offer->unitCents = $terms['unit'];
                $account->moveCredits(-$terms['fee']);
                $account->moveGoods($resource, -$quantity);
                $this->manager->save($offer);
                return;
            }

            $offer = $this->em->getRepository(MarketOffer::class)->findOneBy(['id' => $id, 'universe' => $member->universe, 'resource' => $resource]);
            if (!$offer || $offer->seller->universe->id !== $member->universe->id) {
                throw new \DomainException('Offre indisponible.');
            }
            $this->manager->lockCurrent($offer);
            if ($offer->expiresAt && $offer->expiresAt <= $this->clock->now()) throw new \DomainException('Cette offre a expiré.');
            if ($action === 'cancel' && $offer->seller->id === $member->id) {
                $account->moveGoods($resource, $offer->remaining);
                $this->manager->remove($offer);
                return;
            }
            if ($action !== 'purchase' || $offer->seller->id === $member->id || !$offer->seller->isPlayerControlled() || $quantity > $offer->remaining) {
                throw new \DomainException('Cet achat est indisponible.');
            }
            $this->assertRoom($member,$quantity);
            $seller = $this->accountLocked($offer->seller);
            $gross = $offer->unitCents * $quantity;
            $account->moveCredits(-$gross);
            $account->moveGoods($resource, $quantity);
            $seller->moveCredits($gross);
            $offer->remaining -= $quantity;
            $market->lastPlayerPrices[$resource] = $offer->unitCents;
            if ($offer->remaining === 0) {
                $this->manager->remove($offer);
            }
        });
    }
}
