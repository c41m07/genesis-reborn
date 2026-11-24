<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use Doctrine\DBAL\{ArrayParameterType, Connection};
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class AdventCohortCapture
{
    private const PAGE_SIZE = 200;
    private const MAX_MEMBERSHIPS = 5000;
    private const ON_TIME_WINDOW_SECONDS = 120;

    public function __construct(
        private EntityManagerInterface $em,
        private AdventBracket $brackets,
        private GameCatalog $catalog,
        private ClockInterface $clock,
    ) {}

    /** @return array{state:string, observed_at:string, profiles:int, unknown:int, milliseconds:int} */
    public function capture(int $edition, bool $acceptLate = false): array
    {
        $timer = hrtime(true);
        $db = $this->em->getConnection();
        if ($db->isTransactionActive()) {
            throw new \LogicException('Le relevé Avent exige sa propre lecture cohérente.');
        }
        // Une seule vue MVCC pour la cohorte, sans verrou de jeu et sans
        // rattraper une file : le jour planifié n’est pas un journal historique.
        $db->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        try {
            $observed = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $header = $db->fetchAssociative('SELECT starts_at, closes_at, published_at, cohort_captured_at, plan FROM advent_edition WHERE id = ?', [$edition]);
            if ($header === false || $header['plan'] !== 'consolidated') {
                throw new \DomainException('Édition consolidée introuvable.');
            }
            $observedAt = $observed->format('Y-m-d H:i:s');
            if ($header['cohort_captured_at'] !== null) {
                $db->commit();
                return ['state' => 'already', 'observed_at' => $header['cohort_captured_at'], 'profiles' => 0, 'unknown' => 0, 'milliseconds' => 0];
            }
            if ($observedAt < $header['starts_at'] || $observedAt >= $header['closes_at']) {
                throw new \DomainException('Le relevé doit commencer pendant l’édition.');
            }
            $startsAt = new \DateTimeImmutable($header['starts_at'], new \DateTimeZone('UTC'));
            $late = $observed->getTimestamp() - $startsAt->getTimestamp() > self::ON_TIME_WINDOW_SECONDS;
            if ($late && !$acceptLate) {
                throw new \DomainException('Relevé de départ manqué. N’acceptez une observation tardive qu’en sachant qu’elle ne reconstruit pas l’ouverture.');
            }
            [$profiles, $unknown] = $this->evaluate($db, max($header['starts_at'], $header['published_at']), $observedAt);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        // Les inscriptions tiennent le compte avant d’écrire une observation
        // liée à l’édition. Respecter ce même ordre, sans prendre de verrou de
        // jeu ni patienter si un compte a déjà une transaction active.
        try {
            $db->executeStatement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $count = $db->transactional(function (Connection $connection) use ($edition, $profiles, $observedAt, $late): ?int {
                $players = array_values(array_unique(array_column($profiles, 0)));
                sort($players, SORT_NUMERIC);
                $eligible = [];
                foreach (array_chunk($players, self::PAGE_SIZE) as $batch) {
                    foreach ($connection->fetchFirstColumn(
                        'SELECT id FROM player WHERE id IN (?) AND locked_at IS NULL ORDER BY id LOCK IN SHARE MODE NOWAIT',
                        [$batch], [ArrayParameterType::INTEGER],
                    ) as $id) {
                        $eligible[(int) $id] = true;
                    }
                }
                $prior = $connection->fetchOne('SELECT cohort_captured_at FROM advent_edition WHERE id = ? FOR UPDATE', [$edition]);
                if ($prior === false) {
                    throw new \DomainException('L’édition n’existe plus.');
                }
                if ($prior !== null) {
                    return null;
                }

                $written = 0;
                // Tout ou rien ; aucune écriture de partie n’est exécutée.
                foreach (array_chunk($profiles, self::PAGE_SIZE) as $batch) {
                    $rows = [];
                    $args = [];
                    foreach ($batch as [$player, $universe, $bracket]) {
                        if (isset($eligible[$player])) {
                            $rows[] = '(?, ?, ?, ?, ?, ?)';
                            array_push($args, $edition, $player, $universe, $bracket, $observedAt, $late ? 'late_cohort' : 'cohort');
                        }
                    }
                    if ($rows !== []) {
                        $written += $connection->executeStatement(
                            'INSERT INTO advent_progression (advent_edition_id, player_id, universe_id, bracket, observed_at, origin)
                             VALUES '.implode(', ', $rows).' ON DUPLICATE KEY UPDATE id = advent_progression.id',
                            $args,
                        );
                    }
                }
                $connection->executeStatement('UPDATE advent_edition SET cohort_captured_at = ? WHERE id = ?', [$observedAt, $edition]);

                return $written;
            });
        } catch (RetryableException $error) {
            throw new \DomainException('Un compte est en cours de modification pendant le relevé. Aucun profil n’a été publié ; réessayez.', previous: $error);
        }

        return [
            'state' => $count === null ? 'already' : ($late ? 'late' : 'captured'),
            'observed_at' => $count === null ? (string) $db->fetchOne('SELECT cohort_captured_at FROM advent_edition WHERE id = ?', [$edition]) : $observedAt,
            'profiles' => $count ?? 0,
            'unknown' => $unknown,
            'milliseconds' => (int) ((hrtime(true) - $timer) / 1000000),
        ];
    }

    /** @return array{list<array{int,int,string}>,int} */
    private function evaluate(Connection $db, string $start, string $observed): array
    {
        $profiles = [];
        $catalogues = [];
        $cursor = $unknown = $total = 0;
        do {
            $members = $db->fetchAllAssociative('SELECT m.id, m.player_id, m.universe_id, m.catalog_unlocks, u.slug, u.game_settings
                FROM player_universe m JOIN player p ON p.id = m.player_id JOIN universe u ON u.id = m.universe_id
                WHERE m.id > ? AND m.joined_at < ? AND m.forgotten_at IS NULL AND p.locked_at IS NULL
                    AND u.status = ? AND (u.starts_at IS NULL OR u.starts_at <= ?) AND (u.ends_at IS NULL OR u.ends_at > ?)
                ORDER BY m.id LIMIT '.self::PAGE_SIZE, [$cursor, $start, 'active', $observed, $observed]);
            if ($members === []) {
                break;
            }
            $total += count($members);
            if ($total > self::MAX_MEMBERSHIPS) {
                throw new \DomainException('Le nombre de participations dépasse le relevé borné. Aucun profil n’a été publié.');
            }
            $ids = array_map('intval', array_column($members, 'id'));
            $colonies = [];
            foreach ($db->fetchAllAssociative(
                'SELECT p.id, p.owner_id, b.slug, b.tier, b.level FROM planet p LEFT JOIN planet_building b ON b.planet_id = p.id WHERE p.owner_id IN (?)',
                [$ids], [ArrayParameterType::INTEGER],
            ) as $row) {
                $colonies[$row['owner_id']][$row['id']] ??= [];
                if ($row['slug'] !== null) {
                    $colonies[$row['owner_id']][$row['id']][$row['slug']][$row['tier']] = (int) $row['level'];
                }
            }
            $technologies = [];
            foreach ($db->fetchAllAssociative(
                'SELECT player_universe_id, slug, tier, level FROM player_technology WHERE player_universe_id IN (?)',
                [$ids], [ArrayParameterType::INTEGER],
            ) as $row) {
                $technologies[$row['player_universe_id']][$row['slug']][$row['tier']] = (int) $row['level'];
            }
            foreach ($members as $row) {
                $cursor = (int) $row['id'];
                $universeId = (int) $row['universe_id'];
                if (!isset($catalogues[$universeId])) {
                    $universe = new Universe();
                    $universe->id = $universeId;
                    $universe->slug = $row['slug'];
                    $universe->gameSettings = json_decode($row['game_settings'] ?? '[]', true, 512, JSON_THROW_ON_ERROR) ?? [];
                    $catalogues[$universeId] = $this->catalog->forUniverse($universe)['catalog'];
                }
                $bracket = $this->brackets->classify($colonies[$cursor] ?? [], $technologies[$cursor] ?? [],
                    json_decode($row['catalog_unlocks'] ?? '[]', true, 512, JSON_THROW_ON_ERROR) ?? [], $catalogues[$universeId]);
                if ($bracket === null) {
                    ++$unknown;
                    continue;
                }
                $profiles[] = [(int) $row['player_id'], $universeId, $bracket];
            }
        } while (count($members) === self::PAGE_SIZE);

        return [$profiles, $unknown];
    }
}
