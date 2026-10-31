<?php
namespace App\Command;

use App\Entity\{AdminAuditLog, Player};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Clock\ClockInterface;

#[AsCommand(name: 'app:admin:grant', description: 'Attribuer le rôle administrateur à un compte existant.')]
final class GrantAdminCommand extends Command
{
    public function __construct(private GameManager $manager, private GameRepository $repo, private ClockInterface $clock) { parent::__construct(); }
    protected function configure(): void { $this->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail exacte du compte'); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = trim((string) $input->getArgument('email'));
        $player = $this->repo->one(Player::class, ['email' => $email]);
        if (!$player instanceof Player) { $output->writeln('<error>Compte introuvable.</error>'); return Command::FAILURE; }
        $player->roles = array_values(array_unique([...$player->roles, 'ROLE_ADMIN']));
        $entry = new AdminAuditLog();
        $entry->action = 'admin_role_grant';
        $entry->targetType = 'player';
        $entry->targetId = $player->id;
        $entry->details = ['source' => 'cli'];
        $entry->createdAt = $this->clock->now();
        $this->manager->save($entry);
        $this->manager->flush();
        $output->writeln('<info>Rôle administrateur attribué à '.$player->username.'.</info>');
        return Command::SUCCESS;
    }
}
