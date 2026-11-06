<?php
namespace App\Command;

use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Requirement\CatalogAccess;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name:'app:catalog:preserve-unlocks',description:'Préserve les anciens accès avant de rouvrir le jeu après la migration.')]
final class PreserveCatalogUnlocksCommand extends Command
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private CatalogAccess $access)
    {
        parent::__construct();
    }
    protected function configure():void
    {
        $this->addOption('dry-run',null,InputOption::VALUE_NONE,'Compter les participations restant à reprendre, sans écriture.');
    }
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $count=0;
        foreach($this->repo->all(PlayerUniverse::class) as $membership){
            if($membership->catalogUnlocks!==null)continue;
            ++$count;
            if($input->getOption('dry-run'))continue;
            $this->manager->transaction(function()use($membership){
                $this->manager->lock($membership);$this->manager->lock($membership->universe);
                if($membership->catalogUnlocks===null)$this->access->captureLocked($membership);
            });
        }
        $output->writeln($count.' participation(s) '.($input->getOption('dry-run')?'à reprendre.':'reprise(s), progression et files conservées.'));
        return Command::SUCCESS;
    }
}
