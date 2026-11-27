<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\{AdventBinding, AdventClaim, AdventEdition, Planet, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Player\ProfileCosmetics;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class AdventRewards
{
    public function __construct(
        private AdventCalendar $calendar,
        private \App\Repository\AdventWindowRepository $windows,
        private ActionGuard $guard,
        private ResourceService $resources,
        private GameManager $manager,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private AdventAllowance $allowance,
        private AdventShipGrants $ships,
        private ProfileCosmetics $cosmetics,
    ) {}

    /** @return array<int, AdventClaim> */
    public function claims(Player $player, AdventEdition $edition): array
    {
        $claims = [];
        foreach ($this->em->getRepository(AdventClaim::class)->findBy(['player' => $player, 'adventEdition' => $edition]) as $claim) {
            $claims[$claim->door] = $claim;
        }

        return $claims;
    }

    /** Droits privés déjà acquis, y compris ceux des éditions historiques sans liaison. */
    public function pending(PlayerUniverse $membership): array
    {
        return $this->em->createQuery('SELECT c, e FROM '.AdventClaim::class.' c JOIN c.adventEdition e
            WHERE c.player = :player AND c.deliveredAt IS NULL AND c.boxExpiresAt IS NULL AND (e.plan = :custom OR EXISTS (
                SELECT b.id FROM '.AdventBinding::class.' b WHERE b.player = c.player
                AND b.adventEdition = e AND b.universeId = :universe))
            ORDER BY e.season DESC, c.door')
            ->setParameter('player', $membership->player)->setParameter('custom', 'custom')
            ->setParameter('universe', $membership->universe->id)->getResult();
    }

    /** @return list<AdventClaim> */
    public function boxes(PlayerUniverse $membership): array
    {
        return $this->availableBoxesQuery($membership)
            ->select('c, e')->join('c.adventEdition', 'e')
            ->orderBy('c.boxExpiresAt', 'ASC')->addOrderBy('c.door', 'ASC')
            ->getQuery()->getResult();
    }

    public function boxCount(PlayerUniverse $membership): int
    {
        return (int) $this->availableBoxesQuery($membership)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
    }

    /** @return array{count:int,through:?int} */
    public function giftNoticeSummary(PlayerUniverse $membership): array
    {
        $result = $this->availableBoxesQuery($membership)->andWhere('c.boxUnread = true')
            ->select('COUNT(c.id) AS total, MAX(c.id) AS newest')->getQuery()->getSingleResult();

        return ['count' => (int) $result['total'], 'through' => $result['newest'] === null ? null : (int) $result['newest']];
    }

    public function acknowledgeGiftNotices(PlayerUniverse $membership, int $through, bool $single = false): void
    {
        // Une nouvelle boîte créditée pendant la consultation doit garder son indicateur.
        $this->em->createQuery('UPDATE '.AdventClaim::class.' c SET c.boxUnread = false
            WHERE c.player = :player AND c.boxUniverseId = :universe AND c.boxUnread = true
            AND c.deliveredAt IS NULL AND c.boxExpiresAt > :now AND c.id '.($single ? '=' : '<=').' :through')
            ->setParameter('player', $membership->player)->setParameter('universe', $membership->universe->id)
            ->setParameter('now', $this->clock->now()->setTimezone(new \DateTimeZone('UTC')))
            ->setParameter('through', $through)->execute();
    }

    /** @param list<int> $ids */
    public function acknowledgeDisplayedBoxes(PlayerUniverse $membership, array $ids): void
    {
        $this->availableBoxesQuery($membership)->update()->set('c.boxUnread', 'false')
            ->andWhere('c.boxUnread = true AND c.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()->execute();
    }

    private function availableBoxesQuery(PlayerUniverse $membership): \Doctrine\ORM\QueryBuilder
    {
        return $this->em->createQueryBuilder()->from(AdventClaim::class, 'c')
            ->where('c.player = :player AND c.deliveredAt IS NULL AND c.boxUniverseId = :universe AND c.boxExpiresAt > :now')
            ->setParameter('player', $membership->player)
            ->setParameter('universe', $membership->universe->id)
            ->setParameter('now', $this->clock->now()->setTimezone(new \DateTimeZone('UTC')));
    }

    /** @return array{state: string, missing?: array<string, int>} */
    public function open(Player $player, PlayerUniverse $membership, Planet $planet, AdventEdition $edition, int $door, int $choice, bool $reservationOnly = false): array
    {
        return $this->manager->transaction(function () use ($player, $membership, $planet, $edition, $door, $choice, $reservationOnly): array {
            $this->guard->lock($membership, $planet, currentReads: true);
            if ($membership->player?->id !== $player->id) {
                return ['state' => 'stale'];
            }

            // Même ordre que la récompense quotidienne : après la participation
            // et la planète, ne pas attendre un verrou du compte déjà détenu
            // par un changement d’univers ou une suppression de compte.
            try {
                $account = $this->em->getConnection()->fetchAssociative(
                    'SELECT current_player_universe_id, locked_at, email_verified_at FROM player WHERE id = ? FOR UPDATE NOWAIT',
                    [$player->id],
                );
            } catch (LockWaitTimeoutException) {
                return ['state' => 'busy'];
            }
            if (!$account || $account['locked_at'] !== null || $account['email_verified_at'] === null
                || (int) $account['current_player_universe_id'] !== $membership->id) {
                return ['state' => 'stale'];
            }

            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            // Une requête du calendrier peut avoir attendu le verrou jusqu’à la fermeture.
            // Seul le retrait d’un reçu existant dans l’inventaire reste alors permis.
            if (!$reservationOnly && !$this->windows->isVisible($membership->universe, $edition->id)) {
                return ['state' => 'unavailable'];
            }
            $binding = null;
            if ($edition->plan === 'consolidated') {
                $binding = $this->em->getConnection()->fetchAssociative(
                    'SELECT universe_id, bracket, joined_at FROM advent_binding WHERE player_id = ? AND advent_edition_id = ? FOR UPDATE',
                    [$player->id, $edition->id],
                );
                if ($binding === false || (int) $binding['universe_id'] !== $membership->universe->id) {
                    return ['state' => 'wrong_universe'];
                }
            }

            // Une lecture courante du droit voit la décision d’un autre onglet
            // même si une lecture précédente avait ouvert un instantané SQL.
            $existing = $this->em->getConnection()->fetchAssociative(
                'SELECT id, delivered_at, credited_resources FROM advent_claim WHERE player_id = ? AND advent_edition_id = ? AND door = ? FOR UPDATE',
                [$player->id, $edition->id, $door],
            );
            if ($existing !== false && $existing['delivered_at'] !== null) {
                return ['state' => 'already'];
            }
            $claim = $existing === false ? null : $this->em->find(AdventClaim::class, (int) $existing['id']);
            if ($claim !== null && $claim->choice !== $choice) {
                return ['state' => 'reserved'];
            }
            if ($claim !== null && $claim->boxExpiresAt !== null) {
                if ($claim->boxUniverseId !== $membership->universe->id) {
                    return ['state' => 'wrong_universe'];
                }
                if ($now >= $claim->boxExpiresAt) {
                    return ['state' => 'box_expired'];
                }
                if (!$reservationOnly) {
                    return ['state' => 'box_ready'];
                }
                // Un autre onglet peut avoir versé une partie du contenu avant
                // l’obtention de nos verrous : reprendre le reliquat courant.
                $claim->creditedResources = $existing['credited_resources'] === null
                    ? null : json_decode($existing['credited_resources'], true, 512, JSON_THROW_ON_ERROR);
            }
            if ($claim === null) {
                $definition = $edition->doors[$door] ?? null;
                // Après la clôture, les portes non ouvertes expirent ; aucun
                // nouveau droit n’est recréé sur la base d’une ancienne page.
                $options = $binding === null ? ($definition['options'] ?? []) : ($definition['options'][$binding['bracket']] ?? []);
                if ($reservationOnly || $definition === null || !isset($options[$choice])
                    || !$this->calendar->canOpen($now, new \DateTimeImmutable($definition['opens_at']), $edition->closesAt)) {
                    return ['state' => 'unavailable'];
                }
                $claim = new AdventClaim();
                $claim->adventEdition = $edition;
                $claim->player = $player;
                $claim->originalDestinationId = $planet->id;
                $claim->door = $door;
                $claim->choice = $choice;
                $claim->award = $binding === null ? null : $options[$choice];
                $claim->resources = $binding === null ? $options[$choice] : ($claim->award['resources'] ?? []);
                $claim->openedAt = $now;
                if (in_array($claim->award['kind'] ?? 'resources', ['resources', 'ship_pack'], true)) {
                    $expires = $this->calendar->boxExpiry($edition->season);
                    if ($now >= $expires) {
                        return ['state' => 'box_expired'];
                    }
                    $claim->boxExpiresAt = $expires;
                    $claim->boxUniverseId = $membership->universe->id;
                    $claim->boxUnread = true;
                }
                $this->manager->save($claim);
                if ($binding !== null && $door === 24) {
                    // Les deux cosmétiques sont acquis à l’ouverture même si
                    // les coques restent réservées ou si un bon attend d’être utilisé.
                    $this->cosmetics->grantLocked($player, $definition['cosmetics'] ?? [], $now);
                }
            }

            $kind = $claim->award['kind'] ?? 'resources';
            if ($kind === 'research_discount' || $kind === 'ship_voucher') {
                return ['state' => 'credit_ready'];
            }
            if ($claim->boxExpiresAt !== null && !$reservationOnly) {
                // L’ouverture de la porte crée le droit, pas le crédit sur une colonie.
                return ['state' => 'box_ready'];
            }
            if ($kind === 'ship_pack') {
                if (!$this->ships->deliverLocked($membership, $planet, $claim->award['units'], $now)) {
                    return ['state' => 'ships_reserved'];
                }
                $claim->deliveredDestinationId = $planet->id;
                $claim->deliveredAt = $now;

                return ['state' => 'delivered'];
            }

            $portion = $claim->resources;
            if ($binding !== null) {
                $credited = $claim->creditedResources ?? [];
                $remaining = [];
                foreach ($claim->resources as $resource => $quantity) {
                    $remaining[$resource] = $quantity - ($credited[$resource] ?? 0);
                }
                $joined = new \DateTimeImmutable($binding['joined_at'], new \DateTimeZone('UTC'));
                $prior = [];
                if ($now->getTimestamp() - $joined->getTimestamp() < AdventSeasonPlan::FIRST_WALLET_SECONDS) {
                    foreach ($this->em->getConnection()->fetchFirstColumn(
                        'SELECT credited_resources FROM advent_claim WHERE player_id = ? AND advent_edition_id = ? AND credited_resources IS NOT NULL FOR UPDATE',
                        [$player->id, $edition->id],
                    ) as $json) {
                        foreach (json_decode($json, true, 512, JSON_THROW_ON_ERROR) as $resource => $quantity) {
                            $prior[$resource] = ($prior[$resource] ?? 0) + $quantity;
                        }
                    }
                }
                $portion = $this->allowance->resourcePortion($remaining, $prior, $joined, $now);
                if ($portion === []) {
                    return ['state' => 'early_cap'];
                }
            }
            if ($claim->boxExpiresAt !== null) {
                $this->resources->creditAdventBox($planet, $portion, $now);
                // Même un premier versement partiel correspond à une ouverture de la boîte.
                $claim->boxUnread = false;
            } else {
                // Les anciens reçus gardent leur délai et leurs règles de dépôt.
                $deposit = $this->resources->deposit($planet, $portion, allOrNothing: true);
                $missing = [];
                foreach ($deposit['overflow'] as $resource => $amount) {
                    if ($amount > 0.0) {
                        $missing[$resource] = (int) ceil($amount);
                    }
                }
                if ($missing !== []) {
                    return ['state' => 'full', 'missing' => $missing];
                }
            }

            if ($binding !== null) {
                $credited = $claim->creditedResources ?? [];
                foreach ($portion as $resource => $quantity) {
                    $credited[$resource] = ($credited[$resource] ?? 0) + $quantity;
                }
                $claim->creditedResources = $credited;
                if (array_diff_assoc($claim->resources, $credited) !== []) {
                    return ['state' => 'partial'];
                }
            }
            $claim->deliveredDestinationId = $planet->id;
            $claim->deliveredAt = $now;

            return ['state' => 'delivered'];
        });
    }
}
