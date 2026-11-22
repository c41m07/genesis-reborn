<?php

namespace App\Security;

use App\Entity\Player;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class AdminCapabilityVoter extends Voter
{
    public function __construct(private AdminCapabilityPolicy $policy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Revendiquer aussi les noms inconnus du namespace pour les refuser explicitement.
        return str_starts_with($attribute, 'ADMIN_CAPABILITY_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $actor = $token->getUser();
        // Ce premier lot ne définit pas de portée par univers ou par cible.
        // Refuser un sujet inattendu plutôt que prétendre en contrôler la propriété.
        return $subject === null && $actor instanceof Player && $this->policy->allows($actor, $attribute);
    }
}
