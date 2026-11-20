<?php

namespace App\Service\Player;

/** Contrat interne de remise à usage unique et d'audit transactionnel. */
interface VerificationRecoveryDeliveryBinding
{
    /** @return array{result: string, player_id?: int, link?: string} */
    public function prepareRecoveryDelivery(
        int $requestId,
        #[\SensitiveParameter] string $claimToken,
        #[\SensitiveParameter] VerificationLinkKeyResolver $keys,
    ): array;

    public function completeRecoveryDelivery(
        int $requestId,
        #[\SensitiveParameter] string $claimToken,
        VerificationRecoveryOutcome $outcome,
    ): string;
}
