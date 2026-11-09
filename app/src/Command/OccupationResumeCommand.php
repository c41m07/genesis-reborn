<?php
namespace App\Command;

use App\Service\Combat\OccupationScheduler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name:'app:occupations:resume',description:'Prévisualiser ou reprogrammer les contrôles des occupations actives.')]
final class OccupationResumeCommand extends Command
{
    public function __construct(private OccupationScheduler $scheduler) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('apply',null,InputOption::VALUE_NONE,'Reprogrammer les contrôles, seulement si la fonctionnalité est activée.')
            ->addOption('limit',null,InputOption::VALUE_REQUIRED,'Taille du lot (1 à 500).','100')
            ->addOption('after-id',null,InputOption::VALUE_REQUIRED,'Reprendre après cet identifiant d’occupation.','0');
    }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $io=new SymfonyStyle($input,$output);$limit=filter_var($input->getOption('limit'),FILTER_VALIDATE_INT);
        $afterId=filter_var($input->getOption('after-id'),FILTER_VALIDATE_INT);
        if($limit===false || $afterId===false) { $io->error('La limite et le curseur doivent être des entiers.');return Command::INVALID; }
        try { $result=$this->scheduler->resume((bool)$input->getOption('apply'),$limit,$afterId); }
        catch(\InvalidArgumentException $error) { $io->error($error->getMessage());return Command::INVALID; }
        catch(\DomainException $error) { $io->error($error->getMessage());return Command::FAILURE; }
        $io->writeln($result['preview']?'Prévisualisation sans écriture.':'Contrôles reprogrammés ; aucune capture exécutée par cette commande.');
        $io->writeln('Occupations examinées : '.count($result['occupations']).'.');
        if($result['occupations']!==[]) $io->table(['Occupation','Univers','Échéance','État'],array_map('array_values',$result['occupations']));
        if($result['batch_full']) $io->writeln('Lot plein : poursuivre avec --after-id='.$result['last_scanned_id'].'.');
        return Command::SUCCESS;
    }
}
