<?php

namespace App\Service\Admin;

use App\Entity\Player;
use App\Repository\AdminAccountDiagnosticRepository;
use Psr\Clock\ClockInterface;

final class AdminAccountDiagnosticService
{
    public function __construct(private AdminAccountDiagnosticRepository $repository, private ClockInterface $clock)
    {
    }

    /** @return array{verified_at: ?\DateTimeImmutable, locked_at: ?\DateTimeImmutable, verification: array{state: string, expires_at: ?\DateTimeImmutable}, password_reset: array{state: string, expires_at: ?\DateTimeImmutable}} */
    public function snapshot(Player $player): array
    {
        $expirations = $this->repository->latestExpirations($player);
        $now = $this->clock->now();

        return [
            'verified_at' => $player->emailVerifiedAt,
            'locked_at' => $player->lockedAt,
            // Une confirmation déjà acquise prime sur un éventuel ancien jeton résiduel.
            'verification' => $player->emailVerifiedAt !== null
                ? ['state' => 'not_required', 'expires_at' => null]
                : $this->linkStatus($expirations['verification'], $now),
            'password_reset' => $this->linkStatus($expirations['password_reset'], $now),
        ];
    }

    /** @return array{state: string, expires_at: ?\DateTimeImmutable} */
    private function linkStatus(?\DateTimeImmutable $expiresAt, \DateTimeImmutable $now): array
    {
        return [
            'state' => $expiresAt === null ? 'absent' : ($expiresAt <= $now ? 'expired' : 'active'),
            'expires_at' => $expiresAt,
        ];
    }
}
