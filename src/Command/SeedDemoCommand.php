<?php
namespace App\Command;
use App\Entity\{Player,Universe};
use App\Repository\GameRepository;
use App\Service\Player\RegistrationService;
use App\Service\Universe\UniverseService;
use App\Validator\DTO\RegistrationData;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:seed-demo',description:'Créer explicitement un compte de démonstration (développement local seulement).')]
final class SeedDemoCommand extends Command
{
    public function __construct(private GameRepository $repo,private RegistrationService $registration,private UniverseService $universes,#[Autowire('%kernel.environment%')] private string $environment) { parent::__construct(); }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        if($this->environment!=='dev') { $output->writeln('Commande réservée à APP_ENV=dev.');return Command::FAILURE; }
        $u=$this->repo->one(Universe::class,['slug'=>'turbo-1']);
        if(!$u) { $output->writeln('Lancer app:universe:import avant le seed.');return Command::FAILURE; }
        $email='explorateur@genesis.local';$p=$this->repo->one(Player::class,['email'=>$email]);
        if(!$p) { $d=new RegistrationData();$d->email=$email;$d->username='Explorateur';$d->plainPassword='Genesis-Demo-2026!';$p=$this->registration->register($d);$output->writeln('Compte créé : '.$email.' / Genesis-Demo-2026!'); }
        else $output->writeln('Compte existant conservé (mot de passe inchangé).');
        if(!$p->currentPlayerUniverse) $this->universes->select($p,$u->id);
        return Command::SUCCESS;
    }
}
