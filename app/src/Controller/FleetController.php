<?php

namespace App\Controller;

use App\Entity\{Fleet, Planet, SolarSystem};
use App\Repository\GameRepository;
use App\Service\Universe\UniverseContext;
use App\Service\Fleet\{FleetService, DestinationCatalogue};
use App\Service\Player\{FirstStepRewardPolicy, PreferencesService};
use App\Service\Catalog\GameCatalog;
use App\Service\Guild\{GuildJumpLink, GuildJumpView};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class FleetController extends AbstractController
{
    #[Route('/flottes', name: 'app_fleets', defaults: ['fleet_page' => 'missions'], methods: ['GET'])]
    #[Route('/flottes/preparer', name: 'app_fleet_prepare', defaults: ['fleet_page' => 'prepare'], methods: ['GET'])]
    #[Route('/flottes/orbites', name: 'app_fleet_orbits', defaults: ['fleet_page' => 'orbits'], methods: ['GET'])]
    #[Route('/flottes/quai', name: 'app_fleet_holds', defaults: ['fleet_page' => 'holds'], methods: ['GET'])]
    public function index(
        Request $request,
        UniverseContext $context,
        \App\Service\Queue\QueueService $queues,
        \App\Service\Fleet\FleetPageView $view,
        \App\Service\Colony\GameShell $shell,
        \App\Service\Colony\LiveRegionResponse $liveResponses,
        GuildJumpLink $jumps,
        GuildJumpView $jumpView,
    ): Response
    {
        $member = $context->membership();
        $page = $request->attributes->getString('fleet_page');
        // Les liens de carte et de composition déjà diffusés gardent leurs paramètres de préparation.
        if ($page === 'missions' && array_intersect(['mission', 'system', 'planet', 'composition'], array_keys($request->query->all()))) {
            return $this->redirectToRoute('app_fleet_prepare', $request->query->all());
        }
        if ($page === 'missions' && $request->query->has('colony')) {
            return $this->redirectToRoute('app_fleet_orbits', $request->query->all());
        }
        $queues->synchronize($member);
        $parameters = [
            'mission' => $request->query->getString('mission', 'explore'), 'system' => $request->query->getInt('system'),
            'planet' => $request->query->getInt('planet'), 'composition' => $request->query->getInt('composition'),
            'origin' => $request->query->getInt('origin'), 'confirmation' => $request->query->getString('confirmation'),
            'adjust' => $request->query->getBoolean('adjust'), 'page' => $request->query->getInt('page', 1),
            'colony' => $request->query->getInt('colony'), 'focus' => $request->query->getInt('fleet'),
            'tutorial_spy' => $request->query->getBoolean('tutorial'),
        ];
        try {
            $data = $view->prepare($member, (int) $request->getSession()->get('selected_planet_'.$member->id, 0), $page, $parameters);
        } catch (\DomainException $error) {
            throw $this->createNotFoundException($error->getMessage());
        }
        if (isset($data['composition_review'])) {
            $this->addFlash('error', 'Les disponibilités ou la composition ont changé. Vérifiez les quantités avant de charger.');
            return $this->redirectToRoute('app_fleet_composition_load', $data['composition_review']);
        }
        $data['jump_origins'] = [];
        if ($page === 'prepare' && !$parameters['tutorial_spy']) {
            foreach ($data['idle'] as $entry) {
                $origin = $entry['fleet']->homePlanet;
                $data['jump_origins'][$origin->id] = $jumps->originEquipped($member, $origin);
            }
        }
        if ($page === 'holds') {
            $data['jump_instability'] = $data['fleet_planet'] instanceof Planet
                ? $jumpView->personalInstability($member, $data['fleet_planet']) : null;
        }
        if ($request->headers->get('X-Genesis-Live') === '1' && $request->getPreferredFormat() === 'json') {
            $data['chrome'] = $shell->get($this->getUser(), $data['fleet_planet'], rememberSelection: false);
            $data['section'] = 'overview';
            $data['preferences'] = array_replace(PreferencesService::DEFAULTS, $this->getUser()->preferences);
            $templates = [
                'colony-selector' => 'ui/colony_selector.html.twig', 'resources' => 'ui/resource_strip.html.twig',
                'fleet-counts' => 'fleet/_counts.html.twig',
            ];
            // Chaque actualisation ne produit que les fragments présents sur cette destination.
            $templates += match ($page) {
                'prepare' => ['available-fleets' => 'fleet/_available.html.twig', 'mission-summary' => 'fleet/_mission_summary.html.twig'],
                'missions' => ['active-flights' => 'ui/flights.html.twig', 'mission-pagination' => 'fleet/_pagination.html.twig'],
                'orbits' => ['orbit-requests' => 'ui/orbit_requests.html.twig'],
                'holds' => ['fleet-holds' => 'fleet/_dock.html.twig', 'fleet-jump-instability' => 'fleet/_jump_instability.html.twig'],
                default => [],
            };
            $regions = [];
            foreach ($templates as $name => $template) {
                $regions[$name] = $this->renderView($template, $data);
            }
            return $liveResponses->create($request, $regions);
        }
        if ($data['fleet_planet']) {
            $request->getSession()->set('selected_planet_'.$member->id, $data['fleet_planet']->id);
        }
        return $this->render('fleet/index.html.twig', $data, new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
    #[Route('/flottes/empire', name: 'app_fleet_empire', methods: ['GET'])]
    public function empire(UniverseContext $context, \App\Repository\EmpireFleetRepository $fleets, GameCatalog $catalog): Response
    {
        $member = $context->membership();
        $ships = $fleets->ships($member);

        return $this->render('fleet/empire.html.twig', [
            'membership' => $member,
            'ships' => $ships,
            'total' => array_sum(array_column($ships, 'quantity')),
            'catalog' => $catalog->forUniverse($member->universe)['catalog']['ships'],
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/flottes/destinations', name: 'app_fleet_destinations', methods: ['GET'])]
    public function destinations(Request $request, UniverseContext $context, DestinationCatalogue $destinations): Response
    {
        $membership = $context->membership();
        try {
            $data = $destinations->choices(
                $membership,
                $request->query->getString('mission', 'explore'),
                $request->query->has('q') ? $request->query->getString('q') : null,
                tutorialSpy: $request->query->getBoolean('tutorial_spy'),
            );
            return $this->json($data, 200, ['Cache-Control' => 'private, no-store']);
        } catch (\DomainException $error) {
            return $this->json(['error' => $error->getMessage()], 422, ['Cache-Control' => 'private, no-store']);
        }
    }
    #[Route('/flottes/mission-rapide', name: 'app_fleet_quick_send', methods: ['POST'])]
    public function quickSend(
        Request $request,
        UniverseContext $context,
        GameRepository $repo,
        FleetService $service,
        PreferencesService $preferences,
        GameCatalog $catalog,
    ): Response
    {
        $membership = $context->membership();
        $mission = (string) $request->request->get('mission', '');
        $targetId = filter_var($request->request->get('target'), FILTER_VALIDATE_INT);
        if (!in_array($mission, ['explore', 'spy', 'colonize'], true) || $targetId === false || $targetId < 1) {
            throw $this->createNotFoundException('Mission directe invalide.');
        }
        if (!$this->isCsrfTokenValid('map-mission-' . $mission . '-' . $targetId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        $system = $mission === 'explore' ? $repo->find(SolarSystem::class, $targetId) : null;
        $planet = $mission === 'explore' ? null : $repo->find(Planet::class, $targetId);
        $destinationSystem = $system ?? $planet?->solarSystem;
        try {
            $origin = $repo->find(Planet::class, (int) $request->request->get('origin'));
            if (!$origin instanceof Planet || $origin->owner?->id !== $membership->id) {
                throw new \DomainException('Planète de départ inaccessible.');
            }
            if (!$destinationSystem || $destinationSystem->galaxy->universe->id !== $membership->universe->id) {
                throw new \DomainException('Destination inaccessible.');
            }
            // Un ancien formulaire ouvert avant la mise à jour demandait le rang basique.
            $tier = $request->request->getString('ship_tier', 'basic');
            if (!in_array($tier, GameCatalog::TIERS, true)) {
                throw new \DomainException('Rang de vaisseau invalide.');
            }
            $shipSlug = $mission === 'colonize' ? 'colony_ship' : 'probe';
            $ranks = $mission === 'spy' ? $this->quickProbeRanks($request, $tier) : [$tier => 1];
            $composition = [$shipSlug => $ranks];
            $savedPreferences = $preferences->for($membership->player);
            $defaultSpeed = match ($mission) {
                'spy' => (float) $savedPreferences['galaxySpySpeed'],
                'colonize' => (float) $savedPreferences['galaxyColonizeSpeed'],
                default => 1.0,
            };
            $service->send(
                $membership,
                $origin,
                $mission,
                $targetId,
                $composition,
                $defaultSpeed,
                [],
                (string) $request->request->get('request_key'),
                requireExactComposition: true,
            );
            $shipLabel = $catalog->getTierForUniverse($membership->universe, 'ships', $shipSlug, array_key_first($ranks))['label'];
            $this->addFlash('success', match ($mission) {
                'explore' => $shipLabel . ' envoyée en exploration.',
                'spy' => array_sum($ranks) === 1 ? $shipLabel . ' envoyée en espionnage.' : array_sum($ranks) . ' sondes envoyées en espionnage.',
                'colonize' => $shipLabel . ' envoyé.',
            });
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        $redirectSystem = $destinationSystem && $destinationSystem->galaxy->universe->id === $membership->universe->id ? $destinationSystem : null;
        $parameters = $redirectSystem ? ['galaxy' => $redirectSystem->galaxy->id, 'system' => $redirectSystem->id] : [];
        $returnRoute = (string) $request->request->get('return_to', 'app_map');
        if (!in_array($returnRoute, ['app_map', 'app_map_v2'], true)) {
            $returnRoute = 'app_map';
        }
        return $this->redirectToRoute($returnRoute, $parameters);
    }

    /** @return array<string, int> */
    private function quickProbeRanks(Request $request, string $legacyTier): array
    {
        // Un formulaire ouvert avant le tableau des rangs contient une seule quantité.
        $submitted = $request->request->all()['probes'] ?? [$legacyTier => $request->request->get('probe_quantity')];
        if (!is_array($submitted) || array_diff(array_keys($submitted), GameCatalog::TIERS)) {
            throw new \DomainException('Rang de sonde invalide.');
        }
        $ranks = [];
        foreach ($submitted as $tier => $raw) {
            $quantity = $raw === '' ? 0 : filter_var($raw, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 0 || $quantity > FleetService::QUICK_SPY_LIMIT) {
                throw new \DomainException('Quantité de sondes invalide.');
            }
            if ($quantity > 0) {
                $ranks[$tier] = $quantity;
            }
        }
        if (!$ranks || array_sum($ranks) > FleetService::QUICK_SPY_LIMIT) {
            throw new \DomainException(sprintf('Choisissez entre 1 et %d sondes au total.', FleetService::QUICK_SPY_LIMIT));
        }
        return $ranks;
    }

    #[Route('/flottes/estimer', name: 'app_fleet_preview', methods: ['POST'])]
    public function preview(
        Request $request,
        UniverseContext $context,
        GameRepository $repo,
        FleetService $service,
        DestinationCatalogue $destinations,
    ): Response
    {
        if (!$this->isCsrfTokenValid('fleet', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $m = $context->membership();
            $origin = $repo->find(Planet::class, (int) $request->request->get('origin'));
            if (!$origin || $origin->owner?->id !== $m->id) {
                throw new \DomainException('Origine inaccessible.');
            }
            $mission = (string) $request->request->get('mission');
            $ratio = filter_var($request->request->get('ratio'), FILTER_VALIDATE_FLOAT);
            if ($ratio === false) {
                throw new \DomainException('Vitesse invalide.');
            }
            $orbitHours = 0.0;
            if ($mission === 'orbit') {
                $orbitHours = filter_var($request->request->get('orbit_hours', 0), FILTER_VALIDATE_FLOAT);
                if ($orbitHours === false) {
                    throw new \DomainException('Durée d’orbite invalide.');
                }
            }
            $mode = (string) $request->request->get('destination_mode', 'custom');
            $destination = $destinations->resolve(
                $m,
                $mission,
                $mode,
                (string) $request->request->get('coordinates', ''),
                $request->request->get($mode . '_destination')
            );
            $jump = !$request->request->getBoolean('tutorial_spy') && $request->request->getBoolean('jump_point');
            $ships = $request->request->all('ships');
            $cargo = $request->request->all('cargo');
            $result = $service->preview(
                $m,
                $origin,
                $mission,
                (int) $destination->id,
                $ships,
                $ratio,
                $cargo,
                $orbitHours,
                jumpPoint: $jump,
            );
            if ($request->request->getBoolean('tutorial_spy')) {
                FirstStepRewardPolicy::assertSpyQuote($mission, $destination, $result['composition'], $result['ship_adjusted']);
            }
            if ($request->request->getBoolean('require_exact_composition') && $result['ship_adjusted']) {
                throw new \DomainException('La composition n’est plus disponible exactement. Modifiez vos quantités ou vérifiez à nouveau la composition enregistrée.');
            }
            if ($jump) {
                // Le trajet normal reste une estimation ; seul le mode choisi est envoyé.
                try {
                    $ordinary = $service->preview($m, $origin, $mission, (int) $destination->id, $ships, $ratio, $cargo, $orbitHours);
                    $result['ordinary_route'] = array_intersect_key($ordinary, array_flip(['seconds', 'total_fuel', 'freight_capacity']));
                } catch (\DomainException) {
                    $result['ordinary_route'] = null;
                }
            }
            return $this->json($result, 200, ['Cache-Control' => 'private, no-store']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422, ['Cache-Control' => 'private, no-store']);
        }
    }
    #[Route('/flottes/orbite/{id}/repondre', name: 'app_orbit_decision', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function orbitDecision(
        int $id,
        Request $request,
        UniverseContext $context,
        FleetService $service,
    ): Response
    {
        if (!$this->isCsrfTokenValid('orbit-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        $decision = (string) $request->request->get('decision');
        if (!in_array($decision, ['accept', 'reject'], true)) {
            throw $this->createNotFoundException();
        }
        try {
            $service->decideOrbit($context->membership(), $id, $decision === 'accept', (string) $request->request->get('request_key', ''));
            $this->addFlash('success', $decision === 'accept' ? 'Demande d’orbite acceptée.' : 'Demande d’orbite refusée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('app_fleet_orbits');
    }
    #[Route('/flottes/envoyer', name: 'app_fleet_send', methods: ['POST'])]
    public function send(
        Request $request,
        UniverseContext $context,
        GameRepository $repo,
        FleetService $service,
        DestinationCatalogue $destinations,
    ): Response
    {
        if (!$this->isCsrfTokenValid('fleet', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        $m = $context->membership();
        try {
            $origin = $repo->find(Planet::class, (int) $request->request->get('origin'));
            if (!$origin || $origin->owner?->id !== $m->id) {
                throw new \DomainException('Origine inaccessible.');
            }
            $mission = (string) $request->request->get('mission');
            $mode = (string) $request->request->get('destination_mode', 'custom');
            $destination = $destinations->resolve(
                $m,
                $mission,
                $mode,
                (string) $request->request->get('coordinates', ''),
                $request->request->get($mode . '_destination')
            );
            $target = (int) $destination->id;
            $ratio = filter_var($request->request->get('ratio'), FILTER_VALIDATE_FLOAT);
            if ($ratio === false) {
                throw new \DomainException('Vitesse invalide.');
            }
            $orbitHours = 0.0;
            if ($mission === 'orbit') {
                $orbitHours = filter_var($request->request->get('orbit_hours', 0), FILTER_VALIDATE_FLOAT);
                if ($orbitHours === false) {
                    throw new \DomainException('Durée d’orbite invalide.');
                }
            }
            $adjusted = $service->send(
                $m,
                $origin,
                $mission,
                $target,
                $request->request->all('ships'),
                $ratio,
                $request->request->all('cargo'),
                (string) $request->request->get('request_key'),
                $orbitHours,
                $request->request->getBoolean('require_exact_composition'),
                tutorialSpy: $request->request->getBoolean('tutorial_spy'),
                jumpPoint: !$request->request->getBoolean('tutorial_spy') && $request->request->getBoolean('jump_point'),
            );
            $this->addFlash('success', 'Flotte envoyée. Carburant aller-retour réservé.' . ($adjusted ? ' Quantités ajustées aux stocks et capacités disponibles.' : ''));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute(isset($adjusted) ? 'app_fleets' : 'app_fleet_prepare', isset($adjusted) ? [] : array_filter([
            'mission' => $request->request->getString('mission', 'explore'),
            'tutorial' => $request->request->getBoolean('tutorial_spy') ? 1 : null,
        ]));
    }
}
