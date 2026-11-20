<?php

namespace App\Service\Admin;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/** Contexte borné de la fiche admin, sans accès DB ni effet d'autorisation. */
final class AdminActivationFormContext
{
    private const SESSION_KEY = 'admin_player_activation_contexts';
    private const TTL = 120;
    private const LIMIT = 20;

    public function __construct(private ClockInterface $clock)
    {
    }

    public function issue(SessionInterface $session, int $actorId, int $playerId, int $receiptId): string
    {
        if ($actorId < 1 || $playerId < 1 || $receiptId < 1) {
            throw new \InvalidArgumentException('Contexte de fiche invalide.');
        }
        $contexts = $session->get(self::SESSION_KEY, []);
        $now = $this->clock->now()->getTimestamp();
        $contexts = array_filter($contexts, static fn (array $row): bool => $row['expires'] > $now);
        $key = bin2hex(random_bytes(32)); // Même type de clé opaque que le formulaire existant.
        $contexts[$key] = [
            'session' => hash('sha256', $session->getId()),
            'actor' => $actorId,
            'player' => $playerId,
            'receipt' => $receiptId,
            'expires' => $now + self::TTL,
        ];
        $session->set(self::SESSION_KEY, array_slice($contexts, -self::LIMIT, null, true));

        return $key;
    }

    public function expectedReceipt(SessionInterface $session, int $actorId, int $playerId, #[\SensitiveParameter] string $key): ?int
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $key)) {
            return null;
        }
        $context = $session->get(self::SESSION_KEY, [])[$key] ?? null;
        if ($context === null || $context['actor'] !== $actorId || $context['player'] !== $playerId
            || $context['expires'] <= $this->clock->now()->getTimestamp()
            || !hash_equals($context['session'], hash('sha256', $session->getId()))) {
            return null;
        }

        // Garder le contexte jusqu'à expiration pour que deux soumissions
        // retrouvent la même idempotence en DB. Il ne confère aucun droit.
        return $context['receipt'];
    }
}
