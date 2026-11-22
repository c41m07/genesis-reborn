<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Planet;
use App\Service\PirateCampaign\{PiratePilotActiveColony, PiratePilotAttackCommand, PiratePilotConvoyDepositCommand, PiratePilotConvoyRouteCommand, PiratePilotEncounterForms, PiratePilotLocalReader, PiratePilotScoutCommand, PiratePilotSurvivorsCommand};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[When(env: 'dev')]
final class PiratePilotLocalController extends AbstractController
{
    private const PRIVATE_HEADERS = ['Cache-Control' => 'private, no-store'];

    #[Route('/evenements/campagne-pirate/{missionNumber}', name: 'app_dev_pirate_pilot',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['GET'], env: 'dev')]
    public function dossier(
        Request $request, PiratePilotLocalReader $reader, PiratePilotActiveColony $colonies,
        PiratePilotEncounterForms $forms, ?int $missionNumber = null, ?PiratePilotConvoyDepositCommand $deposit = null,
        ?PiratePilotConvoyRouteCommand $route = null,
        ?PiratePilotSurvivorsCommand $survivors = null,
    ): Response {
        $dossier = $reader->dossier($missionNumber);
        if ($dossier === null) {
            return $this->missing();
        }
        // L'historique reste consultable : il n'instancie aucun aperçu ni jeton de commande.
        $current = $dossier['current'] !== null && $dossier['selected'] === $dossier['current'];
        $origin = $current ? $colonies->forRequest($request) : null;
        $form = $origin === null ? null : ($forms->scoutConfirmation($origin, $dossier['selected'])
            ?? $forms->attackChoices($origin, $dossier['selected']));

        $state = $dossier['convoy']['selected_reward']['state'] ?? null;
        $depositToken = $state === 'full_pending'
            ? $deposit?->confirmation($dossier['selected']) : null;

        $routeForm = in_array($state, ['unassigned', 'destination_lost'], true) ? $route?->choices($dossier['selected']) : null;

        $survivorForm = ($dossier['briefing']['may_offer_recovery'] ?? false) ? $survivors?->choices($dossier['selected']) : null;

        return $this->page($dossier, $form, $origin, $current && $origin === null, $depositToken, $routeForm, $survivorForm);
    }

    #[Route('/evenements/campagne-pirate/{missionNumber}/sonde', name: 'app_dev_pirate_pilot_scout',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['POST'], env: 'dev')]
    public function scout(
        Request $request, int $missionNumber, PiratePilotLocalReader $reader, PiratePilotActiveColony $colonies,
        PiratePilotScoutCommand $scout,
    ): Response {
        $scope = $this->scope($request, $missionNumber, $reader, $colonies);
        if ($scope instanceof Response) {
            return $scope;
        }
        $body = $this->body($request);
        try {
            if ($body === null || !$scout->execute($scope[1], $missionNumber, $body[0], $body[1])) {
                return $this->stale();
            }
        } catch (\DomainException) {
            return $this->stale();
        }

        return $this->afterCommand($missionNumber);
    }

    #[Route('/evenements/campagne-pirate/{missionNumber}/attaque/apercu', name: 'app_dev_pirate_pilot_attack_preview',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['POST'], env: 'dev')]
    public function attackPreview(
        Request $request, int $missionNumber, PiratePilotLocalReader $reader, PiratePilotActiveColony $colonies,
        PiratePilotEncounterForms $forms,
    ): Response {
        $scope = $this->scope($request, $missionNumber, $reader, $colonies);
        if ($scope instanceof Response) {
            return $scope;
        }
        $body = $this->body($request);
        $card = $body === null ? null : $forms->attackConfirmation($scope[1], $missionNumber, $body[0], $body[1]);
        if ($card === null) {
            return $this->stale();
        }

        return $this->page($scope[0], $card, $scope[1], false);
    }

    #[Route('/evenements/campagne-pirate/{missionNumber}/attaque', name: 'app_dev_pirate_pilot_attack',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['POST'], env: 'dev')]
    public function attack(
        Request $request, int $missionNumber, PiratePilotLocalReader $reader, PiratePilotActiveColony $colonies,
        PiratePilotAttackCommand $attack,
    ): Response {
        $scope = $this->scope($request, $missionNumber, $reader, $colonies);
        if ($scope instanceof Response) {
            return $scope;
        }
        $body = $this->body($request);
        try {
            if ($body === null || !$attack->execute($scope[1], $missionNumber, $body[0], $body[1])) {
                return $this->stale();
            }
        } catch (\DomainException) {
            return $this->stale();
        }

        return $this->afterCommand($missionNumber);
    }

    #[Route('/evenements/campagne-pirate/{missionNumber}/convoi/depot', name: 'app_dev_pirate_pilot_convoy_deposit',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['POST'], env: 'dev')]
    public function convoyDeposit(
        Request $request, int $missionNumber, PiratePilotLocalReader $reader, PiratePilotConvoyDepositCommand $deposit,
    ): Response {
        $dossier = $reader->dossier($missionNumber);
        if ($dossier === null) {
            return $this->missing();
        }
        $body = $this->body($request);
        try {
            if (($dossier['convoy']['selected_reward']['state'] ?? null) !== 'full_pending'
                || $body === null || !$deposit->execute($missionNumber, $body[0], $body[1])) {
                return $this->stale();
            }
        } catch (\DomainException) {
            return $this->stale();
        }

        return $this->afterCommand($missionNumber);
    }

