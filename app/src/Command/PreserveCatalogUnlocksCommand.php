<?php
namespace App\Command;

use App\Entity\{PlayerUniverse,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Requirement\CatalogAccess;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name:'app:catalog:preserve-unlocks',description:'Diagnostique ou requalifie explicitement les accès selon la nouvelle grille.')]
final class PreserveCatalogUnlocksCommand extends Command
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private CatalogAccess $access)
    {
        parent::__construct();
    }
    protected function configure():void
    {
        $this->addOption('dry-run',null,InputOption::VALUE_NONE,'Diagnostiquer les participations et surcharges sans écriture (par défaut).');
        $this->addOption('apply',null,InputOption::VALUE_NONE,'Requalifier les accès après autorisation et sauvegarde vérifiée.');
    }
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        if ($input->getOption('apply') && $input->getOption('dry-run')) {
            $output->writeln('Choisir --dry-run ou --apply.');
            return Command::INVALID;
        }
        $apply = (bool) $input->getOption('apply');
        $conflicts = 0;
        foreach ($this->repo->all(Universe::class) as $universe) {
            foreach ($this->access->overrideConflicts($universe) as $profile) {
                $output->writeln('Surcharge à examiner : univers #'.$universe->id.' / '.$profile);
                ++$conflicts;
            }
        }
        if ($apply && $conflicts > 0) {
            $output->writeln('Bascule refusée : aucune personnalisation effacée.');
            return Command::FAILURE;
        }
        $count = 0;
        foreach ($this->repo->all(PlayerUniverse::class) as $membership) {
            if ($this->access->isCurrent($membership)) {
                continue;
            }
            if (!$apply) {
                ++$count;
                continue;
            }
            // Même ordre de verrouillage que les commandes : participation, puis univers.
            $count += $this->manager->transaction(function () use ($membership): int {
                $this->manager->lock($membership);
                $this->manager->lock($membership->universe);
                // Une autre exécution a pu traiter cette participation pendant l'attente du verrou.
                if ($this->access->isCurrent($membership)) {
                    return 0;
                }
                $this->access->requalifyLocked($membership);
                return 1;
            });
        }
        $output->writeln($count.' participation(s) '.($apply?'requalifiée(s), progression et files conservées.':'à requalifier ; aucune écriture.'));
        $output->writeln($conflicts.' surcharge(s) de prérequis à examiner.');
        return Command::SUCCESS;
    }
}
