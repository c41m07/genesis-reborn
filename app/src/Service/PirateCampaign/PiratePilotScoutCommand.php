<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Planet, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Colony\ActionGuard;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Security\Csrf\{CsrfToken, CsrfTokenManagerInterface};

/** Premier ordre privé du pilote, explicitement sans route ni bouton associé. */
#[When(env: 'dev')]
final readonly class PiratePilotScoutCommand
{
    public function __construct(
        private PiratePilotGate $gate,
        private Security $security,
        private ClockInterface $clock,
        private CsrfTokenManagerInterface $csrf,
        private EntityManagerInterface $em,
        private GameManager $manager,
        private ActionGuard $guard,
        private PiratePilotSignalResolver $signals,
        private PirateReconnaissanceService $scout,
    ) {
    }

    /** Colonie déjà résolue côté serveur, jamais un identifiant pris dans le formulaire. */
    public function preview(Planet $activeOrigin, int $missionNumber): ?array
    {
        $scope = $this->readScope($missionNumber);
        if ($scope === null) {
            return null;
        }

        return $this->manager->transaction(function () use ($scope, $activeOrigin): ?array {
            $plan = $this->prepareLocked($scope[0], $scope[1], $activeOrigin);
            // L'intention reste un objet d'adaptation serveur, jamais un champ fourni par le joueur.
            return $plan === null ? null : ['quote' => $plan['quote'], 'intent' => $plan['intent']];
        });
    }

    /** Le futur adaptateur extrait seulement le vrai _token ; la reconnaissance n'a aucun autre choix POST. */
    public function execute(Planet $activeOrigin, int $missionNumber, ?array $submitted, string $token): bool
    {
        $scope = $this->readScope($missionNumber);
        if ($scope === null || $token === '') {
            return false;
        }
        $options = PiratePilotCommandOptions::noChoices(PiratePilotCommandAction::Reconnaissance);
        try {
            if (PiratePilotCommandInput::parse($submitted, $options) === null) {
                return false;
            }
        } catch (\DomainException) {
            return false;
        }

        return $this->manager->transaction(function () use ($scope, $activeOrigin, $token): bool {
            [$account, $member] = $scope;
            $plan = $this->prepareLocked($account, $member, $activeOrigin);
            if ($plan === null || !$this->csrf->isTokenValid(new CsrfToken($plan['intent']->csrfId(), $token))) {
                return false;
            }
            // La relecture d'ActionGuard et les mêmes verrous dans le lanceur natif ne libèrent
            // rien avant le commit externe. Il garde l'unicité, le prélèvement et l'outbox.
            return $this->scout->launchDefinedWithOmegaPilotFuel(
                $member, $activeOrigin, $plan['signal']->system, $plan['signal']->site,
                PiratePilotSignal::FIRST_KEY, PiratePilotSignal::FIRST_KEY,
            );
        });
    }

    /** @return array{Player,PlayerUniverse}|null */
    private function readScope(int $missionNumber): ?array
    {
        if ($missionNumber !== 1 || !$this->gate->isLocallyEnabled()) {
            return null;
        }
        $account = $this->security->getUser();
        if (!$account instanceof Player || $account->id === null || $account->currentPlayerUniverse?->id === null) {
            return null;
        }

        return [$account, $account->currentPlayerUniverse];
    }

    private function prepareLocked(Player $account, PlayerUniverse $member, Planet $origin): ?array
    {
        if ($origin->id === null) {
            return null;
        }
        try {
            // Comme les commandes natives : participation, univers, planète, sans lecture paresseuse
            // 'current' avant le mutex ni mutation d'activité (pas de UniverseContext).
            $this->guard->lock($member, $origin, currentReads: true);

            // Même règle que DailyRewardService : la sélection et la suppression du compte
            // peuvent tenir le verrou dans un autre ordre. Un NOWAIT laisse l'action en attente,
            // plutôt que saisir un ancien instantané ou constituer un verrou en cycle.
            $locked = $this->em->getConnection()->fetchAssociative(
                'SELECT current_player_universe_id, locked_at, email_verified_at FROM player WHERE id = ? FOR UPDATE NOWAIT',
                [$account->id],
            );
        } catch (\DomainException|LockWaitTimeoutException) {
            return null;
        }
        $at = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        if (!$locked || $locked['locked_at'] !== null || $locked['email_verified_at'] === null
            || (int) $locked['current_player_universe_id'] !== $member->id
            || $origin->npcPlanet || !$this->gate->admits($account, $member, $at)) {
            return null;
        }
        $signal = $this->signals->resolveLocked($member, $origin, $at);
        if ($signal === null || !$signal->allowsFirstScout) {
            return null;
        }
        $quote = $this->scout->pilotScoutQuoteLocked($member, $origin, $signal->system);
        if ($quote === null || !$this->gate->admits($account, $member, $this->clock->now())) {
            return null; // L'univers peut avoir fermé pendant les derniers verrous de devis.
        }
        $digest = hash('sha256', json_encode([
            'version' => 1, 'universe' => $member->universe->id, 'mission' => PiratePilotSignal::FIRST_KEY,
            'origin' => [$origin->solarSystem->id, $origin->solarSystem->galaxy->id, $origin->solarSystem->coordX,
                $origin->solarSystem->coordY, $origin->position],
            'site' => [$signal->system->id, $signal->system->galaxy->id, $signal->system->coordX, $signal->system->coordY],
            'quote' => $quote,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return ['signal' => $signal, 'quote' => $quote, 'intent' => new PiratePilotCommandIntent(
            $account->id, $member->id, $origin->id, 1, 0, $digest,
            PiratePilotCommandOptions::noChoices(PiratePilotCommandAction::Reconnaissance),
        )];
    }
}
