<?php
namespace App\Command;

use App\Service\Player\ForgottenEmpirePreviewService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name:'app:empires:preview-forgotten',description:'Prévisualiser les échéances J30 et leurs opérations en cours, sans écriture.')]
final class ForgottenEmpirePreviewCommand extends Command
{
    public function __construct(private ForgottenEmpirePreviewService $preview) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('limit',null,InputOption::VALUE_REQUIRED,'Taille du lot (1 à 500).','100')
            ->addOption('after-id',null,InputOption::VALUE_REQUIRED,'Reprendre après cet identifiant de participation.','0');
    }

    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $io=new SymfonyStyle($input,$output);
        $limit=filter_var($input->getOption('limit'),FILTER_VALIDATE_INT);
        $afterId=filter_var($input->getOption('after-id'),FILTER_VALIDATE_INT);
        if($limit===false || $afterId===false) {
            $io->error('La limite et le curseur doivent être des entiers.');
            return Command::INVALID;
        }
        try { $result=$this->preview->preview($limit,$afterId); }
        catch(\InvalidArgumentException $error) { $io->error($error->getMessage());return Command::INVALID; }
        $io->writeln('Prévisualisation sans écriture ni envoi. Aucune transformation exécutable dans ce lot.');
        $io->writeln('Observation : '.$result['observed_at'].' ; empires à échéance : '.count($result['empires']).'.');
        $rows=[];
        $labels=['open_combats'=>'combats ouverts','deployed_fleets'=>'flottes sorties',
            'incoming_or_stationed_fleets'=>'flottes étrangères','queued_orders'=>'commandes payées',
            'alliances'=>'alliances','guild_memberships'=>'membre de guilde','led_guilds'=>'chef de guilde',
            'unverified_account'=>'compte non vérifié','locked_account'=>'compte verrouillé'];
        foreach($result['empires'] as $empire) {
            $inventory=$empire['inventory'];
            $attention=array_map(static fn(string $key): string=>$labels[$key],$empire['attention']);
            $rows[]=[$empire['membership_id'], $empire['universe'],$empire['due_at'],$inventory['colonies'],
                $inventory['deployed_fleets'],$inventory['queued_orders'],$inventory['open_combats'],implode(', ',$attention)?:'—'];
        }
        if($rows!==[]) $io->table(['Empire','Univers','Échéance UTC','Colonies','Flottes sorties','Commandes','Combats','Points à traiter'],$rows);
        if($result['batch_full']) $io->writeln('Lot plein : poursuivre avec --after-id='.$result['last_scanned_id'].'.');
        return Command::SUCCESS;
    }
}
