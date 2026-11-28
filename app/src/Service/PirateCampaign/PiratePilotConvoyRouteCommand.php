<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateConvoyDelivery, Planet, PlayerUniverse};
use App\Repository\PirateConvoyReadRepository;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Security\Csrf\{CsrfToken, CsrfTokenManagerInterface};

/** Affectation ou nouvelle destination du convoi historique, jamais redirection d'un trajet en cours. */
#[When(env: 'dev')]
#[When(env: 'prod')]
final readonly class PiratePilotConvoyRouteCommand
{
    public function __construct(
        private PiratePilotHistoricalAccess $access,
        private PiratePilotDestinationSelection $destinations,
        private PirateCampaignCatalogue $catalogue,
        private PirateConvoyReadRepository $rights,
        private PirateConvoyDeliveryService $deliveries,
        private CsrfTokenManagerInterface $csrf,
    ) {
    }

    public function choices(int $mission): ?array
    {
        return $this->access->whileCurrent(function (PlayerUniverse $member, \DateTimeImmutable $at) use ($mission): ?array {
            $context = $this->context($member, $at, $mission);
            if ($context === null) {
                return null;
            }
            [$right, $options, $planets, $proof] = $context;
            $intent = $this->intent($right, $mission, array_key_first($planets), $proof, $options);

            return ['action' => 'convoy_route', 'token' => $this->csrf->getToken('select:'.$intent)->getValue(),
                'choices' => $this->destinations->labels($planets)];
        });
    }

    public function preview(int $mission, array $body, string $token): ?array
    {
        return $this->selection($mission, $body, $token, false);
    }

    public function execute(int $mission, array $body, string $token): bool
    {
        return $this->selection($mission, $body, $token, true) === true;
    }

    private function selection(int $mission, array $body, string $token, bool $execute): array|bool|null
    {
        return $this->access->whileCurrent(function (PlayerUniverse $member, \DateTimeImmutable $at) use ($mission, $body, $token, $execute): array|bool|null {
            $context = $this->context($member, $at, $mission);
            if ($context === null) {
                return null;
            }
            [$right, $options, $planets, $proof] = $context;
            $choice = PiratePilotCommandInput::parse($body, $options)?->destinationChoice;
            if ($choice === null || ($target = $planets[$options->colonyId($choice)] ?? null) === null) {
                return null;
            }
            if (!$execute && !$this->csrf->isTokenValid(new CsrfToken(
                'select:'.$this->intent($right, $mission, array_key_first($planets), $proof, $options), $token,
            ))) {
                return null;
            }
            $quote = $this->deliveries->previewOwnedDestination($member, $right, $target);
            if ($quote === null) {
                return null;
            }
            $intent = 'confirm:'.$this->intent($right, $mission, $target->id, $quote['digest'], $options);
            if ($execute) {
                return $this->csrf->isTokenValid(new CsrfToken($intent, $token))
                    && $this->deliveries->chooseOwnedDestination($member, $right->id, $target->id, $quote['digest']) === 'traveling';
            }

            return ['action' => 'convoy_route', 'token' => $this->csrf->getToken($intent)->getValue(),
                'choice' => $choice, 'target' => $target->name, 'seconds' => $quote['seconds']];
        });
    }

    private function context(PlayerUniverse $member, \DateTimeImmutable $at, int $mission): ?array
    {
        $key = $this->catalogue->keys($member->universe)[$mission - 1] ?? null;
        $right = $key === null ? null : $this->rights->forOwnedMission($member, $key, $at);
        if ($right === null) {
            return null;
        }
        try {
            $timeline = $right->timeline();
        } catch (\DomainException) {
            return null;
        }
        $point = $timeline->attestedPositionForDeparture();
        $state = $timeline->checkpoint();
        if ($point === null || new \DateTimeImmutable($state['transition_at']) > $at) {
            return null;
        }
        $selection = $this->destinations->forOwner($member, PiratePilotCommandAction::ChooseConvoyDestination,
            static fn(Planet $p): bool => $p->id !== $state['destination'] && $p->solarSystem->galaxy->id === $point[1]);
        if ($selection === null) {
            return null;
        }
        $digest = hash('sha256', json_encode([$right->id, $right->universeId, $state, $timeline->quote->exactParts()], JSON_THROW_ON_ERROR));

        return [$right, ...$selection, $digest];
    }

    private function intent(PirateConvoyDelivery $right, int $mission, int $target, string $proof, PiratePilotCommandOptions $options): string
    {
        $quoteVersion = hash('sha256', $right->id.':'.$right->universeId.':'.$proof);

        return (new PiratePilotCommandIntent($right->ownerId, $right->membershipId, $target,
            $mission, $right->winningAttempt, $quoteVersion, $options))->csrfId();
    }
}
