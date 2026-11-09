<?php
namespace App\Command;

use App\Service\Player\EmpireConversionBatch;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name:'app:empires:convert-forgotten',description:'Prévisualiser les conversions J30 ; application explicite uniquement si activées.')]
final class EmpireConversionCommand extends Command
{
    public function __construct(private EmpireConversionBatch $batch) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('apply',null,InputOption::VALUE_NONE,'Convertir les empires à échéance si la fonctionnalité est activée.')
            ->addOption('all',null,InputOption::VALUE_NONE,'Parcourir tous les lots, y compris après les empires reportés pour combat.')
            ->addOption('limit',null,InputOption::VALUE_REQUIRED,'Taille du lot (1 à 500).','100')
            ->addOption('after-id',null,InputOption::VALUE_REQUIRED,'Reprendre après cet identifiant de participation.','0');
    }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $io=new SymfonyStyle($input,$output);$limit=filter_var($input->getOption('limit'),FILTER_VALIDATE_INT);
        $afterId=filter_var($input->getOption('after-id'),FILTER_VALIDATE_INT);
        if($limit===false || $afterId===false) { $io->error('La limite et le curseur doivent être des entiers.');return Command::INVALID; }
        $all=(bool)$input->getOption('all');$converted=0;$deferred=0;
        do {
            try { $result=$this->batch->run((bool)$input->getOption('apply'),$limit,$afterId); }
            catch(\InvalidArgumentException $error) { $io->error($error->getMessage());return Command::INVALID; }
            catch(\DomainException $error) { $io->error($error->getMessage());return Command::FAILURE; }
            $io->writeln($result['preview']?'Prévisualisation sans écriture ni envoi.':'Application des conversions J30.');
            $io->writeln(sprintf('Éligibles : %d ; convertis : %d ; reportés pour combat : %d ; ignorés : %d ; échecs : %d.',
                $result['eligible'],$result['converted'],$result['deferred'],$result['skipped'],$result['failed']));
            $labels=['eligible'=>'Éligible','converted'=>'Converti','deferred_combat'=>'Reporté : combat ouvert',
                'not_due'=>'Plus à échéance','already_converted'=>'Déjà converti','not_found'=>'Introuvable','failed'=>'Échec'];
            $rows=[];foreach($result['empires'] as $empire) $rows[]=[$empire['membership_id'],$empire['universe'],$empire['due_at'],$labels[$empire['status']]??$empire['status']];
            if($rows!==[]) $io->table(['Empire','Univers','Échéance UTC','Résultat'],$rows);
            if($result['failed']>0) {
                $io->error('Lot interrompu sur l’empire '.$result['failed_membership_id'].'. Les conversions précédentes sont conservées. Consulter les journaux et corriger la cause avant de reprendre avec --after-id='.$result['last_scanned_id'].'.');
                return Command::FAILURE;
            }
            if($result['batch_full'] && !$all) $io->writeln('Lot plein : poursuivre avec --after-id='.$result['last_scanned_id'].'.');
            $converted+=$result['converted'];$deferred+=$result['deferred'];
            $afterId=$result['last_scanned_id'];
        } while($all && $result['batch_full']);
        if($all) $io->writeln('Parcours terminé : '.$converted.' conversion(s), '.$deferred.' report(s) pour combat.');
        if($deferred>0) $io->writeln('Repartir sans curseur lors du prochain passage pour réexaminer les combats reportés.');
        return Command::SUCCESS;
    }
}
