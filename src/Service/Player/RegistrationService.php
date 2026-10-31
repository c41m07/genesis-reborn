<?php
namespace App\Service\Player;
use App\Entity\Player;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Validator\DTO\RegistrationData;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
final class RegistrationService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private UserPasswordHasherInterface $hasher) {}
    public function register(RegistrationData $data): Player
    {
        $email=mb_strtolower(trim($data->email));$username=trim($data->username);
        if($this->repo->one(Player::class,['email'=>$email]) || $this->repo->one(Player::class,['username'=>$username])) throw new \DomainException('Cette adresse email ou ce pseudo est déjà utilisé.');
        $p=new Player();$p->email=$email;$p->username=$username;$p->password=$this->hasher->hashPassword($p,$data->plainPassword);
        $this->manager->transaction(fn()=>$this->manager->save($p));return $p;
    }
}
