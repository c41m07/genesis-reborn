<?php
namespace App\Command;
use App\Service\Universe\UniverseImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:universe:import',description:'Importer explicitement les cartes, sans modifier les galaxies déjà présentes.')]
final class UniverseImportCommand extends Command
{
    public function __construct(private UniverseImporter $importer) { parent::__construct(); }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $output->writeln($this->importer->import().' planètes ajoutées.');return Command::SUCCESS;
    }
}
