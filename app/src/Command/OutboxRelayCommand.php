<?php

namespace App\Command;

use App\Service\Boost\BoostEventService;
use App\Service\Notification\{OutboxPublicationFailed, OutboxRelay};
use App\Service\Player\DailyLeaderboard;
use App\Service\Market\WeeklyMerchantDelivery;
use App\Service\ProcessHeartbeat;
use App\Service\Performance\InfrastructurePerformance;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:outbox:relay', description: 'Relayer les événements persistés. Circuit notifications séparé du gameplay.')]
final class OutboxRelayCommand extends Command
{
    public function __construct(
        private OutboxRelay $relay,
        private ProcessHeartbeat $heartbeat,
        private BoostEventService $boostEvents,
        private DailyLeaderboard $leaderboard,
        private ManagerRegistry $doctrine,
        private InfrastructurePerformance $performance,
        private WeeklyMerchantDelivery $merchants,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('loop', null, InputOption::VALUE_NONE)
            ->addOption('kind', null, InputOption::VALUE_REQUIRED, 'events ou notifications', 'events');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kind = $input->getOption('kind');
        if (!in_array($kind, ['events', 'notifications'], true)) {
            $output->writeln('kind doit être events ou notifications.');
            return Command::INVALID;
        }
        $notifications = $kind === 'notifications';
        $loop = $input->getOption('loop');
        do {
            $cyclesOk = true;
            if (!$notifications) {
                // Garder l’ordre boost → classement → publication, sans lier leur disponibilité.
                $boostOk = $this->runCycle('boost', $this->boostEvents->tick(...), $output);
                $leaderboardOk = $this->runCycle('classement', $this->leaderboard->tick(...), $output);
                $marketOk = $this->runCycle('marchands de la Bourse', $this->merchants->tick(...), $output);
                $cyclesOk = $boostOk && $leaderboardOk && $marketOk;
                // Le relais des événements est le seul processus à entretenir les agrégats.
                $this->performance->pruneIfDue();
            }

            $relayOk = true;
            try {
                $this->relay->run($notifications);
                $this->heartbeat->touch($notifications ? 'notifier' : 'relay');
            } catch (OutboxPublicationFailed $failure) {
                // Le lot a été parcouru, mais une écriture au broker reste indéterminée.
                // Ne pas rafraîchir le signal de succès : la prochaine passe retrouve les mêmes lignes.
                $output->writeln('Relais '.$kind.' incomplet : '.$failure->getMessage());
                $relayOk = false;
            } catch (\Throwable $error) {
                $output->writeln(sprintf('Relais %s interrompu (%s).', $kind, $error::class));
                return Command::FAILURE;
            }

            if (!$loop) {
                return $cyclesOk && $relayOk ? Command::SUCCESS : Command::FAILURE;
            }
            sleep(1);
        } while (true);
    }

    private function runCycle(string $phase, callable $cycle, OutputInterface $output): bool
    {
        try {
            $cycle();
            return true;
        } catch (\Throwable $error) {
            $output->writeln(sprintf('Cycle %s en échec (%s).', $phase, $error::class));
            $manager = $this->doctrine->getManager();
            if ($manager instanceof EntityManagerInterface && !$manager->isOpen()) {
                // Un rollback Doctrine ferme le gestionnaire. Réinitialiser le service paresseux
                // partagé avant le prochain cycle, sans réutiliser les entités de la transaction échouée.
                $this->doctrine->resetManager();
            }
            return false;
        }
    }
}
