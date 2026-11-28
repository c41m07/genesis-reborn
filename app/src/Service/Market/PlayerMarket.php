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
    public static function listingTerms(int $quantity, string $price): array
    {
        if ($quantity < 1 || $quantity > MarketPricing::MAX_LOT) {
            throw new \DomainException('Quantité de lot invalide.');
        }
        $unit = self::parsePrice($price);
        $gross = $unit * $quantity;
        return ['quantity' => $quantity, 'unit' => $unit, 'gross' => $gross,
            'fee' => intdiv($gross * MarketPricing::FEE_PERCENT + 99, 100)];
    }

    public function order(PlayerUniverse $member, string $key, string $action, string $resource, int $quantity, string $price, int $id): void
    {
        $this->manager->transaction(function () use ($member, $key, $action, $resource, $quantity, $price, $id): void {
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
                $terms = self::listingTerms($quantity, $price);
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
            if ($action === 'cancel' && $offer->seller->id === $member->id) {
                $account->moveGoods($resource, $offer->remaining);
                $this->manager->remove($offer);
                return;
            }
            if ($action !== 'purchase' || $offer->seller->id === $member->id || !$offer->seller->isPlayerControlled() || $quantity > $offer->remaining) {
                throw new \DomainException('Cet achat est indisponible.');
            }
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
