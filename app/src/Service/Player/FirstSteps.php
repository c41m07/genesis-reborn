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
     * Lit la progression affichée de cet empire sans charger de stocks ou de récompenses.
     * Une seule colonie détenue dans l’univers fournit les bâtiments historiques manquants :
     * celle qui apporte le plus de jalons supplémentaires, puis l’identifiant le plus bas.
     * Le choix de la colonie affichée ne change ni ces indices, ni les preuves persistées.
     *
     * @return array{proved:array<string, true>, existing:array<string, true>}
     */
    public function guideEvidence(PlayerUniverse $membership, bool $exerciseInResponse = false): array
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
                SELECT CAST(step_key AS BINARY) AS evidence, 0 AS source, NULL AS colony
                FROM first_step_completion WHERE player_universe_id = :member
                UNION ALL
                SELECT CAST(b.slug AS BINARY), 1, p.id
                FROM planet p
                JOIN solar_system s ON s.id = p.solar_system_id
                JOIN galaxy g ON g.id = s.galaxy_id
                JOIN planet_building b ON b.planet_id = p.id
                WHERE p.owner_id = :owner AND g.universe_id = :universe
                  AND b.tier = 'basic' AND b.level > 0 AND b.slug IN (:buildings)
                UNION ALL
                SELECT CAST(:research AS BINARY), 2, NULL WHERE EXISTS (
                    SELECT 1 FROM player_technology
                    WHERE player_universe_id = :researchMember AND tier = 'basic' AND level > 0
                )
                SQL,
            [
                'member' => $membership->id,
                'owner' => $membership->id,
                'universe' => $membership->universe->id,
                'buildings' => array_keys($buildings),
                'research' => FirstStep::Research->value,
                'researchMember' => $membership->id,
            ],
            ['buildings' => ArrayParameterType::STRING],
        );

        $proved = [];
        $existing = [];
        $byColony = [];
        foreach ($rows as $row) {
            $evidence = $row['evidence'];
            if ((int) $row['source'] === 1) {
                $byColony[(int) $row['colony']][$buildings[$evidence]] = true;
            } elseif ((int) $row['source'] === 2) {
                $existing[FirstStep::Research->value] = true;
            } elseif (FirstStep::tryFrom($evidence) !== null) {
                $proved[$evidence] = true;
            }
        }

        // Dans le seul POST qui rend son rapport, anticiper l’affichage du jalon dans la réponse.
        // Cette lecture ne l’accorde pas : seule l’écriture après succès du rendu en fait foi.
        if ($exerciseInResponse) {
            $proved[FirstStep::PirateExercise->value] = true;
        }

        ksort($byColony, SORT_NUMERIC);
        $inheritedBuildings = [];
        foreach ($byColony as $candidate) {
            $candidate = array_diff_key($candidate, $proved);
            if (count($candidate) > count($inheritedBuildings)) {
                $inheritedBuildings = $candidate;
            }
        }

        return ['proved' => $proved, 'existing' => array_diff_key($existing + $inheritedBuildings, $proved)];
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
