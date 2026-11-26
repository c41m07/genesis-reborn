<?php

namespace App\Service\Combat;

use App\Entity\OutboxMessage;
use App\Entity\PlayerCombatSimulationJob;
use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use App\Message\PlayerCombatSimulationDue;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/** Coordonne les essais privés différés ; aucun résultat ne constitue un rapport de combat du jeu. */
final class PlayerCombatSimulationQueue
{
    // Borner le temps d'une livraison même si chaque essai atteint le budget du moteur.
    public const TRIALS_PER_DELIVERY = 4;

    public function __construct(
        private readonly GameManager $manager,
        private readonly GameRepository $repo,
        private readonly ClockInterface $clock,
        private readonly PlayerCombatSimulation $simulation,
        private readonly CombatSimulationView $view,
    ) {
    }

    /**
     * @param array $prepared Statistiques calculées avec le catalogue au moment de la demande.
     * @param array $rows Libellés et effectifs d’origine figés pour le bilan.
     */
    public function enqueue(PlayerUniverse $owner, array $prepared, array $rows, int $seed, int $report = 0, int $ownSource = 0, int $pirateReport = 0): PlayerCombatSimulationJob
    {
        $player = $owner->player;
        if ($player === null || !$owner->isPlayerControlled()) {
            throw new \DomainException('Simulation inaccessible.');
        }

        $accepted = $this->manager->transaction(function () use ($player, $owner, $prepared, $rows, $seed, $report, $ownSource, $pirateReport): ?PlayerCombatSimulationJob {
            // Le verrou du joueur existe avant le premier calcul et couvre toutes ses appartenances.
            // Limiter une demande lourde en cours par compte empêche de remplir la file en changeant d’univers.
            $this->manager->lockCurrent($player);
            $job = $this->repo->one(PlayerCombatSimulationJob::class, ['player' => $player]);
            if ($job instanceof PlayerCombatSimulationJob) {
                $job = $this->repo->combatSimulationJobForUpdate($job->id);
            }
            $now = $this->now();
            if ($job instanceof PlayerCombatSimulationJob && $job->status === 'pending' && $job->expiresAt > $now) {
                // Deux rapports peuvent avoir les mêmes effectifs, mais des règles ou profils figés différents.
                $existingPirateReport = $job->scenario['pirate_report'] ?? 0;
                $sameScenario = $pirateReport > 0
                    ? $job->scenario['prepared'] == $prepared
                    : $job->scenario['prepared']['forces'] == $prepared['forces'];
                if ($job->membership->id === $owner->id && $job->scenario['seed'] === $seed
                    && $existingPirateReport === $pirateReport && $sameScenario) {
                    return $job;
                }

                return null;
            }

            $job ??= new PlayerCombatSimulationJob();
            $job->player = $player;
            $job->membership = $owner;
            $job->publicKey = Uuid::v4()->toRfc4122();
            $job->dispatchToken = Uuid::v4()->toRfc4122();
            $job->status = 'pending';
            $job->scenario = ['prepared' => $prepared, 'seed' => $seed, 'rows' => $rows, 'report' => $report, 'own_source' => $ownSource];
            if ($pirateReport > 0) {
                // Ajouter la référence sans changer le format des jobs historiques.
                $job->scenario['pirate_report'] = $pirateReport;
            }
            $job->trials = [];
            $job->expiresAt = $now->modify('+24 hours');
            $this->manager->save($job);
            $this->manager->flush();
            $this->schedule($job, PlayerCombatSimulationDue::KIND, $job->dispatchToken, $now);
            $this->schedule($job, PlayerCombatSimulationDue::EXPIRY, $job->publicKey, $job->expiresAt);

            return $job;
        });
        if ($accepted === null) {
            throw new \DomainException('Une simulation en arrière-plan est déjà en cours sur votre compte.');
        }

        return $accepted;
    }

    public function visible(PlayerUniverse $owner, string $key): ?PlayerCombatSimulationJob
    {
        if (!$owner->isPlayerControlled() || $owner->player === null || !Uuid::isValid($key)) {
            return null;
        }
        // La clé d’URL ne suffit pas à consulter une simulation : compte et univers doivent correspondre.
        $job = $this->repo->one(PlayerCombatSimulationJob::class, ['publicKey' => $key, 'player' => $owner->player, 'membership' => $owner]);

        return $job instanceof PlayerCombatSimulationJob && $job->expiresAt > $this->now() ? $job : null;
    }

    public function run(PlayerCombatSimulationDue $message): void
    {
        // Chaque essai est validé séparément en base. Regrouper les livraisons évite de
        // refaire le trajet relais → RabbitMQ → worker pour chacun des dix essais.
        do {
            $again = $this->runNextTrial($message);
        } while ($again);
    }

