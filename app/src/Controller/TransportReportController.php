<?php
namespace App\Controller;

use App\Entity\TransportReport;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TransportReportController extends AbstractController
{
    #[Route('/rapports/transport/{id}', name: 'app_transport_report', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UniverseContext $context, GameRepository $repo, GameCatalog $catalog): Response
    {
        $member = $context->membership();
        $report = $repo->find(TransportReport::class, $id);
        if (!$report || !in_array($member->id, [$report->sender->id, $report->recipient->id], true)) throw $this->createNotFoundException();
        return $this->render('transport/report.html.twig', ['report' => $report, 'resources' => $catalog->rules()['resources']]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/transports', name: 'app_admin_transports', methods: ['GET'])]
    public function admin(Request $request, GameRepository $repo, GameCatalog $catalog): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        return $this->redirectToRoute('app_admin_missions', ['mission'=>'transport', 'status'=>'', 'page'=>$page]);
    }
}
