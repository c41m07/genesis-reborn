<?php

declare(strict_types=1);

namespace App\Service\Performance;

/** Les producteurs fournissent des clés internes bornées, jamais une URL ou un nom libre. */
final class PerformanceMetrics
{
    public const PAGES = [
        'app_home' => 'Accueil public',
        'app_planets' => 'Planètes',
        'app_colony' => 'Vue générale',
        'colony_buildings' => 'Bâtiments',
        'colony_technologies' => 'Recherches',
        'colony_ships' => 'Chantier spatial',
        'colony_defenses' => 'Défenses',
        'app_login' => 'Connexion',
        'app_register' => 'Inscription',
        'app_combat_reports' => 'Liste des rapports',
        'app_combat_report' => 'Rapport de combat',
        'app_combat_simulation' => 'Simulateur de combat',
        'app_guilds' => 'Guildes',
        'app_map_v2' => 'Galaxie',
        'app_fleets' => 'Flottes',
        'app_fleet_prepare' => 'Préparation de flotte',
        'app_technology_tree' => 'Technologies',
        'app_advent' => 'Calendrier de l’Avent',
        'app_advent_edition' => 'Édition de l’Avent',
    ];
    public const BACKGROUND = ['rabbitmq', 'worker', 'mercure'];
    public const DEVICES = ['server', 'mobile', 'desktop'];
    public const FIELDS = [
        'http_php', 'http_sql', 'http_twig', 'sql_dominates', 'twig_dominates', 'php_pair', 'twig_pair',
        'ttfb', 'html_transfer', 'lcp', 'outbox_ready', 'broker_publish', 'worker_wait',
        'broker_error', 'worker_execute', 'worker_error', 'mercure_publish', 'mercure_error',
    ];

    public static function page(string $route, ?string $section): ?string
    {
        if (!isset(self::PAGES[$route]) || str_starts_with($route, 'colony_')) {
            return null;
        }
        if ($route === 'app_colony' && $section !== null && isset(self::PAGES['colony_' . $section])) {
            return 'colony_' . $section;
        }

        return $route;
    }

    public static function accepts(string $scope, string $device, string $metric): bool
    {
        return (isset(self::PAGES[$scope]) || in_array($scope, self::BACKGROUND, true))
            && in_array($device, self::DEVICES, true)
            && in_array($metric, self::FIELDS, true);
    }

    /** Les valeurs trop grandes sont bornées dans le dernier seau, pas enregistrées individuellement. */
    public static function bucket(int $milliseconds): int
    {
        $milliseconds = max(0, min(600_000, $milliseconds));
        $step = match (true) {
            $milliseconds <= 1000 => 10,
            $milliseconds <= 5000 => 50,
            $milliseconds <= 30_000 => 250,
            $milliseconds <= 120_000 => 1000,
            default => 5000,
        };

        return intdiv($milliseconds + $step - 1, $step) * $step;
    }
}