    private function runNextTrial(PlayerCombatSimulationDue $message): bool
    {
        $job = $this->repo->find(PlayerCombatSimulationJob::class, $message->id);
        if (!$job instanceof PlayerCombatSimulationJob || !$this->canRun($job, $message)) {
            return false;
        }
        $index = count($job->trials);
        $scenario = $job->scenario;
        // Le calcul ne tient ni le verrou du joueur, ni une transaction SQL ouverte.
        try {
            $trial = $this->simulation->runTrial($scenario['prepared'], $scenario['seed'], $index);
        } catch (CombatCapacityExceeded) {
            $this->markFailed($message, 'capacity');

            return false;
        }
        $trial = $this->view->prepareRows(['trials' => [$trial]], $scenario['rows'])['trials'][0];
        if ($scenario['prepared']['grouped_survivors'] || ($trial['grouped_survivors'] ?? false)) {
            $trial['survivors'] = $this->summarizeSurvivors($trial['survivors']);
            $trial['grouped_survivors'] = true;
        }
        unset($trial['losses']);

        return $this->manager->transaction(function () use ($message, $trial, $index): bool {
            $job = $this->repo->combatSimulationJobForUpdate($message->id);
            // Une livraison répétée peut recalculer un essai ; seul l'indice suivant peut être ajouté.
            if ($job === null || !$this->canRun($job, $message) || count($job->trials) !== $index) {
                return false;
            }
            $job->trials[] = $trial;
            $completed = $index + 1;
            if ($completed === PlayerCombatSimulation::TRIALS) {
                $job->status = 'done';

                return false;
            }
            if ($completed % self::TRIALS_PER_DELIVERY === 0) {
                $job->dispatchToken = Uuid::v4()->toRfc4122();
                $this->schedule($job, PlayerCombatSimulationDue::KIND, $job->dispatchToken, $this->now());

                return false;
            }

            // En cas de reprise du message, les essais déjà validés ne sont pas rejoués.
            return true;
        });
    }

    public function fail(PlayerCombatSimulationDue $message): void
    {
        $this->markFailed($message, 'failed');
    }

    private function markFailed(PlayerCombatSimulationDue $message, string $status): void
    {
        $this->manager->transaction(function () use ($message, $status): void {
            $job = $this->repo->combatSimulationJobForUpdate($message->id);
            if ($job !== null && $this->canRun($job, $message)) {
                $job->status = $status;
            }
        });
    }

    public function expire(PlayerCombatSimulationDue $message): void
    {
        $this->manager->transaction(function () use ($message): void {
            $job = $this->repo->combatSimulationJobForUpdate($message->id);
            if ($job !== null && $job->publicKey === $message->token && $job->expiresAt <= $this->now()) {
                $this->manager->remove($job);
            }
        });
    }

    /** En file, conserver les effectifs et les extrêmes exacts sans une ligne HTML par unité. */
    private function summarizeSurvivors(array $survivors): array
    {
        $groups = [];
        foreach ($survivors as $survivor) {
            $key = $survivor['camp'].':'.$survivor['slug'].':'.$survivor['tier'];
            $alreadyGrouped = isset($survivor['quantity']);
            $min = $alreadyGrouped ? ($survivor['hp_min'] ?? null) : $survivor['hp'];
            $max = $alreadyGrouped ? ($survivor['hp_max'] ?? null) : $survivor['hp'];
            $shieldMin = $alreadyGrouped ? ($survivor['shield_min'] ?? null) : ($survivor['shield'] ?? null);
            $shieldMax = $alreadyGrouped ? ($survivor['shield_max'] ?? null) : ($survivor['shield'] ?? null);
            $groups[$key] ??= [
                'camp' => $survivor['camp'], 'slug' => $survivor['slug'], 'tier' => $survivor['tier'],
                'quantity' => 0, 'hp_min' => $min, 'hp_max' => $max, 'shield_min' => $shieldMin, 'shield_max' => $shieldMax,
            ];
            $groups[$key]['quantity'] += $survivor['quantity'] ?? 1;
            $groups[$key]['hp_min'] = $min === null || $groups[$key]['hp_min'] === null ? null : min($min, $groups[$key]['hp_min']);
            $groups[$key]['hp_max'] = $max === null || $groups[$key]['hp_max'] === null ? null : max($max, $groups[$key]['hp_max']);
            $groups[$key]['shield_min'] = $shieldMin === null || $groups[$key]['shield_min'] === null ? null : min($shieldMin, $groups[$key]['shield_min']);
            $groups[$key]['shield_max'] = $shieldMax === null || $groups[$key]['shield_max'] === null ? null : max($shieldMax, $groups[$key]['shield_max']);
        }

        return array_values($groups);
    }

    private function canRun(PlayerCombatSimulationJob $job, PlayerCombatSimulationDue $message): bool
    {
        return $job->status === 'pending'
            && $job->dispatchToken === $message->token
            && $job->expiresAt > $this->now()
            && $job->membership->isPlayerControlled()
            && $job->membership->player?->id === $job->player->id;
    }

    private function schedule(PlayerCombatSimulationJob $job, string $kind, string $token, \DateTimeImmutable $due): void
    {
        $entry = new OutboxMessage();
        $entry->kind = $kind;
        $entry->targetId = $job->id;
        $entry->token = $token;
        $entry->dueAt = $due;
        $this->manager->save($entry);
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
