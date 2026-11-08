<?php
namespace App\Controller;

use App\Entity\{AdminAuditLog, GameEvent, MessageReport, Player, PlayerUniverse, Universe};
use App\Service\Admin\AdminMissionMonitoring;
use App\Repository\GameRepository;
use App\Service\Admin\AdminService;
use App\Service\Admin\AdminOperationsService;
use App\Service\Admin\AdminNotificationService;
use App\Service\Admin\UniverseMetricsService;
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
    #[Route('/joueurs', name: '_players', methods: ['GET'], defaults: ['screen'=>'players'])]
    #[Route('/univers', name: '_universes', methods: ['GET'], defaults: ['screen'=>'universes'])]
    #[Route('/moderation', name: '_reports', methods: ['GET'], defaults: ['screen'=>'reports'])]
    #[Route('/securite', name: '_security', methods: ['GET'], defaults: ['screen'=>'security'])]
    #[Route('/exploitation', name: '_operations', methods: ['GET'], defaults: ['screen'=>'operations'])]
    #[Route('/journal', name: '_audit', methods: ['GET'], defaults: ['screen'=>'audit'])]
    public function index(Request $request, GameRepository $repo, GameCatalog $catalog, AdminService $admin, AdminOperationsService $operations, AdminNotificationService $notifications, AdminMissionMonitoring $monitor, string $screen = 'overview'): Response
    {
        $titles = ['overview'=>'Accueil', 'players'=>'Joueurs', 'universes'=>'Univers', 'reports'=>'Modération', 'security'=>'Sécurité', 'operations'=>'Exploitation', 'audit'=>'Journal des actions'];
        if (!isset($titles[$screen])) throw $this->createNotFoundException();
        $reports = $repo->all(MessageReport::class, ['status' => 'open'], ['createdAt' => 'DESC']);
        if ($screen === 'reports') $admin->recordReportViews($this->getUser(), $reports);
        $universes = $repo->all(Universe::class, [], ['id' => 'ASC']);
        $memberships = $repo->all(PlayerUniverse::class);
        $universePlayerCounts = [];
        foreach ($memberships as $membership) {
            $universePlayerCounts[$membership->universe->id] = ($universePlayerCounts[$membership->universe->id] ?? 0) + 1;
        }
        $universeConfigs = [];
        foreach ($universes as $universe) { $universeConfigs[$universe->id] = $catalog->forUniverse($universe); }
        $players = $repo->all(Player::class, [], ['id' => 'DESC']);
        $activeUniverseCount = count(array_filter($universes, static fn (Universe $universe): bool => $universe->status === 'active'));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $scheduledUniverseCount = count(array_filter($universes, static fn (Universe $universe): bool => $universe->status === 'active' && $universe->startsAt !== null && $universe->startsAt > $now));
        $closedUniverseCount = count(array_filter($universes, static fn (Universe $universe): bool => $universe->status === 'closed'));
        $suspendedPlayerCount = count(array_filter($players, static fn (Player $player): bool => $player->lockedAt !== null));
        $auditLogs = $repo->all(AdminAuditLog::class, [], ['createdAt' => 'DESC']);
        $reviewedIncidentIds = [];
        foreach ($auditLogs as $entry) {
            if ($entry->action === 'admin_access_reviewed' && $entry->targetType === 'admin_access_incident') {
                $reviewedIncidentIds[$entry->targetId] = true;
            }
        }
        $securityIncidents = array_values(array_filter($auditLogs, static fn (AdminAuditLog $entry): bool => $entry->action === 'admin_access_denied' && !isset($reviewedIncidentIds[$entry->id])));
        if ($screen === 'reports') $notifications->markVisited($this->getUser(), ['reports'=>max([0, ...array_map(static fn (MessageReport $report): int => $report->id, $reports)])]);
        if ($screen === 'security') $notifications->markVisited($this->getUser(), ['security'=>max([0, ...array_map(static fn (AdminAuditLog $entry): int => $entry->id, $securityIncidents)])]);
        $auditActions = array_values(array_unique(array_map(static fn (AdminAuditLog $entry): string => $entry->action, $auditLogs)));
        sort($auditActions);
        $operationsSnapshot = $operations->snapshot();
        return $this->render($screen === 'overview' ? 'admin/index.html.twig' : 'admin/section.html.twig', [
            'screen'=>$screen, 'screenTitle'=>$titles[$screen],
            'fleetOverview'=>$screen === 'overview' ? $monitor->search(['universe'=>0, 'mission'=>'', 'status'=>'active', 'q'=>''], 1) : [],
            'upcomingEvents'=>$screen === 'overview' ? array_slice(array_filter($repo->all(GameEvent::class, ['status'=>['planned', 'active']], ['startsAt'=>'ASC']), static fn (GameEvent $event): bool => $event->endsAt > $now), 0, 5) : [],
            'players' => $players,
            'playerCount' => count($players),
            'suspendedPlayerCount' => $suspendedPlayerCount,
            'activeUniverseCount' => $activeUniverseCount,
            'scheduledUniverseCount' => $scheduledUniverseCount,
            'closedUniverseCount' => $closedUniverseCount,
            'universePlayerCounts' => $universePlayerCounts,
            'openReportCount' => count($reports),
            'securityIncidents' => $securityIncidents,
            'securityIncidentCount' => count($securityIncidents),
            'operations' => $operationsSnapshot,
            'auditActions' => $auditActions,
            'universes' => $universes,
            'universeConfigs' => $universeConfigs,
            'universeProfiles' => UniverseCreationService::profiles(),
            'universeCreationDraft' => $screen === 'universes' ? ($request->getSession()->remove('admin_universe_creation_draft') ?? []) : [],
            'universeScheduleDraft' => $screen === 'universes' ? ($request->getSession()->remove('admin_universe_schedule_draft') ?? []) : [],
            'universeSettingsDraft' => $screen === 'universes' ? ($request->getSession()->remove('admin_universe_settings_draft') ?? []) : [],
            'reports' => $reports,
            'auditLogs' => $auditLogs,
        ]);
    }

    #[Route('/universes/{id}/mesures', name: '_universe_metrics', methods: ['GET'])]
    public function metrics(int $id, Request $request, GameRepository $repo, UniverseMetricsService $metrics): Response
    {
        $universe = $repo->find(Universe::class, $id);
        if (!$universe instanceof Universe) {
            throw $this->createNotFoundException('Univers introuvable.');
        }
        return $this->render('admin/universe_metrics.html.twig', [
            'universe' => $universe,
            'metrics' => $metrics->snapshot($universe, $request->query->getInt('page', 1)),
        ]);
    }

    #[Route('/universes/{id}/catalogue', name: '_catalogue', methods: ['GET'])]
    public function catalogue(int $id, GameRepository $repo, CatalogBalanceService $balance): Response
    {
        $universe=$repo->find(Universe::class,$id);
        if(!$universe instanceof Universe)throw $this->createNotFoundException('Univers introuvable.');
        return $this->render('admin/catalogue.html.twig',['universe'=>$universe,'catalogues'=>$balance->entries($universe),'adminUniverses'=>$repo->all(Universe::class,[],['id'=>'ASC'])]);
    }

    #[Route('/universes/{id}/catalogue/{group}/{slug}', name: '_catalogue_save', methods: ['POST'])]
    public function saveCatalogue(int $id,string $group,string $slug,Request $request,CatalogBalanceService $balance):Response
    {
        $tokenId='admin_catalogue_'.$id.'_'.$group.'_'.$slug;
        if(!$this->isCsrfTokenValid($tokenId,$request->request->get('_token')))throw $this->createAccessDeniedException('Formulaire expiré.');
        try{if($request->request->getBoolean('reset'))$balance->reset($this->getUser(),$id,$group,$slug);else $balance->save($this->getUser(),$id,$group,$slug,$request->request->all('values'));$this->addFlash('success','Équilibrage enregistré pour cet univers.');}
        catch(\DomainException $error){$this->addFlash('error',$error->getMessage());}
        return $this->redirectToRoute('app_admin_catalogue',['id'=>$id,'_fragment'=>$group.'-'.$slug]);
    }

    #[Route('/security-incidents/{id}/review', name: '_security_review', methods: ['POST'])]
    public function reviewSecurityIncident(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_security_review_'.$id, $request->request->get('_token'))) {
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
        if (!$this->isCsrfTokenValid('admin_role_'.$id, $request->request->get('_token'))) {
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
        if (!$this->isCsrfTokenValid('admin_suspend_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try { $admin->setPlayerSuspended($this->getUser(), $id, $request->request->getBoolean('suspend')); }
        catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_players');
    }

    #[Route('/reports/{id}', name: '_report_review', methods: ['POST'])]
    public function review(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_report_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try { $admin->reviewReport($this->getUser(), $id, (string) $request->request->get('decision')); }
        catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_reports');
    }


    #[Route('/universes/{id}/settings', name: '_universe_settings', methods: ['POST'])]
    public function settings(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_settings_'.$id, $request->request->get('_token'))) {
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
            foreach (['multipliers' => ['building_time', 'research_time', 'ship_build_time', 'resource_production', 'travel_time'], 'limits' => ['max_planets', 'max_fleet_size'], 'features' => ['guilds_enabled', 'planet_conquest']] as $group => $keys) {
                $values = is_array($input[$group] ?? null) ? $input[$group] : [];
                $draft[$group] = array_filter(array_intersect_key($values, array_flip($keys)), 'is_scalar');
            }
            $request->getSession()->set('admin_universe_settings_draft', $draft);
        }
        return $this->redirectToRoute('app_admin_universes', ['_fragment' => 'admin-universe-'.$id]);
    }

    #[Route('/universes', name: '_universe_create', methods: ['POST'])]
    public function createUniverse(Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_create_universe', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }

        $fragment = 'admin-universe-create';
        try {
            $universe = $admin->createUniverse(
                $this->getUser(),
                (string) $request->request->get('name'),
                (string) $request->request->get('slug'),
                (string) $request->request->get('profile'),
                (int) $request->request->get('galaxies'),
                (int) $request->request->get('systems_per_galaxy'),
            );
            $request->getSession()->remove('admin_universe_creation_draft');
            $fragment = 'admin-universe-'.$universe->id;
            $this->addFlash('success', 'Univers créé et prêt à accueillir des joueurs.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
            $draft = array_intersect_key(
                $request->request->all(),
                array_flip(['name', 'slug', 'profile', 'galaxies', 'systems_per_galaxy']),
            );
            $draft['error'] = $error->getMessage();
            $request->getSession()->set('admin_universe_creation_draft', $draft);
        }

        return $this->redirectToRoute('app_admin_universes', ['_fragment' => $fragment]);
    }

    #[Route('/universes/{id}/delete', name: '_universe_delete', methods: ['POST'])]
    public function deleteUniverse(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_delete_universe_'.$id, $request->request->get('_token'))) {
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
        if (!$this->isCsrfTokenValid('admin_universe_'.$id, $request->request->get('_token'))) {
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
        return $this->redirectToRoute('app_admin_universes', ['_fragment' => 'admin-universe-'.$id]);
    }
}
