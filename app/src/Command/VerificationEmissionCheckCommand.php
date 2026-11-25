<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Player\VerificationEmissionProviderFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:email-verification:check-config', description: 'Vérifie la configuration de confirmation versionnée, sans envoyer de mail.')]
final class VerificationEmissionCheckCommand extends Command
{
    public function __construct(
        private readonly VerificationEmissionProviderFactory $factory,
        #[Autowire('%env(bool:EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED)%')] private readonly bool $versioned,
        #[Autowire('%env(bool:EMAIL_VERIFICATION_MIXED_READER_ENABLED)%')] private readonly bool $mixedReader,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->versioned) {
            $output->writeln('Émission classique active. Aucun transport versionné vérifié.');
            return Command::SUCCESS;
        }
        if (!$this->mixedReader) {
            $output->writeln('Activez d’abord le lecteur mixte (EMAIL_VERIFICATION_MIXED_READER_ENABLED).');
            return Command::FAILURE;
        }
        try {
            $this->factory->create();
        } catch (\DomainException $error) {
            $output->writeln($error->getMessage());
            return Command::FAILURE;
        }

        $output->writeln('Configuration versionnée construite. Aucun contact SMTP et aucune remise vérifiés.');
        return Command::SUCCESS;
    }
}
