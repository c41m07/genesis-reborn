<?php

namespace App\Service\Player;

use App\Entity\Player;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Sélecteur unique pour inscription et renvoi public, sans SMTP raccordé. */
final class EmailVerificationEmissionSelector
{
    public function __construct(
        private EmailVerificationService $legacy,
        private VerificationGenerationStore $writer,
        private VerificationRequestReservation $reservations,
        private VerificationRequestLifecycle $lifecycle,
        private ClockInterface $clock,
        #[Autowire('%env(bool:EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED)%')] private bool $versioned,
        #[Autowire('%env(bool:EMAIL_VERIFICATION_MIXED_READER_ENABLED)%')] private bool $reader,
        #[\SensitiveParameter] private ?VerificationEmissionProvider $provider = null,
    ) {
    }

    public function sendWelcomeAndVerification(Player $player, bool $welcome = true): bool
    {
        if (!$this->versioned) {
            return $this->legacy->sendWelcomeAndVerification($player, $welcome);
        }
        if (!$this->reader || $this->provider === null || $player->id === null) {
            // Aucun chemin historique ne peut être sélectionné par erreur.
            // Le contrôleur conserve l'avertissement usuel à l'inscription.
            return false;
        }
        $prepared = $this->writer->prepareForEmission(
            $player->id, $this->provider->keyId, $this->clock->now()->modify('+24 hours'), $this->provider->keys,
        );
        if (!in_array($prepared['result'], ['created', 'reused'], true)) {
            return false;
        }

        // Le limiteur public continue à s'appliquer dans le contrôleur.
        // Ici, le compte sérialise les remises et ne réessaie jamais seul.
        $reservation = $this->reservations->reserve(
            $player->id, bin2hex(random_bytes(32)), 300, generationVersion: $prepared['generation']->version,
        );
        if ($reservation['result'] !== 'reserved') {
            return false;
        }
        $id = $reservation['request_id'];
        $claim = $this->lifecycle->claim($id, 120);
        if ($claim['result'] !== 'claimed') {
            return false;
        }
        $snapshot = $this->lifecycle->prepareVersionedEmission($id, $claim['claim_token'], $this->provider->keys);
        if ($snapshot === null) {
            $this->lifecycle->complete($id, $claim['claim_token'], 'cancelled');
            return false;
        }

        try {
            $outcome = $this->provider->transport->confirmation($snapshot['email'], $snapshot['link'], $snapshot['username']);
        } catch (\Throwable) {
            // Une exception peut survenir après une remise : pas de retry.
            $outcome = VerificationRecoveryOutcome::Unknown;
        }
        $resolved = $this->lifecycle->complete(
            $id, $claim['claim_token'],
            $outcome === VerificationRecoveryOutcome::NotDispatched ? 'cancelled' : $outcome->value,
        );
        if ($welcome) {
            try {
                $this->provider->transport->welcome($snapshot['email'], $snapshot['username']);
            } catch (\Throwable) {
                // Message distinct et sans secret de confirmation. Ne pas
                // répéter une exception de fournisseur ni lancer de reprise.
            }
        }

        return $outcome === VerificationRecoveryOutcome::TransportAccepted && $resolved['result'] === 'completed';
    }
}