    #[Route('/evenements/campagne-pirate/{missionNumber}/convoi/destination/apercu', name: 'app_dev_pirate_pilot_convoy_route_preview',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['POST'], env: 'dev')]
    public function convoyRoutePreview(
        Request $request, int $missionNumber, PiratePilotLocalReader $reader, PiratePilotConvoyRouteCommand $route,
    ): Response {
        return $this->convoyRouteAction($request, $missionNumber, $reader, $route, false);
    }

    #[Route('/evenements/campagne-pirate/{missionNumber}/convoi/destination', name: 'app_dev_pirate_pilot_convoy_route',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['POST'], env: 'dev')]
    public function convoyRoute(
        Request $request, int $missionNumber, PiratePilotLocalReader $reader, PiratePilotConvoyRouteCommand $route,
    ): Response {
        return $this->convoyRouteAction($request, $missionNumber, $reader, $route, true);
    }

    private function convoyRouteAction(
        Request $request, int $mission, PiratePilotLocalReader $reader, PiratePilotConvoyRouteCommand $route, bool $execute,
    ): Response {
        $dossier = $reader->dossier($mission);
        if ($dossier === null) {
            return $this->missing();
        }
        $body = $this->body($request);
        if ($body === null || !in_array($dossier['convoy']['selected_reward']['state'] ?? null,
            ['unassigned', 'destination_lost'], true)) {
            return $this->stale();
        }
        try {
            if ($execute) {
                return $route->execute($mission, $body[0], $body[1]) ? $this->afterCommand($mission) : $this->stale();
            }
            $choice = $route->preview($mission, $body[0], $body[1]);

            return $choice === null ? $this->stale() : $this->page($dossier, null, null, false, null, $choice);
        } catch (\DomainException) {
            return $this->stale();
        }
    }

    #[Route('/evenements/campagne-pirate/{missionNumber}/repli/apercu', name: 'app_dev_pirate_pilot_survivors_preview',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['POST'], env: 'dev')]
    public function survivorsPreview(
        Request $request, int $missionNumber, PiratePilotLocalReader $reader, PiratePilotSurvivorsCommand $survivors,
    ): Response {
        return $this->survivorAction($request, $missionNumber, $reader, $survivors, false);
    }

    #[Route('/evenements/campagne-pirate/{missionNumber}/repli', name: 'app_dev_pirate_pilot_survivors',
        requirements: ['missionNumber' => '[1-9][0-9]{0,8}'], methods: ['POST'], env: 'dev')]
    public function survivors(
        Request $request, int $missionNumber, PiratePilotLocalReader $reader, PiratePilotSurvivorsCommand $survivors,
    ): Response {
        return $this->survivorAction($request, $missionNumber, $reader, $survivors, true);
    }

    private function survivorAction(
        Request $request, int $mission, PiratePilotLocalReader $reader, PiratePilotSurvivorsCommand $survivors, bool $execute,
    ): Response {
        $dossier = $reader->dossier($mission);
        if ($dossier === null) {
            return $this->missing();
        }
        $body = $this->body($request);
        if ($body === null || !($dossier['briefing']['may_offer_recovery'] ?? false)) {
            return $this->stale();
        }
        try {
            if ($execute) {
                return $survivors->execute($mission, $body[0], $body[1]) ? $this->afterCommand($mission) : $this->stale();
            }
            $choice = $survivors->preview($mission, $body[0], $body[1]);

            return $choice === null ? $this->stale() : $this->page($dossier, null, null, false, null, null, $choice);
        } catch (\DomainException) {
            return $this->stale();
        }
    }

    private function scope(Request $request, int $mission, PiratePilotLocalReader $reader, PiratePilotActiveColony $colonies): array|Response
    {
        $dossier = $reader->dossier($mission);
        if ($dossier === null) {
            return $this->missing();
        }
        if ($dossier['current'] !== $mission || $dossier['selected'] !== $mission) {
            return $this->stale();
        }
        $origin = $colonies->forRequest($request);

        return $origin === null ? $this->stale() : [$dossier, $origin];
    }

    /** Extraction volontaire d'un formulaire HTML normal ; le service vérifie le contenu restant. */
    private function body(Request $request): ?array
    {
        $post = $request->request->all();
        $token = $post['_token'] ?? null;
        if (!is_string($token) || $token === '' || strlen($token) > 512) {
            return null;
        }
        unset($post['_token']);

        return [$post, $token];
    }

    private function page(array $dossier, ?array $form, ?Planet $origin, bool $needsColony, ?string $depositToken = null, ?array $destinationForm = null, ?array $survivorForm = null): Response
    {
        return $this->render('player/pirate_campaign/pilot_local.html.twig', [
            'dossier' => $dossier, 'encounter_form' => $form,
            'origin_label' => $origin?->name, 'needs_colony' => $needsColony, 'deposit_token' => $depositToken, 'destination_form' => $destinationForm, 'survivor_form' => $survivorForm,
        ], new Response(headers: self::PRIVATE_HEADERS));
    }

    private function missing(): Response
    {
        return new Response('Page introuvable.', Response::HTTP_NOT_FOUND, self::PRIVATE_HEADERS);
    }

    private function stale(): Response
    {
        return new Response('Cette commande n’est plus disponible. Rouvrez le dossier de campagne.', Response::HTTP_CONFLICT, self::PRIVATE_HEADERS);
    }

    private function afterCommand(int $mission): Response
    {
        $result = $this->redirectToRoute('app_dev_pirate_pilot', ['missionNumber' => $mission], Response::HTTP_SEE_OTHER);
        $result->headers->add(self::PRIVATE_HEADERS);

        return $result;
    }
}
