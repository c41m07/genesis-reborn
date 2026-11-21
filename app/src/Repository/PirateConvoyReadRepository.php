<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{PirateConvoyDelivery, PlayerUniverse};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\{EntityManagerInterface, Query};

/** Lecture bornée après validation du dossier ; aucun chargement par ID fourni par la requête. */
final readonly class PirateConvoyReadRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param list<string> $visibleKeys Clés du catalogue déjà consultables dans le dossier validé.
     * @return list<array{right:PirateConvoyDelivery,missionKey:string,victoryAt:\DateTimeImmutable}>
     */
    public function forOwnedDossier(PlayerUniverse $viewer, array $visibleKeys, \DateTimeImmutable $at): array
    {
        if ($visibleKeys === [] || ($viewer->id ?? 0) < 1 || ($viewer->player?->id ?? 0) < 1
            || ($viewer->universe->id ?? 0) < 1) {
            return [];
        }

        $rows = $this->queryForOwnedDossier($viewer, $visibleKeys, $at)->getResult();
        $rights = [];
        foreach ($rows as $row) {
            if (($row[0] ?? null) instanceof PirateConvoyDelivery && is_string($row['missionKey'] ?? null)
                && ($row['victoryAt'] ?? null) instanceof \DateTimeImmutable) {
                $rights[] = ['right' => $row[0], 'missionKey' => $row['missionKey'], 'victoryAt' => $row['victoryAt']];
            }
        }

        return $rights;
    }

    /** @param list<string> $visibleKeys */
    private function queryForOwnedDossier(PlayerUniverse $viewer, array $visibleKeys, \DateTimeImmutable $at): Query
    {
        // Vérifier l'identité durable du droit ET celle de la mission avant d'hydrater les détails.
        // Ne pas joindre Planet : aucun nom de destination n'est exposé, même après une cession.
        return $this->em->createQueryBuilder()
            ->select('cargoRight', 'scout.missionKey AS missionKey', 'cargoRight.victoryAt AS victoryAt')
            ->from(PirateConvoyDelivery::class, 'cargoRight')
            ->join('cargoRight.attack', 'battle')->join('battle.reconnaissance', 'scout')
            ->join('scout.membership', 'seat')->join('seat.player', 'owner')->join('seat.universe', 'realm')
            ->join('scout.system', 'system')->join('system.galaxy', 'galaxy')
            ->andWhere('seat.id = :member AND owner.id = :account AND realm.id = :universe')
            ->andWhere('cargoRight.membershipId = seat.id AND cargoRight.ownerId = owner.id AND cargoRight.universeId = realm.id')
            ->andWhere('IDENTITY(scout.owner) = owner.id AND IDENTITY(galaxy.universe) = realm.id')
            ->andWhere('seat.forgottenAt IS NULL AND IDENTITY(owner.currentPlayerUniverse) = seat.id AND owner.lockedAt IS NULL')
            ->andWhere('owner.emailVerifiedAt <= :now AND realm.status = :status')
            ->andWhere('realm.startsAt IS NULL OR realm.startsAt <= :now')
            ->andWhere('realm.endsAt IS NULL OR realm.endsAt > :now')
            ->andWhere('scout.missionKey IN (:visible) AND cargoRight.victoryAt <= :now')
            ->setParameter('member', $viewer->id)
            ->setParameter('account', $viewer->player->id)
            ->setParameter('universe', $viewer->universe->id)
            ->setParameter('status', 'active')
            ->setParameter('visible', $visibleKeys)
            ->setParameter('now', $at, Types::DATETIME_IMMUTABLE)
            ->setMaxResults(count($visibleKeys))
            ->getQuery()->setHint(Query::HINT_REFRESH, true);
    }
}
