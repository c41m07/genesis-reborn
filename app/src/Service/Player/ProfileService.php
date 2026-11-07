<?php
namespace App\Service\Player;
use App\Entity\Player;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Validator\DTO\ProfileData;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
final class ProfileService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private UserPasswordHasherInterface $hasher) {}
    public function update(Player $player,ProfileData $data):void
    {
        $this->manager->transaction(function()use($player,$data){
            $this->manager->lock($player);
            if(!$this->hasher->isPasswordValid($player,$data->currentPassword)) throw new \DomainException('Mot de passe actuel incorrect.');
            $email=mb_strtolower(trim($data->email));$name=trim($data->username);
            foreach(['email'=>$email,'username'=>$name] as $field=>$value) {
                $existing=$this->repo->one(Player::class,[$field=>$value]);
                if($existing && $existing->id!==$player->id) throw new \DomainException('Cette adresse email ou ce pseudo est déjà utilisé.');
            }
            $player->email=$email;$player->username=$name;
            if($data->newPassword!==null && $data->newPassword!=='') $player->password=$this->hasher->hashPassword($player,$data->newPassword);
        });
    }
}
