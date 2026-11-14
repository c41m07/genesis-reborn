<?php
namespace App\Controller;

use App\Entity\CombatLog;
use App\Service\Combat\CombatReportReader;
use App\Service\Combat\CombatReportPresenter;
use App\Service\Catalog\GameCatalog;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CombatReportController extends AbstractController
{
    #[Route('/rapports', name: 'app_combat_reports', methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, \App\Service\Combat\ReportArchiveService $archives): Response
    {
        $membership = $context->membership();
        $filter = $request->query->getString('type', 'all');
        if (!in_array($filter, ['all', 'combat', 'economy', 'espionage'], true)) $filter = 'all';
        $archived = $request->query->getString('view') === 'archives';
        $error=null;$status=200;
        try {
            $criteria=new \App\Service\Search\SearchCriteria($request->query->all(),$membership->player->preferences['timezone']??'Europe/Paris');
            $data=$archives->page($membership,$filter,$archived,$request->query->getInt('page',1),$criteria);
        } catch(\DomainException $e) {
            $error=$e->getMessage();$status=422;$criteria=new \App\Service\Search\SearchCriteria([]);$data=['entries'=>[],'report_count'=>0,'page'=>1,'pages'=>1];
        }
        return $this->render('combat/reports.html.twig', $data + ['membership'=>$membership, 'filter'=>$filter, 'archived'=>$archived,'criteria'=>$criteria->values,'search_filters'=>$criteria->url(),'search_error'=>$error],new Response(status:$status));
    }

    #[Route('/rapports/archives', name: 'app_report_archive_change', methods: ['POST'])]
    public function archive(Request $request, UniverseContext $context, \App\Service\Combat\ReportArchiveService $archives): Response
    {
        if (!$this->isCsrfTokenValid('report_archive', $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $member = $context->membership();
        $filter = $request->request->getString('type', 'all');
        if (!in_array($filter, ['all', 'combat', 'economy', 'espionage'], true)) $filter = 'all';
        $view = $request->request->getString('view') === 'archives' ? 'archives' : '';
        try {
            $operation = $request->request->getString('operation');
            if (!in_array($operation, ['archive', 'restore'], true)) throw new \DomainException('Action sur les rapports invalide.');
            $keys = $request->request->has('single') ? [$request->request->getString('single')] : $request->request->all('selected');
            $archives->change($member, $keys, $operation === 'archive');
            $this->addFlash('success', $operation === 'archive' ? 'Rapports archivés. Vous pouvez les restaurer depuis les archives.' : 'Rapports restaurés dans votre liste.');
        } catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        try {$criteria=new \App\Service\Search\SearchCriteria($request->request->all(),$member->player->preferences['timezone']??'Europe/Paris');$extra=$criteria->url();} catch(\DomainException) {$extra=[];}
        return $this->redirectToRoute('app_combat_reports', $extra+['type'=>$filter, 'view'=>$view, 'page'=>max(1, $request->request->getInt('page', 1))]);
    }

    #[Route('/rapports/combat/{id}', name: 'app_combat_report', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UniverseContext $context, CombatReportReader $reader, GameCatalog $catalog, CombatReportPresenter $presenter, \App\Service\Combat\OccupationReportState $occupations): Response
    {
        $membership = $context->membership();
        $log = $reader->findVisible($membership, $id);
        if (!$log) {
            throw $this->createNotFoundException();
        }
        $presentation = $presenter->present($log->snapshot, $log->rounds, $membership->id);
        $ownLoot = [];
        foreach ($log->snapshot['loot']['fleets'] ?? [] as $fleetId => $resources) {
            foreach ($log->snapshot['participants'] ?? [] as $participant) {
                if ((int) ($participant['fleet_id'] ?? 0) === (int) $fleetId && (int) ($participant['player_universe_id'] ?? 0) === $membership->id) {
                    foreach ($resources as $slug => $amount) {
                        $ownLoot[$slug] = ($ownLoot[$slug] ?? 0) + (int) $amount;
                    }
                }
            }
        }
        $lootRows = [];
        foreach ($ownLoot as $slug => $amount) {
            if ($amount > 0) {
                $lootRows[] = ['label' => $catalog->rules()['resources'][$slug] ?? $slug, 'amount' => $amount];
            }
        }

        return $this->render('combat/report.html.twig', ['report' => $log, 'loot_rows' => $lootRows, 'membership' => $membership, 'capture_state' => $occupations->forReport($log)] + $presentation);
    }
}
