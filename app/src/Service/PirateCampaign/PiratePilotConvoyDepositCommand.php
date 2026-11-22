<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateConvoyDelivery, PlayerUniverse};
use App\Repository\PirateConvoyReadRepository;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Security\Csrf\{CsrfToken, CsrfTokenManagerInterface};

/** Retenter la remise du lot déjà arrivé, à sa destination attestée. */
#[When(env: 'dev')]
#[When(env: 'prod')]
final readonly class PiratePilotConvoyDepositCommand
{
    public function __construct(
        private PiratePilotHistoricalAccess $access,
        private PirateCampaignCatalogue $catalogue,
        private PirateConvoyReadRepository $rights,
        private PirateConvoyDeliveryService $deliveries,
        private CsrfTokenManagerInterface $csrf,
    ) {
    }

    public function confirmation(int $mission): ?string
    {
        return $this->access->whileCurrent(function (PlayerUniverse $member, \DateTimeImmutable $at) use ($mission): ?string {
            $pending = $this->pending($member, $at, $mission);

            return $pending === null ? null : $this->csrf->getToken($pending[1])->getValue();
        });
    }

    public function execute(int $mission, array $body, string $token): bool
    {
        if (PiratePilotCommandInput::parse($body, self::options()) === null) {
            return false;
        }

        return $this->access->whileCurrent(function (PlayerUniverse $member, \DateTimeImmutable $at) use ($mission, $token): bool {
            $pending = $this->pending($member, $at, $mission);
            if ($pending === null || !$this->csrf->isTokenValid(new CsrfToken($pending[1], $token))) {
                return false;
            }
            // Les mêmes verrous empire et univers couvrent la relecture du droit et sa remise native.
            return $this->deliveries->retryDeposit($member, $pending[0]->id) !== null;
        }) === true;
    }

    /** @return array{PirateConvoyDelivery,string}|null */
    private function pending(PlayerUniverse $member, \DateTimeImmutable $at, int $mission): ?array
    {
        $key = $this->catalogue->keys()[$mission - 1] ?? null;
        if ($key === null) {
            return null;
        }
        $right = $this->rights->forOwnedMission($member, $key, $at);
        if ($right === null) {
            return null;
        }
        try {
            $timeline = $right->timeline();
        } catch (\DomainException) {
            return null;
        }
        $state = $timeline->checkpoint();
        if ($timeline->state() !== 'waiting_capacity' || !is_int($destination = $state['destination'])
            || new \DateTimeImmutable($state['transition_at']) > $at) {
            return null;
        }
        $proof = hash('sha256', json_encode([$right->id, $right->universeId, $state, $timeline->quote->exactParts()], JSON_THROW_ON_ERROR));
        $intent = new PiratePilotCommandIntent($right->ownerId, $right->membershipId, $destination,
            $mission, $right->winningAttempt, $proof, self::options());

        return [$right, $intent->csrfId()];
    }

    private static function options(): PiratePilotCommandOptions
    {
        return PiratePilotCommandOptions::noChoices(PiratePilotCommandAction::RetryDeposit);
    }
}
