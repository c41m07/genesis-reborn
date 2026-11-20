<?php

namespace App\Service\Player;

/** Compose les étapes sans posséder de transport ni de trousseau réel. */
final class VerificationRecoveryDeliveryAdapter
{
    public function deliver(
        int $requestId,
        #[\SensitiveParameter] string $claimToken,
        VerificationRecoveryDeliveryBinding $claims,
        #[\SensitiveParameter] VerificationLinkKeyResolver $keys,
        VerificationRecoveryTransport $transport,
    ): string {
        $prepared = $claims->prepareRecoveryDelivery($requestId, $claimToken, $keys);
        if ($prepared['result'] !== 'handoff') {
            return $prepared['result'];
        }

        // La remise commence après commit. Une révocation qui arrive maintenant
        // invalide le lien, mais ne peut pas annuler un transport déjà lancé.
        try {
            $outcome = $transport->handoff($prepared['email'], $prepared['link'], $prepared['username']);
        } catch (\Throwable) {
            // Une exception ne prouve ni absence de remise ni acceptation.
            // Ne reporter ni message d'exception ni contexte porteur du lien.
            $outcome = VerificationRecoveryOutcome::Unknown;
        } finally {
            unset($prepared);
        }

        return $claims->completeRecoveryDelivery($requestId, $claimToken, $outcome);
    }
}
