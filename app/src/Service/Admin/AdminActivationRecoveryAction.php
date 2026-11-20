<?php

namespace App\Service\Admin;

use App\Entity\Player;
use App\Service\Player\{VerificationRecoveryDeliveryAdapter, VerificationRecoveryOrchestrator, VerificationRequestLifecycle};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/** Composition optionnelle du formulaire existant, sans fournisseur intégré. */
final class AdminActivationRecoveryAction implements AdminActivationContextAction
{
    public function __construct(
        private AdminActivationService $normal,
        private AdminActivationFormContext $contexts,
        private RequestStack $requests,
        private VerificationRecoveryOrchestrator $recovery,
        private VerificationRequestLifecycle $lifecycle,
        private VerificationRecoveryDeliveryAdapter $delivery,
        #[Autowire('%env(bool:ADMIN_ACTIVATION_RECOVERY_ENABLED)%')] private bool $recoveryEnabled,
        #[\SensitiveParameter] private ?AdminActivationRecoveryProvider $provider = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->normal->isEnabled() && (!$this->recoveryEnabled || $this->provider !== null);
    }

    public function prepareFormKey(Player $actor, int $playerId): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }
        if (!$this->recoveryEnabled) {
            return bin2hex(random_bytes(32)); // Chemin V7 inchangé.
        }
        $request = $this->requests->getCurrentRequest();
        if ($actor->id === null || $request === null || !$request->hasSession()) {
            return null;
        }

        // Ce repère n'autorise rien ; il sera vérifié de nouveau sous verrou.
        $expected = $this->recovery->candidateForForm($playerId);
        return $expected === null ? null : $this->contexts->issue($request->getSession(), $actor->id, $playerId, $expected);
    }

    public function request(Player $actor, int $playerId, #[\SensitiveParameter] string $key): string
    {
        if (!$this->recoveryEnabled) {
            return $this->normal->request($actor, $playerId, $key);
        }
        if (!$this->normal->isEnabled() || $this->provider === null) {
            // Si le nouveau mode est demandé mais incomplet, ne jamais
            // retomber sur la voie ordinaire qui ignore ce contexte.
            return 'disabled';
        }
        $request = $this->requests->getCurrentRequest();
        $expected = $actor->id !== null && $request !== null && $request->hasSession()
            ? $this->contexts->expectedReceipt($request->getSession(), $actor->id, $playerId, $key)
            : null;
        if ($expected === null) {
            throw new \DomainException('Formulaire périmé. Rechargez la fiche.');
        }

        $reservation = $this->recovery->reserveForAdmin($actor->id, $playerId, $expected, $key, 300, $this->provider->keys);
        if ($reservation['result'] !== 'reserved') {
            return $reservation['result'];
        }
        $id = $reservation['request_id'];
        $claim = $this->lifecycle->claimRecovery($id, 120);
        if ($claim['result'] !== 'claimed') {
            return $claim['result'];
        }
        $outcome = $this->delivery->deliver($id, $claim['claim_token'], $this->lifecycle, $this->provider->keys, $this->provider->transport);
        return $outcome === 'not_dispatched' ? 'recovery_not_dispatched' : $outcome;
    }
}
