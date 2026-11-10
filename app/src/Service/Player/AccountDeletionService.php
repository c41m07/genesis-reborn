<?php
namespace App\Service\Player;

use App\Entity\{AdminAuditLog, EmailVerificationToken, PasswordResetToken, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountDeletionService
{
    public function __construct(private GameManager $manager, private GameRepository $repo, private EntityManagerInterface $em, private EmpireConversionService $conversion, private UserPasswordHasherInterface $hasher, private ClockInterface $clock, private \App\Service\Colony\ResourceService $resources) {}

    public function delete(Player $player,string $password,bool $confirmed): void
    {
        if (!$confirmed) throw new \DomainException('Confirmez explicitement la suppression définitive de votre compte.');
        $this->manager->transaction(function () use ($player,$password): void {
            // Sérialiser les suppressions d'administrateurs pour conserver un accès d'administration.
            $admins=$this->em->getConnection()->fetchFirstColumn('SELECT id FROM player WHERE JSON_CONTAINS(roles, :role) = 1 AND locked_at IS NULL ORDER BY id FOR UPDATE',['role'=>'"ROLE_ADMIN"']);
            $this->manager->lock($player);
            if ($player->lockedAt!==null || !$this->hasher->isPasswordValid($player,$password)) throw new \DomainException('Mot de passe actuel incorrect ou compte indisponible.');
            if (in_array('ROLE_ADMIN',$player->getRoles(),true) && count($admins)<2) throw new \DomainException('Le dernier administrateur actif ne peut pas supprimer son compte. Désignez d’abord un autre administrateur.');
            foreach ($this->repo->all(PlayerUniverse::class,['player'=>$player],['id'=>'ASC']) as $membership) {
                $result=$this->conversion->convertForAccountDeletion($membership);
                if (!in_array($result['status'],['converted','already_converted'],true)) throw new \DomainException('Suppression impossible pendant un combat en cours. Réessayez après sa résolution.');
                if ($membership->universe->isAccessible($this->clock->now())) {
                    foreach ($this->repo->planets($membership->universe,$membership) as $planet) {
                        $this->resources->materialize($planet,$this->clock->now());$this->resources->refreshProduction($planet);
                    }
                }
            }
            foreach ($this->repo->all(PlayerUniverse::class,['formerPlayer'=>$player]) as $membership) $membership->formerPlayer=null;
            foreach ([EmailVerificationToken::class,PasswordResetToken::class] as $class) {
                foreach ($this->repo->all($class,['player'=>$player]) as $token) $this->manager->remove($token);
            }
            $player->email='deleted-'.$player->id.'-'.bin2hex(random_bytes(12)).'@deleted.invalid';
            $player->username='Compte supprimé #'.$player->id;
            $player->password=$this->hasher->hashPassword($player,bin2hex(random_bytes(32)));
            $player->emailVerifiedAt=null;$player->roles=[];$player->preferences=[];
            $player->currentPlayerUniverse=null;$player->lockedAt=$this->clock->now();
            $entry=new AdminAuditLog();$entry->actor=$player;$entry->action='player_account_deleted';$entry->targetType='player';
            $entry->targetId=$player->id;$entry->createdAt=$this->clock->now();$entry->details=['empires'=>'forgotten','identity'=>'erased'];
            $this->manager->save($entry);
        });
    }
}
