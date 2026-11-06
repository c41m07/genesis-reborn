<?php
namespace App\Security;

use App\Entity\Player;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class PlayerUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof Player && $user->emailVerifiedAt === null) {
            throw new CustomUserMessageAccountStatusException('Confirmez votre adresse email avant de vous connecter.');
        }
        if ($user instanceof Player && $user->lockedAt !== null) {
            throw new CustomUserMessageAccountStatusException('Ce compte est temporairement suspendu.');
        }
    }

    public function checkPostAuth(UserInterface $user): void {}
}
