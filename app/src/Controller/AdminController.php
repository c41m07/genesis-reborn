<?php

namespace App\Controller;

use App\Entity\{AdminAuditLog, GameEvent, MessageReport, PlayerReport, Player, Universe};
use App\Service\Admin\AdminMissionMonitoring;
use App\Service\Admin\AdminCaseService;
use App\Service\Player\PlayerActivityService;
use App\Service\Performance\PerformancePeriods;
use App\Repository\GameRepository;
use App\Repository\AdminReadRepository;
use App\Service\Admin\AdminService;
use App\Service\Admin\AdminOperationsService;
use App\Service\Admin\AdminNotificationService;
use App\Service\Admin\UniverseMetricsService;
use App\Service\Admin\UniverseObservatory;
use App\Service\Admin\CatalogBalanceService;
use App\Service\Catalog\GameCatalog;
use App\Service\Universe\UniverseLifecycleService;
use App\Service\Universe\UniverseCreationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'app_admin')]
final class AdminController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET'])]
    #[Route('/joueurs', name: '_players', methods: ['GET'], defaults: ['screen' => 'players'])]
    #[Route('/univers', name: '_universes', methods: ['GET'], defaults: ['screen' => 'universes'])]
    #[Route('/moderation', name: '_reports', methods: ['GET'], defaults: ['screen' => 'reports'])]
    #[Route('/securite', name: '_security', methods: ['GET'], defaults: ['screen' => 'security'])]
    #[Route('/exploitation', name: '_operations', methods: ['GET'], defaults: ['screen' => 'operations'])]
    #[Route('/journal', name: '_audit', methods: ['GET'], defaults: ['screen' => 'audit'])]
    public function index(
        Request $request,
        GameRepository $repo,
        GameCatalog $catalog,
        AdminService $admin,
        AdminOperationsService $operations,
        AdminNotificationService $notifications,
        AdminMissionMonitoring $monitor,
        PlayerActivityService $presence,
        AdminCaseService $caseFiles,
        \App\Service\Player\PlayerReportService $playerReportService,
        AdminReadRepository $reads,
        \App\Service\Admin\AdminAuditExport $auditExport,
        string $screen = 'overview',
    ): Response
    {
        $titles = [
            'overview' => 'Accueil',
            'players' => 'Joueurs',
            'universes' => 'Univers',
            'reports' => 'Modération',
            'security' => 'Sécurité',
            'operations' => 'Exploitation',
            'audit' => 'Journal des actions',
        ];
        if (!isset($titles[$screen])) {
            throw $this->createNotFoundException();
        }
        // Préparer les listes de la seule rubrique demandée ; les compteurs restent communs à la console.
        $listFilters = [
            'q' => mb_substr(trim($request->query->getString('q')), 0, 150),
            'status' => in_array($request->query->getString('status'), ['active', 'suspended', 'scheduled', 'closed'], true) ? $request->query->getString('status') : '',
            'verification' => in_array($request->query->getString('verification'), ['confirmed', 'pending'], true) ? $request->query->getString('verification') : '',
            'role' => in_array($request->query->getString('role'), ['admin', 'player'], true) ? $request->query->getString('role') : '',
            'action' => mb_substr($request->query->getString('action'), 0, 80),
        ];
        if ($screen !== 'universes' && !in_array($listFilters['status'], ['active', 'suspended'], true)) $listFilters['status'] = '';
        if ($screen === 'audit' && $request->query->getString('export') === 'all') {
            return $auditExport->response($listFilters);
        }
        $listPage = match ($screen) {
            'players' => $reads->players($listFilters, $request->query->getInt('page', 1)),
            'audit' => $reads->audit($listFilters, $request->query->getInt('page', 1)),
            default => [],
        };
        $counters = $reads->counters();
        $closedReports = $screen === 'reports' && $request->query->getString('status') === 'closed';
        $openModeration = $screen === 'reports' && !$closedReports;
        $reportArchive = $closedReports ? $reads->closedModerationReports(
            $request->query->getInt('messages_page', 1),
            $request->query->getInt('players_page', 1),
        ) : [];
        $reports = $reportArchive['messages']['entries']
            ?? ($openModeration ? $repo->all(MessageReport::class, ['status' => 'open'], ['createdAt' => 'DESC']) : []);
        $playerReports = $reportArchive['players']['entries']
            ?? ($openModeration ? $repo->all(PlayerReport::class, ['status' => 'open'], ['createdAt' => 'DESC', 'id' => 'DESC']) : []);
        // Seule la file des signalements à traiter journalise la consultation et acquitte les nouveautés.
        if ($openModeration) {
            $admin->recordReportViews($this->getUser(), $reports);
            $playerReportService->recordViews($this->getUser(), $playerReports);
        }
        $universePage = $screen === 'universes' ? $reads->universes($request->query->getInt('page', 1), $listFilters['q'], $listFilters['status']) : [];
        $universes = $screen === 'universes' ? $universePage['entries'] : ($screen === 'overview' ? $repo->all(Universe::class, [], ['id' => 'ASC']) : []);
        $universePlayerCounts = $screen === 'universes' ? $reads->universePlayerCounts() : [];
        $universeConfigs = [];
        if ($screen === 'universes') {
            foreach ($universes as $universe) {
                $universeConfigs[$universe->id] = $catalog->forUniverse($universe);
            }
        }
        $players = $screen === 'players' ? $listPage['entries'] : [];
        $activeUniverseCount = count(array_filter($universes, static fn(Universe $universe): bool => $universe->status === 'active'));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $scheduledUniverseCount = count(array_filter($universes, static fn(Universe $universe): bool => $universe->status === 'active' && $universe->startsAt !== null && $universe->startsAt > $now));
        $closedUniverseCount = count(array_filter($universes, static fn(Universe $universe): bool => $universe->status === 'closed'));
        $auditLogs = $screen === 'audit' ? $listPage['entries'] : ($screen === 'overview' ? $reads->recentActions() : []);
        $securityIncidents = $screen === 'security' ? $reads->securityIncidents() : [];
        if ($openModeration) {
            $notifications->markVisited($this->getUser(), ['reports' => max([0, ...array_map(static fn(MessageReport $report): int => $report->id, $reports)]), 'player_reports' => max([0, ...array_map(static fn(PlayerReport $report): int => $report->id, $playerReports)])]);
        }
        if ($screen === 'security') {
            $notifications->markVisited($this->getUser(), ['security' => $reads->latestSecurityIncidentId()]);
        }
        $auditActions = $screen === 'audit' ? $reads->auditActions() : [];
        $operationsSnapshot = in_array($screen, ['overview', 'operations'], true) ? $operations->snapshot() : [];
        $data = [
            'listPage' => $listPage,
            'listFilters' => $listFilters,
            'preferences' => array_replace(['timezone' => 'Europe/Paris'], $this->getUser()->preferences),
            'screen' => $screen,
            'screenTitle' => $titles[$screen],
            'activeCaseCount' => $screen === 'overview' ? $caseFiles->activeCount() : 0,
            'fleetOverview' => $screen === 'overview' ? $monitor->search([
                'universe' => 0,
                'mission' => '',
                'status' => 'active',
                'q' => '',
            ], 1) : [],
            'upcomingEvents' => $screen === 'overview' ? array_slice(array_filter($repo->all(GameEvent::class, ['status' => ['planned', 'active']], ['startsAt' => 'ASC']), static fn(GameEvent $event): bool => $event->endsAt > $now), 0, 5) : [],
            'players' => $players,
            'playerPresence' => $screen === 'players' ? $presence->forPlayers($players) : [],
            'playerCount' => $counters['playerCount'],
            'suspendedPlayerCount' => $counters['suspendedPlayerCount'],
            'activeUniverseCount' => $activeUniverseCount,
            'scheduledUniverseCount' => $scheduledUniverseCount,
            'closedUniverseCount' => $closedUniverseCount,
            'universePlayerCounts' => $universePlayerCounts,
            'openReportCount' => $counters['openReportCount'],
            'securityIncidents' => $securityIncidents,
            'securityIncidentCount' => in_array($screen, ['overview', 'security'], true) ? $reads->securityIncidentCount() : 0,
            'operations' => $operationsSnapshot,
            'auditActions' => $auditActions,
            'universes' => $universes,
            'universeConfigs' => $universeConfigs,
            'universePage' => $universePage,
            'universeProfiles' => UniverseCreationService::profiles(),
            'universeCreationDraft' => [],
            'universeScheduleDraft' => [],
            'universeSettingsDraft' => [],
            'reports' => $reports,
            'closedReports' => $closedReports,
            'reportArchive' => $reportArchive,
            'playerReports' => $playerReports,
            'playerReportReasons' => \App\Service\Player\PlayerReportService::REASONS,
            'playerReportCategories' => \App\Service\Player\PlayerReportService::CATEGORIES,
            'auditLogs' => $auditLogs,
        ];
        if (in_array($screen, ['players', 'audit'], true) && $request->headers->get('X-Genesis-Admin-List') === '1') {
            $response = $this->render('admin/sections/_' . $screen . '_results.html.twig', $data);
        } else {
            $response = $this->render($screen === 'overview' ? 'admin/index.html.twig' : 'admin/section.html.twig', $data);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary('X-Genesis-Admin-List');
        return $response;
    }
    #[Route('/rubriques/{group}', name: '_group', requirements: ['group' => 'players|universe|events|ops'], methods: ['GET'])]
    public function group(string $group): Response
    {
        return $this->render('admin/group.html.twig', ['navigationGroup' => $group]);
    }
    #[Route('/universes/{id}/mesures', name: '_universe_metrics', methods: ['GET'])]
    public function metrics(
        int $id,
        Request $request,
        GameRepository $repo,
        UniverseMetricsService $metrics,
    ): Response
    {
        $universe = $repo->find(Universe::class, $id);
        if (!$universe instanceof Universe) {
            throw $this->createNotFoundException('Univers introuvable.');
        }
        return $this->render('admin/universe_metrics.html.twig', ['universe' => $universe, 'metrics' => $metrics->snapshot($universe, $request->query->getInt('page', 1))]);
    }
    #[Route('/universes/{id}/observatoire', name: '_universe_observatory', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function observatory(
        int $id,
        Request $request,
        GameRepository $repo,
        UniverseObservatory $observatory,
        UniverseMetricsService $metrics,
        \App\Service\Performance\PerformanceReport $performance,
        PerformancePeriods $periods,
    ): Response {
        $universe = $repo->find(Universe::class, $id);
        if (!$universe instanceof Universe) {
            throw $this->createNotFoundException('Univers introuvable.');
        }
        $tab = $request->query->getString('tab', 'economy');
        if (!isset(UniverseObservatory::TABS[$tab])) {
            throw $this->createNotFoundException('Rubrique introuvable.');
        }
        $days = $request->query->getInt('days', 30);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }
        $periodId = $request->query->getInt('measure_period');
        $selection = $periods->selection($periodId);
        $releaseId = $request->query->getInt('release');
        $comparePeriod = $releaseId === -1 && $selection['start'] !== null;
        $releases = $periods->releases($releaseId ?: null);
        $comparisonAt = $comparePeriod ? $selection['start'] : $releases['selected']?->publishedAt;
        if ($tab === 'performance') {
            $report = $performance->read(
                $request->query->getInt('hours', 168),
                $request->query->getString('device', 'all'),
                $request->query->getString('page_key', ''),
                $selection['start'],
                $selection['end'],
            );
            $report['trend']['markers'] = $periods->markers($report['trend'], $releases['notes']);
            $report['comparison'] = $comparisonAt === null ? null
                : $periods->compare($comparisonAt, $report['page']['key'], $report['device']);
        } else {
            $report = $observatory->read($universe, $tab, $days, $selection['start'], $selection['end']);
            if ($tab === 'audience') {
                $report['chart']['markers'] = $periods->markers($report['chart'], $releases['notes'], 10, 740);
            }
            if ($tab !== 'economy' && $comparisonAt !== null) {
                $comparison = $periods->windows($comparisonAt, 90);
                if ($comparison['hours'] > 0) {
                    foreach ($comparison['range'] as $side => [$from, $until]) {
                        $comparison[$side] = $observatory->comparisonCounts($universe, $tab, $from, $until);
                    }
                }
                $report['releaseComparison'] = $comparison;
            }

        }

        // Les stocks sont des valeurs actuelles, jamais remis à zéro avec les compteurs.
        $response = $this->render('admin/universe_observatory.html.twig', [
            'universe' => $universe,
            'universes' => $repo->all(Universe::class, [], ['name' => 'ASC']),
            'tab' => $tab,
            'tabs' => UniverseObservatory::TABS,
            'days' => $days,
            'report' => $report,
            'selection' => $selection,
            'periodId' => $periodId,
            'releases' => $releases,
            'comparePeriod' => $comparePeriod,
            'resources' => $tab === 'economy' ? $metrics->resources($universe, $request->query->getInt('page', 1)) : null,
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/universes/{id}/observatoire/periode', name: '_observatory_start_period', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function startObservatoryPeriod(int $id, Request $request, GameRepository $repo, PerformancePeriods $periods): Response
    {
        if (!$repo->find(Universe::class, $id) instanceof Universe) {
            throw $this->createNotFoundException('Univers introuvable.');
        }
        $actor = $this->getUser();
        if (!$actor instanceof Player || !$this->isCsrfTokenValid('observatory_period_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $periods->start($actor);
        $this->addFlash('success', 'La nouvelle période commencera à la prochaine heure pleine. L’historique est conservé.');
        $tab = $request->request->getString('tab');

        return $this->redirectToRoute('app_admin_universe_observatory', [
            'id' => $id,
            'tab' => isset(UniverseObservatory::TABS[$tab]) ? $tab : 'performance',
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/universes/nouveau', name: '_universe_new', methods: ['GET'])]
    public function newUniverse(Request $request): Response
    {
        return $this->render('admin/universe_new.html.twig', [
            'universeProfiles' => UniverseCreationService::profiles(),
            'universeCreationDraft' => $request->getSession()->remove('admin_universe_creation_draft') ?? [],
        ]);
    }

    #[Route('/universes/{id}/gerer', name: '_universe_manage', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function manageUniverse(int $id, Request $request, GameRepository $repo, GameCatalog $catalog): Response
    {
        $universe = $repo->find(Universe::class, $id);
        if (!$universe instanceof Universe) throw $this->createNotFoundException('Univers introuvable.');
        // Ne consommer que les brouillons de l’univers ouvert ; une autre fiche ne doit pas les perdre.
        $drafts = [];
        foreach (['schedule', 'settings'] as $kind) {
            $key = 'admin_universe_'.$kind.'_draft';
            $draft = $request->getSession()->get($key, []);
            $drafts[$kind] = ($draft['universe_id'] ?? null) === $id ? $request->getSession()->remove($key) : [];
        }
        return $this->render('admin/universe_manage.html.twig', [
            'universe' => $universe, 'config' => $catalog->forUniverse($universe),
            'universePlayerCounts' => [$id => $repo->count(\App\Entity\PlayerUniverse::class, ['universe' => $universe])],
            'universeProfiles' => UniverseCreationService::profiles(),
            'universeScheduleDraft' => $drafts['schedule'], 'universeSettingsDraft' => $drafts['settings'],
        ]);
    }

    #[Route('/universes/{id}/catalogue', name: '_catalogue', methods: ['GET'])]
    #[Route('/universes/{id}/catalogue/{group}', name: '_catalogue_group', requirements: ['group' => 'buildings|technologies|ships|defenses'], methods: ['GET'])]
    #[Route('/universes/{id}/catalogue/{group}/{slug}', name: '_catalogue_edit', requirements: ['group' => 'buildings|technologies|ships|defenses'], methods: ['GET'])]
    public function catalogue(int $id, Request $request, GameRepository $repo, CatalogBalanceService $balance, string $group = 'buildings', ?string $slug = null): Response
    {
        $universe = $repo->find(Universe::class, $id);
        if (!$universe instanceof Universe) throw $this->createNotFoundException('Univers introuvable.');
        $query = mb_substr(trim($request->query->getString('q')), 0, 150);
        // La recherche traverse les familles ; une fiche seule prépare ses champs modifiables.
        $catalogues = $balance->entries($universe, $slug !== null || $query === '' ? $group : null, $slug, $slug !== null);
        if ($slug !== null && empty($catalogues[$group])) throw $this->createNotFoundException('Entrée introuvable.');
        if ($slug !== null) {
            $draft = $request->getSession()->remove('admin_catalogue_draft_'.$id.'_'.$group.'_'.$slug);
            // Un échec ne perd pas les saisies et ne modifie jamais les valeurs de référence.
            if (is_array($draft) && isset($draft['values'], $draft['revision'], $draft['reason']) && !$request->query->getBoolean('reload')) {
                $catalogues[$group][0]['revision'] = $draft['revision'];
                $catalogues[$group][0]['reason'] = $draft['reason'];
                $catalogues[$group][0]['draft'] = true;
                $catalogues[$group][0]['preview'] = $draft['preview'] ?? null;
                foreach ($catalogues[$group][0]['fields'] as &$field) {
                    $value = $draft['values'];
                    foreach (explode('.', $field['path']) as $part) $value = is_array($value) ? ($value[$part] ?? null) : null;
                    if (is_scalar($value)) $field['value'] = $value;
                }
                unset($field);
            }
        }
        if ($query !== '' && $slug === null) {
            foreach ($catalogues as $family => $items) {
                $catalogues[$family] = array_filter($items, static fn(array $item): bool => str_contains(mb_strtolower($item['label'].' '.$item['slug'].' '.implode(' ', $item['tier_labels'])), mb_strtolower($query)));
            }
        }
        $selection = null;
        // Le rail de lecture ne sélectionne qu’une fiche déjà dans les résultats autorisés.
        // Il n’utilise ni la route d’édition ni le brouillon de session.
        $focus = $request->query->all()['focus'] ?? null;
        if ($slug === null && is_string($focus) && strlen($focus) <= 180) {
            foreach ($catalogues as $family => $items) {
                foreach ($items as $entry) {
                    if ($focus === $family.':'.$entry['slug']) {
                        $selection = ['family' => $family, 'entry' => $entry, 'key' => $focus];
                        break 2;
                    }
                }
            }
        }

        return $this->render('admin/catalogue.html.twig', [
            'universe' => $universe, 'catalogues' => $catalogues, 'group' => $group, 'query' => $query,
            'selection' => $selection,
            'editing' => $slug !== null, 'item' => $slug !== null ? $catalogues[$group][0] : null,
        ]);
    }
    #[Route('/universes/{id}/catalogue/{group}/{slug}', name: '_catalogue_save', methods: ['POST'])]
    public function saveCatalogue(
        int $id,
        string $group,
        string $slug,
        Request $request,
        CatalogBalanceService $balance,
        GameRepository $repo,
    ): Response
    {
        $tokenId = 'admin_catalogue_' . $id . '_' . $group . '_' . $slug;
        if (!$this->isCsrfTokenValid($tokenId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            if ($request->request->getBoolean('preview')) {
                $universe = $repo->find(Universe::class, $id);
                if (!$universe instanceof Universe) {
                    throw $this->createNotFoundException('Univers introuvable.');
                }
                $preview = $balance->preview(
                    $universe, $group, $slug, $request->request->all('values'),
                    $request->request->getString('revision'),
                    $request->request->getString('preview_tier', 'basic'),
                    $request->request->getInt('preview_level', 1),
                    $request->request->getInt('preview_quantity', 1),
                );
                $request->getSession()->set('admin_catalogue_draft_'.$id.'_'.$group.'_'.$slug, [
                    'values' => $request->request->all('values'),
                    'revision' => $request->request->getString('revision'),
                    'reason' => $request->request->getString('reason'),
                    'preview' => $preview,
                ]);
                return $this->redirectToRoute('app_admin_catalogue_edit', ['id' => $id, 'group' => $group, 'slug' => $slug]);
            }
            if (!$request->request->getBoolean('confirmed')) {
                throw new \DomainException('Confirmez l’application de cette modification à l’univers.');
            }
            if ($request->request->getBoolean('reset')) {
                $balance->reset($this->getUser(), $id, $group, $slug, $request->request->getString('revision'), $request->request->getString('reason'));
            } else {
                $balance->save($this->getUser(), $id, $group, $slug, $request->request->all('values'), $request->request->getString('revision'), $request->request->getString('reason'));
            }
            $request->getSession()->remove('admin_catalogue_draft_'.$id.'_'.$group.'_'.$slug);
            $this->addFlash('success', 'Équilibrage enregistré pour cet univers.');
        } catch (\DomainException $error) {
            $request->getSession()->set('admin_catalogue_draft_'.$id.'_'.$group.'_'.$slug, ['values' => $request->request->all('values'), 'revision' => $request->request->getString('revision'), 'reason' => $request->request->getString('reason')]);
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_admin_catalogue_edit', ['id' => $id, 'group' => $group, 'slug' => $slug]);
    }
    #[Route('/security-incidents/{id}/review', name: '_security_review', methods: ['POST'])]
    public function reviewSecurityIncident(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_security_review_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $admin->reviewSecurityIncident($this->getUser(), $id);
            $this->addFlash('success', 'Tentative marquée comme examinée.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_admin_security', ['_fragment' => 'admin-security']);
    }
    #[Route('/players/{id}/role', name: '_player_role', methods: ['POST'])]
    public function playerRole(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_role_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $requestedRole = (string) $request->request->get('role');
            if (!in_array($requestedRole, ['promote', 'demote'], true)) {
                throw new \DomainException('Action de rôle inconnue.');
            }
            $actor = $this->getUser();
            if (!$actor instanceof Player) {
                throw $this->createAccessDeniedException();
            }
            $admin->setPlayerAdminRole($actor, $id, $requestedRole === 'promote');
            $this->addFlash('success', $requestedRole === 'promote' ? 'Rôle administrateur accordé.' : 'Rôle administrateur retiré.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_admin_players', ['_fragment' => 'admin-players']);
    }
    #[Route('/players/{id}/suspension', name: '_player_suspend', methods: ['POST'])]
    public function suspend(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_suspend_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $admin->setPlayerSuspended($this->getUser(), $id, $request->request->getBoolean('suspend'));
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_admin_players');
    }
    #[Route('/reports/{id}', name: '_report_review', methods: ['POST'])]
    public function review(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_report_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $admin->reviewReport($this->getUser(), $id, (string) $request->request->get('decision'));
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_admin_reports');
    }
    #[Route('/universes/{id}/settings', name: '_universe_settings', methods: ['POST'])]
    public function settings(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_settings_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        $input = $request->request->all();
        try {
            $admin->setUniverseSettings($this->getUser(), $id, $input);
            $request->getSession()->remove('admin_universe_settings_draft');
            $this->addFlash('success', 'Paramètres de l’univers enregistrés.');
        } catch (\Exception $error) {
            $this->addFlash('error', $error->getMessage());
            $draft = ['universe_id' => $id, 'error' => $error->getMessage(), 'speed_profile' => is_string($input['speed_profile'] ?? null) ? $input['speed_profile'] : 'custom'];
            foreach (['multipliers' => [
                'building_time',
                'research_time',
                'ship_build_time',
                'resource_production',
                'travel_time',
            ], 'limits' => ['max_planets', 'max_fleet_size'], 'features' => ['guilds_enabled', 'planet_conquest', Universe::PIRATE_CAMPAIGN_FEATURE]] as $group => $keys) {
                $values = is_array($input[$group] ?? null) ? $input[$group] : [];
                $draft[$group] = array_filter(array_intersect_key($values, array_flip($keys)), 'is_scalar');
            }
            $request->getSession()->set('admin_universe_settings_draft', $draft);
        }
        return $this->redirectToRoute('app_admin_universe_manage', ['id' => $id]);
    }
    #[Route('/universes', name: '_universe_create', methods: ['POST'])]
    public function createUniverse(Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_create_universe', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $universe = $admin->createUniverse(
                $this->getUser(),
                (string) $request->request->get('name'),
                (string) $request->request->get('slug'),
                (string) $request->request->get('profile'),
                (int) $request->request->get('galaxies'),
                (int) $request->request->get('systems_per_galaxy')
            );
            $request->getSession()->remove('admin_universe_creation_draft');
            $this->addFlash('success', 'Univers créé et prêt à accueillir des joueurs.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
            $draft = array_intersect_key($request->request->all(), array_flip([
                'name',
                'slug',
                'profile',
                'galaxies',
                'systems_per_galaxy',
            ]));
            $draft['error'] = $error->getMessage();
            $request->getSession()->set('admin_universe_creation_draft', $draft);
        }
        return isset($universe) ? $this->redirectToRoute('app_admin_universe_manage', ['id' => $universe->id]) : $this->redirectToRoute('app_admin_universe_new');
    }
    #[Route('/universes/{id}/delete', name: '_universe_delete', methods: ['POST'])]
    public function deleteUniverse(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_delete_universe_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $admin->deleteUniverse($this->getUser(), $id, (string) $request->request->get('confirmation'));
            $this->addFlash('success', 'Univers supprimé définitivement avec toutes ses données de jeu.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_admin_universes');
    }
    #[Route('/universes/{id}/schedule', name: '_universe_schedule', methods: ['POST'])]
    public function schedule(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_universe_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        $draft = ['universe_id' => $id, 'starts_at' => '', 'ends_at' => ''];
        try {
            $startRaw = trim((string) $request->request->get('starts_at'));
            $endRaw = trim((string) $request->request->get('ends_at'));
            $draft['starts_at'] = $startRaw;
            $draft['ends_at'] = $endRaw;
            $startsAt = $startRaw === '' ? null : new \DateTimeImmutable($startRaw, new \DateTimeZone('UTC'));
            $endsAt = $endRaw === '' ? null : new \DateTimeImmutable($endRaw, new \DateTimeZone('UTC'));
            $admin->setUniverseSchedule($this->getUser(), $id, $startsAt, $endsAt);
            $request->getSession()->remove('admin_universe_schedule_draft');
            $this->addFlash('success', 'Calendrier de l’univers enregistré.');
        } catch (\Exception $error) {
            $this->addFlash('error', $error->getMessage());
            $draft['error'] = $error->getMessage();
            $request->getSession()->set('admin_universe_schedule_draft', $draft);
        }
        return $this->redirectToRoute('app_admin_universe_manage', ['id' => $id]);
    }
}
