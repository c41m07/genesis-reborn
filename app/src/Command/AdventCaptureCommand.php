<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Advent\AdventCohortCapture;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:advent:capture', description: 'Fixer le relevé de progression d’une édition Avent.')]
final class AdventCaptureCommand extends Command
{
    public function __construct(private readonly AdventCohortCapture $capture)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('edition', InputArgument::REQUIRED, 'Identifiant de l’édition publiée.');
        $this->addOption('accept-late', null, InputOption::VALUE_NONE, 'Accepter explicitement une observation tardive, sans la présenter comme l’ouverture.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $raw = $input->getArgument('edition');
        if (!is_string($raw) || !ctype_digit($raw) || (int) $raw < 1) {
            return self::INVALID;
        }
        try {
            $result = $this->capture->capture((int) $raw, (bool) $input->getOption('accept-late'));
        } catch (\DomainException $error) {
            $output->writeln('<error>'.$error->getMessage().'</error>');
            return self::FAILURE;
        }
        $output->writeln(sprintf('%s ; observation UTC %s ; %d profils ; %d non évalués ; %d ms.',
            $result['state'], $result['observed_at'], $result['profiles'], $result['unknown'], $result['milliseconds']));

        return self::SUCCESS;
    }
}
