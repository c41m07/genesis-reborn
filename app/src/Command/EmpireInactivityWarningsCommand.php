<?php
namespace App\Command;

use App\Service\Player\EmpireInactivityWarningService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:empires:warn-inactive', description: 'Prévisualiser les avertissements à J14/J27 ; aucun envoi par défaut.')]
final class EmpireInactivityWarningsCommand extends Command
{
    public function __construct(private EmpireInactivityWarningService $warnings) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('send', null, InputOption::VALUE_NONE, 'Envoyer uniquement si la configuration l’autorise.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Avec --send, traiter tous les lots et arrêter en cas d’échec ou sans progression.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de participations traitées (1 à 500).', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if ($limit === false) {
            $output->writeln('La limite doit être un entier entre 1 et 500.');
            return Command::INVALID;
        }
        $send=(bool)$input->getOption('send');$all=(bool)$input->getOption('all');
        if($all && !$send) {
            $output->writeln('--all exige --send ; sans --send, la prévisualisation reste limitée à un lot.');
            return Command::INVALID;
        }
        $result=['preview'=>!$send,'eligible'=>0,'sent'=>0,'skipped'=>0,'failed'=>0];$stalled=false;
        do {
            try { $batch=$this->warnings->run($send,$limit); }
            catch (\InvalidArgumentException | \DomainException $exception) { $output->writeln($exception->getMessage());return Command::INVALID; }
            foreach(['eligible','sent','skipped','failed'] as $key) $result[$key]+=$batch[$key];
            $stalled=$all && $batch['eligible']===$limit && $batch['sent']===0 && $batch['failed']===0;
        } while($all && $batch['eligible']===$limit && $batch['failed']===0 && !$stalled);
        $output->writeln(sprintf('%s — éligibles : %d ; envoyés : %d ; ignorés : %d ; échecs : %d.',
            $result['preview'] ? 'Prévisualisation sans écriture ni envoi' : 'Envoi',
            $result['eligible'], $result['sent'], $result['skipped'], $result['failed'],
        ));
        if($stalled) $output->writeln('Parcours interrompu sans progression ; vérifier la situation avant de relancer.');
        return $result['failed'] > 0 || $stalled ? Command::FAILURE : Command::SUCCESS;
    }
}
