<?php
namespace App\Command;
use App\Service\Notification\OutboxRelay;
use App\Service\Boost\BoostEventService;
use App\Service\ProcessHeartbeat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:outbox:relay',description:'Relayer les événements persistés. Circuit notifications séparé du gameplay.')]
final class OutboxRelayCommand extends Command
{
    public function __construct(private OutboxRelay $relay,private ProcessHeartbeat $heartbeat,private BoostEventService $boostEvents,private \App\Service\Player\DailyLeaderboard $leaderboard) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('loop',null,InputOption::VALUE_NONE)->addOption('kind',null,InputOption::VALUE_REQUIRED,'events ou notifications','events');
    }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $kind=$input->getOption('kind');
        if(!in_array($kind,['events','notifications'],true)) { $output->writeln('kind doit être events ou notifications.');return Command::INVALID; }
        do {
            try { if($kind==='events') { $this->boostEvents->tick();$this->leaderboard->tick(); }$this->relay->run($kind==='notifications');$this->heartbeat->touch($kind==='notifications'?'notifier':'relay'); }
            catch(\Throwable $e) { $output->writeln('Relais '.$kind.' interrompu : '.$e->getMessage());return Command::FAILURE; }
            if($input->getOption('loop')) sleep(1);
        } while($input->getOption('loop'));
        return Command::SUCCESS;
    }
}
