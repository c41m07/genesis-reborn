<?php

namespace App\Repository;

use App\Entity\{EmailVerificationToken, PasswordResetToken, Player};
use Doctrine\ORM\EntityManagerInterface;

final class AdminAccountDiagnosticRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return array{verification: ?\DateTimeImmutable, password_reset: ?\DateTimeImmutable} */
    public function latestExpirations(Player $player): array
    {
        return [
            'verification' => $this->latestExpiration(EmailVerificationToken::class, $player),
            'password_reset' => $this->latestExpiration(PasswordResetToken::class, $player),
        ];
    }

    private function latestExpiration(string $tokenClass, Player $player): ?\DateTimeImmutable
    {
        // Sélectionner uniquement l'échéance : aucun hachage de jeton n'est hydraté pour le support.
        $rows = $this->em->createQueryBuilder()
            ->select('token.expiresAt AS expiresAt')
            ->from($tokenClass, 'token')
            ->where('token.player = :player')
            ->setParameter('player', $player)
            ->orderBy('token.expiresAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getArrayResult();

        return $rows[0]['expiresAt'] ?? null;
    }
}
