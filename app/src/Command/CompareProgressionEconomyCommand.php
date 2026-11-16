<?php

namespace App\Command;

use App\Service\Balance\{CostDistributionCandidate, MilitaryEconomyStudy, ProgressionEconomyStudy};
use App\Service\Catalog\GameCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
#[AsCommand(name: 'app:balance:compare-economy', description: 'Mesure des parcours financés et le candidat de coûts, sans lire ni modifier les données de jeu.')]
final class CompareProgressionEconomyCommand extends Command
{
    public function __construct(private GameCatalog $catalog, private ProgressionEconomyStudy $study, private MilitaryEconomyStudy $military)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('section', null, InputOption::VALUE_REQUIRED, 'all, costs, economy ou military.', 'all')->addOption('trace', null, InputOption::VALUE_NONE, 'Inclure les opérations individuelles des parcours.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $section = $input->getOption('section');
        if (!in_array($section, ['all', 'costs', 'economy', 'military'], true)) {
            throw new \InvalidArgumentException('Section de mesure inconnue.');
        }
        $config = $this->catalog->forUniverse($this->study->universe());
        $report = [
            'baseline_revision' => ProgressionEconomyStudy::BASELINE_REVISION,
            'catalog_sha256' => hash('sha256', json_encode($config, JSON_THROW_ON_ERROR)),
            'scenario_version' => 1,
            'economic_weights' => CostDistributionCandidate::WEIGHTS,
            'hypotheses' => [
                'Univers ×1, position 5 sauf sensibilités annoncées ; nouveaux empires et kits issus des règles actuelles.',
                'Politique déterministe, avec paiement seulement au départ des opérations ; aucune recherche d’optimum.',
                'Bâtiment, recherche et chantier indépendants ; une file de recherche et une flotte de mission à la fois.',
                'Événements et vacances absents des parcours de référence ; le moteur de projection respecte leurs frontières explicites.',
                'Capacités, déficits et consommation H du piège intégrés ; les stockages Basiques sont améliorés si un achat dépasse la capacité.',
                'Le candidat de prix est un ensemble de surcharges sur un univers transitoire, jamais enregistré.',
                'Les infrastructures données aux expériences sont distinguées des parcours depuis un départ neuf.',
            ],
        ];
        if (in_array($section, ['all', 'costs'], true)) {
            $report['costs'] = $this->study->costs();
        }
        if (in_array($section, ['all', 'economy'], true)) {
            $progress = $output instanceof ConsoleOutputInterface ? static fn(string $name) => $output->getErrorOutput()->writeln('Mesure : ' . $name) : null;
            $report['economy'] = $this->study->economy($progress, (bool) $input->getOption('trace'));
        }
        if (in_array($section, ['all', 'military'], true)) {
            $progress = $output instanceof ConsoleOutputInterface ? static fn(string $name) => $output->getErrorOutput()->writeln('Mesure : ' . $name) : null;
            $report['military'] = $this->military->run($report['economy']['current']['science_military']['checkpoint'] ?? null, $progress);
        }
        $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return Command::SUCCESS;
    }
}
