<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{PirateAttackCampaign, PirateReconnaissance, PlayerUniverse};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\{EntityManagerInterface, Query, QueryBuilder};

/** Source privée du dossier. Aucune mutation ou sélection de cible. */
final readonly class PirateCampaignReadRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function activeMembership(int $accountId, int $membershipId, \DateTimeImmutable $at): ?PlayerUniverse
    {
        $query = $this->em->createQueryBuilder()->select('seat', 'realm')
            ->from(PlayerUniverse::class, 'seat');

        return $this->scope($query, $accountId, $membershipId, $at)
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
    }

    /**
     * @param list<string> $missionKeys Clés canoniques fournies par le catalogue serveur.
     * @return list<array{reconnaissance:PirateReconnaissance, attack:?PirateAttackCampaign}>
     */
    public function ownedRows(PlayerUniverse $viewer, array $missionKeys, \DateTimeImmutable $at): array
    {
        $accountId = $viewer->player?->id;
        $membershipId = $viewer->id;
        if ($missionKeys === [] || $accountId === null || $membershipId === null) {
            return [];
        }

        // La jointure de lecture prépare l'univers et l'ancre privés en un lot, pas une requête par mission.
        $recons = $this->scope($this->em->createQueryBuilder()->select('mission', 'system', 'galaxy')
            ->from(PirateReconnaissance::class, 'mission')
            ->join('mission.membership', 'seat')->join('mission.system', 'system')->join('system.galaxy', 'galaxy'),
            $accountId, $membershipId, $at)
            ->andWhere('IDENTITY(mission.owner) = scopeOwner.id AND IDENTITY(galaxy.universe) = realm.id AND realm.id = :viewUniverse')
            ->andWhere('mission.missionKey IN (:missionKeys)')
            ->setParameter('viewUniverse', $viewer->universe->id)
            ->setParameter('missionKeys', $missionKeys)
            ->setMaxResults(count($missionKeys))
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();
        if ($recons === []) {
            return [];
        }

        // La contrainte d'unicité garantit une seule tentative courante par reconnaissance. On repasse
        // volontairement par la portée propriétaire plutôt que de faire confiance aux seuls IDs.
        $attacks = $this->scope($this->em->createQueryBuilder()->select('attack')
            ->from(PirateAttackCampaign::class, 'attack')
            ->join('attack.reconnaissance', 'mission')->join('mission.membership', 'seat'),
            $accountId, $membershipId, $at)
            ->andWhere('IDENTITY(mission.owner) = scopeOwner.id AND realm.id = :viewUniverse')
            ->andWhere('mission.id IN (:reconIds)')
            ->setParameter('viewUniverse', $viewer->universe->id)
            ->setParameter('reconIds', array_map(static fn (PirateReconnaissance $recon): int => $recon->id, $recons))
            ->setMaxResults(count($recons))
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();
        $byRecon = [];
        foreach ($attacks as $attack) {
            $byRecon[$attack->reconnaissance->id] = $attack;
        }

        return array_map(static fn (PirateReconnaissance $recon): array => [
            'reconnaissance' => $recon,
            'attack' => $byRecon[$recon->id] ?? null,
        ], $recons);
    }

    private function scope(QueryBuilder $query, int $accountId, int $membershipId, \DateTimeImmutable $at): QueryBuilder
    {
        // La sélection active est relue en base : un contexte d'authentification périmé ne suffit pas.
        return $query->join('seat.player', 'scopeOwner')->join('seat.universe', 'realm')
            ->andWhere('seat.id = :memberId AND scopeOwner.id = :ownerId AND seat.forgottenAt IS NULL')
            ->andWhere('IDENTITY(scopeOwner.currentPlayerUniverse) = :memberId AND scopeOwner.lockedAt IS NULL')
            ->andWhere('realm.status = :active')
            ->andWhere('realm.startsAt IS NULL OR realm.startsAt <= :now')
            ->andWhere('realm.endsAt IS NULL OR realm.endsAt > :now')
            ->setParameter('memberId', $membershipId)
            ->setParameter('ownerId', $accountId)
            ->setParameter('active', 'active')
            ->setParameter('now', $at, Types::DATETIME_IMMUTABLE);
    }
}
