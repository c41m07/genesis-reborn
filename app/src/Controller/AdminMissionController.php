<?php
namespace App\Controller;

use App\Entity\{Fleet, CombatLog, CombatParticipant, EspionageReport};
use App\Service\Admin\AdminMissionMonitoring;
use App\Service\Admin\AdminInterventionService;
use App\Service\Combat\{CombatReportPresenter, OccupationReportState};
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminMissionController extends AbstractController
{
    #[Route('/admin/flottes', name: 'app_admin_missions', methods: ['GET'])]
    public function index(Request $request, AdminMissionMonitoring $monitor): Response
    {
        $universeId = $request->query->get('universe', '');
        $filters = ['universe'=>$universeId === '' ? 0 : $request->query->getInt('universe'), 'mission'=>(string) $request->query->get('mission', ''), 'status'=>(string) $request->query->get('status', 'active'), 'q'=>mb_substr(trim((string) $request->query->get('q', '')), 0, 120)];
        return $this->render('admin/missions.html.twig', $monitor->search($filters, $request->query->getInt('page', 1)));
    }

    #[Route('/admin/flottes/{id}', name: 'app_admin_mission', requirements: ['id'=>'\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em, AdminMissionMonitoring $monitor): Response
    {
        $fleet = $this->mission($id, $em);
        $monitor->recordView($this->getUser(), $fleet);
        return $this->render('admin/mission.html.twig', $monitor->inspect($fleet) + ['request_key'=>bin2hex(random_bytes(16)), 'fleet_version'=>hash('sha256', $fleet->dispatchToken)]);
    }

    #[Route('/admin/flottes/{id}/intervention', name: 'app_admin_mission_intervention', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function intervene(int $id, Request $request, EntityManagerInterface $em, AdminInterventionService $interventions): Response
    {
        if (!$this->isCsrfTokenValid('admin_fleet_'.$id, $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $fleet = $this->mission($id, $em);
        try {
            if (!$request->request->getBoolean('confirmed')) throw new \DomainException('Confirmez explicitement l’intervention avant de l’appliquer.');
            $changed = $interventions->fleet($this->getUser(), $fleet, (string) $request->request->get('operation'), $request->request->getInt('seconds', 0), (string) $request->request->get('reason'), (string) $request->request->get('expected_version'), (string) $request->request->get('request_key'));
            $this->addFlash('success', $changed ? 'Intervention sur la flotte enregistrée.' : 'Cette intervention a déjà été traitée.');
        } catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_mission', ['id'=>$id]);
    }

    #[Route('/admin/flottes/{id}/combat/{reportId}', name: 'app_admin_mission_combat', requirements: ['id'=>'\d+', 'reportId'=>'\d+'], methods: ['GET'])]
    public function combat(int $id, int $reportId, EntityManagerInterface $em, AdminMissionMonitoring $monitor, CombatReportPresenter $presenter, OccupationReportState $occupations, GameCatalog $catalog): Response
    {
        $fleet = $this->mission($id, $em);
        $report = $em->find(CombatLog::class, $reportId);
        if (!$report || !$em->getRepository(CombatParticipant::class)->findOneBy(['fleet'=>$fleet, 'combatEvent'=>$report->combatEvent])) throw $this->createNotFoundException();
        $monitor->recordView($this->getUser(), $fleet, 'combat_report', $reportId);
        $loot = [];
        foreach ($report->snapshot['loot']['fleets'][$fleet->id] ?? [] as $slug=>$amount) if ($amount > 0) $loot[] = ['label'=>$catalog->rules()['resources'][$slug] ?? $slug, 'amount'=>$amount];
        return $this->render('combat/report.html.twig', [
            'report'=>$report, 'membership'=>$fleet->playerUniverse, 'loot_rows'=>$loot, 'capture_state'=>$occupations->forReport($report),
            'report_layout'=>'admin/layout.html.twig', 'report_back_url'=>$this->generateUrl('app_admin_mission', ['id'=>$id]),
            'monitoring_context'=>'Vue administrative de l’empire '.$fleet->playerUniverse->getDisplayName().' · Flotte n° '.$id,
        ] + $presenter->present($report->snapshot, $report->rounds, $fleet->playerUniverse->id));
    }

    #[Route('/admin/flottes/{id}/renseignement/{reportId}', name: 'app_admin_mission_espionage', requirements: ['id'=>'\d+', 'reportId'=>'\d+'], methods: ['GET'])]
    public function espionage(int $id, int $reportId, EntityManagerInterface $em, AdminMissionMonitoring $monitor): Response
    {
        $fleet = $this->mission($id, $em);
        $report = $em->find(EspionageReport::class, $reportId);
        if (!$report || $fleet->mission !== 'spy' || $report->sender->id !== $fleet->playerUniverse->id || $report->target->id !== $fleet->destination?->id) throw $this->createNotFoundException();
        $monitor->recordView($this->getUser(), $fleet, 'espionage_report', $reportId);
        return $this->render('espionage/report.html.twig', ['report'=>$report, 'report_layout'=>'admin/layout.html.twig',
            'report_back_url'=>$this->generateUrl('app_admin_mission', ['id'=>$id]),
            'monitoring_context'=>'Renseignement de '.$fleet->playerUniverse->getDisplayName().' sur cette cible ; la mission d’origine n’est pas identifiée dans le rapport.',
        ]);
    }

    private function mission(int $id, EntityManagerInterface $em): Fleet
    {
        $fleet = $em->find(Fleet::class, $id);
        if (!$fleet || $fleet->mission === 'idle') throw $this->createNotFoundException('Mission introuvable.');
        return $fleet;
    }
}
