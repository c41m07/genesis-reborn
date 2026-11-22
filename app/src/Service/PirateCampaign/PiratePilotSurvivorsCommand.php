<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateAttackCampaign, Planet, PlayerUniverse};
use App\Repository\PirateCampaignReadRepository;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Security\Csrf\{CsrfToken, CsrfTokenManagerInterface};

/** Commander un repli seulement pour un lot dont l'immobilisation a été attestée. */
#[When(env: 'dev')]
#[When(env: 'prod')]
final readonly class PiratePilotSurvivorsCommand
{
    public function __construct(
        private PiratePilotHistoricalAccess $access,
        private PiratePilotGate $gate,
        private PiratePilotDestinationSelection $destinations,
        private PirateCampaignCatalogue $catalogue,
        private PirateCampaignReadRepository $campaigns,
        private PirateSurvivorRecoveryEvidence $evidence,
        private PirateSurvivorRecoveryService $recovery,
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
            [$attack, $evidence, $options, $planets, $proof] = $context;
            $intent = $this->intent($member, $attack, $evidence, $mission, array_key_first($planets), $proof, $options);

            return ['action' => 'survivors', 'token' => $this->csrf->getToken('select:'.$intent)->getValue(),
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
            [$attack, $evidence, $options, $planets, $proof] = $context;
            $choice = PiratePilotCommandInput::parse($body, $options)?->destinationChoice;
            if ($choice === null || ($target = $planets[$options->colonyId($choice)] ?? null) === null) {
                return null;
            }
            if (!$execute && !$this->csrf->isTokenValid(new CsrfToken(
                'select:'.$this->intent($member, $attack, $evidence, $mission, array_key_first($planets), $proof, $options), $token,
            ))) {
                return null;
            }
            $quote = $this->recovery->preview($member, $attack->id, $evidence->attempt, $evidence->carrierRevision, $target->id, $this->gate);
            if ($quote === null) {
                return null;
            }
            $intent = 'confirm:'.$this->intent($member, $attack, $evidence, $mission, $target->id, $quote['digest'], $options);
            if ($execute) {
                return $this->csrf->isTokenValid(new CsrfToken($intent, $token))
                    && $this->recovery->launch($member, $attack->id, $evidence->attempt, $evidence->carrierRevision, $target->id, $quote['digest'], $this->gate);
            }

            return ['action' => 'survivors', 'token' => $this->csrf->getToken($intent)->getValue(),
                'choice' => $choice, 'target' => $target->name, 'seconds' => $quote['seconds'],
                'hydrogen' => $quote['hydrogen'], 'remaining' => $quote['remaining']];
        });
    }

    private function context(PlayerUniverse $member, \DateTimeImmutable $at, int $mission): ?array
    {
        $key = $this->catalogue->keys()[$mission - 1] ?? null;
        $rows = $key === null ? [] : $this->campaigns->ownedRows($member, [$key], $at);
        $attack = count($rows) === 1 ? $rows[0]['attack'] : null;
        if (!$attack instanceof PirateAttackCampaign || isset($attack->timeline['pilot_recovery'])) {
            return null;
        }
        $evidence = $this->evidence->read($attack, $member, $at)[0] ?? null;
        if ($evidence === null) {
            return null;
        }
        $selection = $this->destinations->forOwner($member, PiratePilotCommandAction::SurvivorFallback,
            static fn(Planet $p): bool => $p->id !== $attack->originId && $p->solarSystem->galaxy->id === $evidence->position[1]);
        if ($selection === null) {
            return null;
        }
        $proof = hash('sha256', json_encode([$attack->timeline['pilot_blocked_return'], $attack->reservedShips], JSON_THROW_ON_ERROR));

        return [$attack, $evidence, ...$selection, $proof];
    }

    private function intent(
        PlayerUniverse $member, PirateAttackCampaign $attack, PirateBlockedReturnEvidence $evidence, int $mission,
        int $target, string $proof, PiratePilotCommandOptions $options,
    ): string {
        $quoteVersion = hash('sha256', $attack->id.':'.$member->universe->id.':'.$evidence->carrierRevision.':'.$proof);

        return (new PiratePilotCommandIntent($member->player->id, $member->id, $target,
            $mission, $evidence->attempt, $quoteVersion, $options))->csrfId();
    }
}
