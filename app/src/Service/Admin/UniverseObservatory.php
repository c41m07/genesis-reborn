<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

final class UniverseObservatory
{
    public const TABS = [
        'audience' => 'Audience',
        'economy' => 'Économie',
        'combat' => 'Combats',
        'progress' => 'Progression',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly GameCatalog $catalog,
    ) {
    }

    /** Prépare uniquement le sujet affiché. Le jour en cours est toujours partiel. */
    public function read(Universe $universe, string $tab, int $days): array
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $today = $now->setTime(0, 0);
        $from = $today->modify('-'.($days - 1).' days');
        $range = [$from->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')];

        return match ($tab) {
            'audience' => $this->audience((int) $universe->id, $range, $from, $today),
            'combat' => $this->combat($universe, $range),
            'progress' => $this->progress((int) $universe->id, $range),
            default => [],
        };
    }

    private function audience(int $universeId, array $range, \DateTimeImmutable $from, \DateTimeImmutable $today): array
    {
        // Le compte peut être confirmé avant d’entrer dans un univers : cette série est globale.
        $confirmed = $this->connection->fetchAllKeyValue(
            'SELECT DATE(email_verified_at), COUNT(*) FROM player
             WHERE email_verified_at BETWEEN ? AND ? GROUP BY DATE(email_verified_at)',
            $range,
        );
        $joined = $this->connection->fetchAllKeyValue(
            'SELECT DATE(joined_at), COUNT(*) FROM player_universe
             WHERE universe_id = ? AND (player_id IS NOT NULL OR former_player_id IS NOT NULL)
               AND joined_at BETWEEN ? AND ? GROUP BY DATE(joined_at)',
            [$universeId, ...$range],
        );
        $activeFrom = max($from, $today->modify('-6 days'))->format('Y-m-d');
        $active = [];
        $first = null;
        // Le service d’activité ne garantit que 7 jours par participation. Ne pas prolonger la courbe par des zéros.
        $rows = $this->connection->iterateAssociative(
            'SELECT a.daily_seconds, a.tracked_since FROM player_activity a JOIN player_universe u ON u.id = a.membership_id
             WHERE u.universe_id = ? AND (u.player_id IS NOT NULL OR u.former_player_id IS NOT NULL)',
            [$universeId],
        );
        foreach ($rows as $row) {
            $tracked = substr((string) $row['tracked_since'], 0, 10);
            $first = $first === null ? $tracked : min($first, $tracked);
            foreach ((array) json_decode((string) $row['daily_seconds'], true, 512, JSON_THROW_ON_ERROR) as $date => $seconds) {
                if ($date >= $activeFrom && $seconds > 0) {
                    $active[$date] = ($active[$date] ?? 0) + 1;
                }
            }
        }
        $series = [];
        for ($date = $from; $date <= $today; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'confirmed' => (int) ($confirmed[$key] ?? 0),
                'joined' => (int) ($joined[$key] ?? 0),
                'active' => $first !== null && $key >= max($first, $activeFrom) ? ($active[$key] ?? 0) : null,
            ];
        }

