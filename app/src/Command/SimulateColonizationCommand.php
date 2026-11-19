<?php

namespace App\Command;

use App\Entity\Universe;
use App\Service\Balance\FundedProgressionSimulation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:balance:simulate-colonization', description: 'Estime le délai de première colonisation à plusieurs cadences de connexion, hors base de données.')]
final class SimulateColonizationCommand extends Command
{
    public function __construct(private FundedProgressionSimulation $simulation)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $goals = [
            ['group' => 'buildings', 'slug' => 'power_plant', 'level' => 5],
            ['group' => 'buildings', 'slug' => 'mine_metal', 'level' => 3],
            ['group' => 'buildings', 'slug' => 'mine_nano', 'level' => 3],
            ['group' => 'technologies', 'slug' => 'colonial_expansion', 'level' => 1, 'label' => 'research'],
            ['group' => 'ships', 'slug' => 'colony_ship', 'quantity' => 1, 'label' => 'ship'],
            // Le système de départ est déjà découvert. La position 6 est supposée libre.
            ['group' => 'missions', 'mission' => 'colonize', 'composition' => ['colony_ship' => ['basic' => 1]],
                'same_system' => true, 'normal_distance' => 10, 'hyper_distance' => 0,
                'after_label' => 'ship', 'label' => 'arrival'],
        ];

        $report = [];
        foreach (['reference' => 288000, 'current' => null] as $version => $previousBase) {
            $universe = new Universe();
            $universe->slug = 'omega-1';
            $universe->name = 'Omega · simulation de colonisation';
            if ($previousBase !== null) {
                // Les objectifs Basique ont gardé les mêmes profils matériels.
                $universe->gameSettings['catalog_overrides']['technologies']['colonial_expansion']['research_time_base'] = $previousBase;
            }
            foreach ([[null, null], [1, 900], [2, 900], [3, 900], [1, 1800], [2, 1800], [3, 1800]] as [$connections, $sessionSeconds]) {
                $options = ['position' => 5, 'trace' => true, 'max_seconds' => 30 * 86400];
                if ($sessionSeconds !== null) {
                    $options['session_interval'] = intdiv(86400, $connections);
                    $options['session_seconds'] = $sessionSeconds;
                }
                $result = $this->simulation->run($universe, $goals, $options);
                $report[$version][] = [
                    'connections_per_day' => $connections,
                    'session_seconds' => $sessionSeconds,
                    'status' => $result['status'],
                    'seconds' => $result['seconds'],
                    'fuel_reserved' => $result['metrics']['fuel_charged'],
                    'operations' => $result['operations'],
                ];
            }
        }

        $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
