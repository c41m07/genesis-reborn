<?php
namespace App\Command;

use App\Service\Boost\BoostEventService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:boost-events:tick', description: 'Planifie les événements de boosts et clôt ceux qui sont terminés.')]
final class BoostEventsTickCommand extends Command
{
    public function __construct(private BoostEventService $events)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->events->tick();
        $output->writeln(sprintf('Événements démarrés : %d; clôturés : %d.', $result['started'], $result['closed']));

        return Command::SUCCESS;
    }
}