        return [
            'rows' => $series,
            'chart' => $this->chart($series, ['confirmed', 'joined', 'active']),
            'confirmations' => (int) array_sum($confirmed),
            'entries' => (int) array_sum($joined),
            'activeToday' => $series[array_key_last($series)]['active'],
        ];
    }

    private function progress(int $universeId, array $range): array
    {
        // Chaque ligne correspond à un jalon enregistré, pas à une cohorte de nouveaux joueurs.
        return $this->connection->fetchAllAssociative(
            'SELECT f.step_key AS step, COUNT(*) AS total
             FROM first_step_completion f JOIN player_universe u ON u.id = f.player_universe_id
             WHERE u.universe_id = ? AND f.completed_at BETWEEN ? AND ?
             GROUP BY f.step_key ORDER BY total DESC, f.step_key',
            [$universeId, ...$range],
        );
    }

    private function combat(Universe $universe, array $range): array
    {
        $ships = $this->unitCatalog('ships');
        $defenses = $this->unitCatalog('defenses');
        $base = ' FROM combat_log l JOIN combat_event e ON e.id = l.combat_event_id
             JOIN planet p ON p.id = e.planet_id JOIN solar_system s ON s.id = p.solar_system_id
             JOIN galaxy g ON g.id = s.galaxy_id WHERE g.universe_id = ? AND l.resolved_at BETWEEN ? AND ?';
        $params = [$universe->id, ...$range];
        $outcomes = $this->connection->fetchAllKeyValue('SELECT l.outcome, COUNT(*)'.$base.' GROUP BY l.outcome', $params);
        $cursor = 0;
        $count = 0;
        // Parcours borné par lot : les rapports portent aussi des rounds détaillés que cette vue ne lit pas.
        do {
            $batch = $this->connection->fetchAllAssociative(
                'SELECT l.id, l.snapshot'.$base.' AND l.id > ? ORDER BY l.id LIMIT 100',
                [...$params, $cursor],
            );
            foreach ($batch as $row) {
                $cursor = (int) $row['id'];
                $snapshot = json_decode((string) $row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($snapshot['participants'] ?? null) || !is_array($snapshot['losses'] ?? null)) {
                    continue;
                }
                // Ne pas mélanger une flotte PNJ avec les unités jouées. La campagne pirate a son propre parcours.
                if (array_filter($snapshot['participants'], static fn (array $p): bool => ($p['pve_npc'] ?? false) === true)) {
                    continue;
                }
                $seen = [];
                foreach ($snapshot['participants'] as $participant) {
                    if (empty($participant['fleet_id'])) {
                        continue;
                    }
                    foreach ($participant['units'] ?? [] as $unit) {
                        $tier = $unit['tier'] ?? 'basic';
                        $slug = $unit['slug'] ?? '';
                        $key = $slug.':'.$tier;
                        $quantity = (int) ($unit['quantity'] ?? 0);
                        if (!isset($ships[$key]) || $quantity <= 0) {
                            continue;
                        }
                        // Même clé que le rapport de bataille, y compris l’absence de suffixe pour le rang basique.
                        $lossKey = $participant['fleet_id'].':'.$slug.($tier === 'basic' ? '' : ':'.$tier);
                        $ships[$key]['fielded'] += $quantity;
                        $ships[$key]['lost'] += min($quantity, max(0, (int) ($snapshot['losses'][$lossKey] ?? 0)));
                        $seen[$key] = true;
                    }
                }
                $seenDefenses = [];
                foreach ($snapshot['defenses'] ?? [] as $unit) {
                    $tier = $unit['tier'] ?? 'basic';
                    $slug = $unit['slug'] ?? '';
                    $key = $slug.':'.$tier;
                    $quantity = (int) ($unit['quantity'] ?? 0);
                    if (!isset($defenses[$key]) || $quantity <= 0 || !isset($unit['id'])) {
                        continue;
                    }
                    // Les ouvrages ont un propriétaire distinct des flottes dans le journal de bataille.
                    $lossKey = 'planet:'.$unit['id'].':'.$slug.($tier === 'basic' ? '' : ':'.$tier);
                    $defenses[$key]['fielded'] += $quantity;
                    $defenses[$key]['lost'] += min($quantity, max(0, (int) ($snapshot['losses'][$lossKey] ?? 0)));
                    $seenDefenses[$key] = true;
                }
                foreach (array_keys($seenDefenses) as $key) {
                    ++$defenses[$key]['battles'];
                    // Le rapport agrège la reconstruction par type et rang, non par ouvrage.
                    $defenses[$key]['rebuilt'] += max(0, (int) ($snapshot['defense_rebuilt'][$key] ?? 0));
                }
                foreach (array_keys($seen) as $key) {
                    ++$ships[$key]['battles'];
                }
                $count += $seen !== [] || $seenDefenses !== [] ? 1 : 0;
            }
        } while (count($batch) === 100);

        return ['outcomes' => array_map('intval', $outcomes), 'ships' => $ships, 'defenses' => $defenses, 'withComposition' => $count];
    }

    private function unitCatalog(string $group): array
    {
        $rows = [];
        foreach ($this->catalog->all($group) as $slug => $definition) {
            foreach (GameCatalog::TIERS as $tier) {
                $rank = $this->catalog->withTier($definition, $tier);
                $rows[$slug.':'.$tier] = [
                    'name' => $rank['label'],
                    'tier' => $rank['_tier_label'],
                    'fielded' => 0,
                    'lost' => 0,
                    'battles' => 0,
                    'rebuilt' => 0,
                ];
            }
        }

        return $rows;
    }

    private function chart(array $rows, array $keys): array
    {
        $max = 1;
        foreach ($rows as $row) {
            $max = max($max, ...array_map(static fn (string $key): int => (int) $row[$key], $keys));
        }
        $paths = [];
        foreach ($keys as $key) {
            $parts = [];
            $command = 'M';
            foreach ($rows as $i => $row) {
                if ($row[$key] === null) {
                    $command = 'M';
                    continue;
                }
                $x = 10 + (int) round(740 * $i / max(1, count($rows) - 1));
                $y = 210 - (int) round(180 * $row[$key] / $max);
                $parts[] = $command.$x.' '.$y;
                $command = 'L';
            }
            $paths[$key] = implode(' ', $parts);
        }

        return ['max' => $max, 'paths' => $paths];
    }
}
