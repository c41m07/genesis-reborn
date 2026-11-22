<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\EspionageReport;
use App\Entity\Fleet;
use App\Entity\Planet;
use App\Entity\PlayerDiscovery;
use App\Entity\PlayerUniverse;
use App\Entity\QueueItem;
use App\Manager\GameManager;
use App\Service\Colony\ActionGuard;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class FirstSteps
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameManager $manager,
        private ActionGuard $guard,
        private ClockInterface $clock,
        private FirstStepRewards $rewards,
    ) {
    }

    /** @return array<string, true> */
    public function completed(PlayerUniverse $membership): array
    {
        $keys = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT step_key FROM first_step_completion WHERE player_universe_id = ?',
            [$membership->id],
        );

        // Une version antérieure du guide ne doit pas compter les jalons d’une version plus récente.
        return array_fill_keys(array_filter($keys, static fn (string $key): bool => FirstStep::tryFrom($key) !== null), true);
    }

    /**
     * Lit les preuves utiles au shell, sans charger stocks, récompenses ou détails du guide.
     * La colonie et son propriétaire ont déjà été vérifiés par l'appelant.
     *
     * @return array<string, true>
     */
    public function guideEvidence(PlayerUniverse $membership, Planet $planet, bool $exerciseInResponse = false): array
    {
        $buildings = [];
        foreach (FirstStep::cases() as $step) {
            if ($step->buildingSlug() !== null) {
                $buildings[$step->buildingSlug()] = $step->value;
            }
        }

        // Les migrations historiques n’utilisent pas toutes la même collation pour ces clés ASCII.
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT CAST(step_key AS BINARY) AS evidence, 0 AS is_building
                FROM first_step_completion WHERE player_universe_id = :member
                UNION ALL
                SELECT CAST(slug AS BINARY), 1 FROM planet_building
                WHERE planet_id = :planet AND tier = 'basic' AND level > 0 AND slug IN (:buildings)
                UNION ALL
                SELECT CAST(:research AS BINARY), 0 WHERE EXISTS (
                    SELECT 1 FROM player_technology
                    WHERE player_universe_id = :researchMember AND tier = 'basic' AND level > 0
                )
                SQL,
            [
                'member' => $membership->id,
                'planet' => $planet->id,
                'buildings' => array_keys($buildings),
                'research' => FirstStep::Research->value,
                'researchMember' => $membership->id,
            ],
            ['buildings' => ArrayParameterType::STRING],
        );

        $proved = [];
        $existing = [];
        foreach ($rows as $row) {
            if ((int) $row['is_building'] === 1) {
                $existing[$buildings[$row['evidence']]] = true;
            } elseif (FirstStep::tryFrom($row['evidence']) !== null) {
                $proved[$row['evidence']] = true;
            }
        }

        // Dans le seul POST qui rend son rapport, anticiper l’affichage du jalon dans la réponse.
        // Cette lecture ne l’accorde pas : seule l’écriture après succès du rendu en fait foi.
        if ($exerciseInResponse) {
            $proved[FirstStep::PirateExercise->value] = true;
        }

        return $proved + $existing + $this->heritageAtCompletion($membership, $proved, $proved + $existing);
    }

    /**
     * Ne redémarre pas un guide déjà complet parce que le joueur change de colonie.
     * Les bâtiments non attestés doivent tous exister sur une même colonie de cet empire ;
     * on ne cumule pas des bâtiments répartis entre plusieurs colonies, et on n'écrit aucun jalon.
     *
     * @param array<string, true> $proved
     * @param array<string, true> $local
     * @return array<string, true>
     */
    public function heritageAtCompletion(PlayerUniverse $membership, array $proved, array $local): array
    {
        if (count($local) >= count(FirstStep::cases())) {
            return [];
        }

        $buildings = [];
        foreach (FirstStep::cases() as $step) {
            $slug = $step->buildingSlug();
            if ($slug === null && !isset($local[$step->value])) {
                // C’est encore un guide en cours : aucune lecture supplémentaire dans le shell.
                return [];
            }
            if ($slug !== null && !isset($proved[$step->value])) {
                $buildings[$slug] = $step->value;
            }
        }
        if ($buildings === []) {
            return [];
        }

        $colony = $this->entityManager->getConnection()->fetchOne(
            <<<'SQL'
                SELECT p.id
                FROM planet p
                JOIN solar_system s ON s.id = p.solar_system_id
                JOIN galaxy g ON g.id = s.galaxy_id
                JOIN planet_building b ON b.planet_id = p.id
                WHERE p.owner_id = :membership AND g.universe_id = :universe
                  AND b.tier = 'basic' AND b.level > 0 AND b.slug IN (:buildings)
                GROUP BY p.id
                HAVING COUNT(*) = :needed
                LIMIT 1
                SQL,
            [
                'membership' => $membership->id,
                'universe' => $membership->universe->id,
                'buildings' => array_keys($buildings),
                'needed' => count($buildings),
            ],
            ['buildings' => ArrayParameterType::STRING],
        );

        return $colony === false ? [] : array_fill_keys($buildings, true);
    }

    public function recordQueueCompletion(QueueItem $item, \DateTimeImmutable $finishedAt, bool $shipConstructed = false): void
    {
        $step = FirstStep::forCompletedQueue($item, $shipConstructed);
        if ($step !== null) {
            $this->record($item->playerUniverse, $step, $finishedAt);
            $this->rewards->recordPaidGuideOrder($item);
        }
    }

    public function recordExplorationDiscovery(Fleet $fleet, PlayerDiscovery $discovery): void
    {
        $destination = $fleet->destinationSystem;
        $membership = $fleet->playerUniverse;
        if ($fleet->mission !== 'explore' || $fleet->status !== 'traveling' || $fleet->arrivalAt === null
            || $destination === null || $discovery->solarSystem !== $destination || $discovery->playerUniverse !== $membership
            || $destination->galaxy->universe->id !== $membership->universe->id
            || !$this->entityManager->getUnitOfWork()->isScheduledForInsert($discovery)) {
            return;
        }

        // La preuve accompagne la nouvelle découverte, pas un système déjà connu ni le départ.
        $this->record($membership, FirstStep::Exploration, $fleet->arrivalAt);
    }

    public function recordNpcEspionageReport(Fleet $fleet, EspionageReport $report, bool $guidedProbeConsumed = false): void
    {
        $target = $fleet->destination;
        $membership = $fleet->playerUniverse;
        if ($fleet->mission !== 'spy' || $fleet->status !== 'traveling' || $fleet->arrivalAt === null
            || $target === null || !$target->npcPlanet || $target->owner !== null
            || $target->solarSystem->galaxy->universe->id !== $membership->universe->id
            || $report->id === null || $report->target !== $target || $report->sender !== $membership
            || $report->probesSent < 1 || !in_array($report->result, ['detected', 'success'], true)) {
            return;
        }

        // Un rapport « détecté » confirme le parcours d'apprentissage, pas l'obtention de renseignements.
        $this->record($membership, FirstStep::Espionage, $report->createdAt);
        if ($guidedProbeConsumed && $report->probesSent === 1) {
            $this->rewards->replaceConsumedProbe($fleet);
        }
    }

    /** Appelé uniquement après l'acquisition d'une planète encore libre, dans la transaction d'arrivée. */
    public function recordColonization(Fleet $fleet, Planet $planet, \DateTimeImmutable $acquiredAt): void
    {
        $membership = $fleet->playerUniverse;
        if ($fleet->mission !== 'colonize' || $fleet->status !== 'traveling' || $fleet->arrivalAt === null
            || $fleet->destination !== $planet || $planet->npcPlanet || $planet->owner !== $membership
            || $planet->acquiredAt === null || $planet->acquiredAt != $acquiredAt
            || $planet->solarSystem->galaxy->universe->id !== $membership->universe->id) {
            return;
        }

        $this->record($membership, FirstStep::Colonization, $acquiredAt);
    }

    /** Appelé après rendu effectif du rapport, sans autre écriture de jeu ni rapport archivé. */
    public function recordPirateExercise(PlayerUniverse $membership): void
    {
        $this->manager->transaction(function () use ($membership): void {
            // La participation peut avoir changé pendant le calcul : valider de nouveau sous son verrou.
            $this->guard->lockMembership($membership);
            $this->record($membership, FirstStep::PirateExercise, $this->clock->now());
        });
    }

    private function record(PlayerUniverse $membership, FirstStep $step, \DateTimeImmutable $finishedAt): void
    {
        if (!$membership->isPlayerControlled()) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive()) {
            throw new \LogicException('Le résultat de jeu doit porter la preuve dans sa transaction.');
        }

        // Le verrou de participation et la transaction viennent de l’action qui applique le résultat.
        // Une nouvelle fin valide ne doit ni modifier la première preuve ni créer un doublon.
        $connection->executeStatement(
            'INSERT INTO first_step_completion (player_universe_id, step_key, completed_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE step_key = VALUES(step_key)',
            [$membership->id, $step->value, $finishedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
        );
    }
}
