<?php
namespace App\Command;

use App\Service\Admin\AdminOperationsService;
use App\Service\Operations\{AlertEvaluator,AlertNotifier};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Transport\Receiver\{ReceiverInterface,MessageCountAwareInterface};

#[AsCommand(name:'app:operations:check',description:'Contrôler les processus, relais et échecs ; simulation par défaut.')]
final class OperationsCheckCommand extends Command
{
    public function __construct(private AdminOperationsService $operations,private AlertEvaluator $evaluator,private AlertNotifier $notifier,#[Autowire(service:'messenger.transport.failed')] private ReceiverInterface $failed) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('processes-stdin',null,InputOption::VALUE_NONE)->addOption('send',null,InputOption::VALUE_NONE);
    }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        try {
            if (!$input->getOption('processes-stdin')) throw new \InvalidArgumentException('Fournir le relevé Docker sur l’entrée standard avec --processes-stdin.');
            $raw=stream_get_contents(STDIN,65537);
            if (strlen($raw)>65536) throw new \InvalidArgumentException('Relevé de supervision trop volumineux.');
            $states=self::parseProcesses($raw);
            try { $failedCount=$this->failed instanceof MessageCountAwareInterface?$this->failed->getMessageCount():null; } catch(\Throwable) { $failedCount=null; }
            $conditions=$this->evaluator->evaluate($this->operations->snapshot(),$states,$failedCount);
            $result=$this->notifier->run($conditions,(bool)$input->getOption('send'));
            $output->writeln(json_encode(['delivery'=>$result,'conditions'=>$conditions],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
            return in_array('alert',array_column($conditions,'status'),true)?Command::FAILURE:Command::SUCCESS;
        } catch(\Throwable $error) {
            $output->writeln('Surveillance impossible : '.$error->getMessage());return Command::INVALID;
        }
    }
    public static function parseProcesses(string $raw): array
    {
        $raw=trim($raw);
        if ($raw==='') throw new \InvalidArgumentException('Relevé Docker vide.');
        if (str_starts_with($raw,'[')) $rows=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        else $rows=array_map(static fn(string $line)=>json_decode($line,true,512,JSON_THROW_ON_ERROR),explode("\n",$raw));
        $states=[];
        foreach($rows as $row) {
            if (!is_array($row) || !isset($row['Service'],$row['State']) || !is_string($row['Service']) || !is_string($row['State'])) throw new \InvalidArgumentException('Relevé Docker invalide.');
            if (!in_array($row['Service'],['worker','relay','notifier'],true)) continue;
            if (isset($states[$row['Service']])) throw new \InvalidArgumentException('Plusieurs instances pour un processus ; supervision à adapter.');
            $states[$row['Service']]=$row['State'];
        }
        return $states;
    }
}
