<?php
namespace App\Controller;

use App\Entity\{AdminAuditLog, MessageReport, Player, Universe};
use App\Repository\GameRepository;
use App\Service\Admin\AdminService;
use App\Service\Catalog\GameCatalog;
use App\Service\Universe\UniverseLifecycleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'app_admin')]
final class AdminController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET'])]
    public function index(GameRepository $repo, GameCatalog $catalog, AdminService $admin): Response
    {
        $reports = $repo->all(MessageReport::class, ['status' => 'open'], ['createdAt' => 'DESC']);
        $admin->recordReportViews($this->getUser(), $reports);
        $universes = $repo->all(Universe::class, [], ['id' => 'ASC']);
        $universeConfigs = [];
        foreach ($universes as $universe) { $universeConfigs[$universe->id] = $catalog->forUniverse($universe); }
        return $this->render('admin/index.html.twig', [
            'players' => $repo->all(Player::class, [], ['id' => 'DESC']),
            'universes' => $universes,
            'universeConfigs' => $universeConfigs,
            'reports' => $reports,
            'auditLogs' => $repo->all(AdminAuditLog::class, [], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/players/{id}/suspension', name: '_player_suspend', methods: ['POST'])]
    public function suspend(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_suspend_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try { $admin->setPlayerSuspended($this->getUser(), $id, $request->request->getBoolean('suspend')); }
        catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_index');
    }

    #[Route('/reports/{id}', name: '_report_review', methods: ['POST'])]
    public function review(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_report_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try { $admin->reviewReport($this->getUser(), $id, (string) $request->request->get('decision')); }
        catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_index');
    }


    #[Route('/universes/{id}/settings', name: '_universe_settings', methods: ['POST'])]
    public function settings(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_settings_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try { $admin->setUniverseSettings($this->getUser(), $id, $request->request->all()); }
        catch (\Exception $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_index');
    }

    #[Route('/universes/{id}/schedule', name: '_universe_schedule', methods: ['POST'])]
    public function schedule(int $id, Request $request, AdminService $admin): Response
    {
        if (!$this->isCsrfTokenValid('admin_universe_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $startRaw = trim((string) $request->request->get('starts_at'));
            $endRaw = trim((string) $request->request->get('ends_at'));
            $startsAt = $startRaw === '' ? null : new \DateTimeImmutable($startRaw, new \DateTimeZone('UTC'));
            $endsAt = $endRaw === '' ? null : new \DateTimeImmutable($endRaw, new \DateTimeZone('UTC'));
            $admin->setUniverseSchedule($this->getUser(), $id, $startsAt, $endsAt);
        } catch (\Exception $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_admin_index');
    }
}
