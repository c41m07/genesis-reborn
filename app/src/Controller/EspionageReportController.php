<?php
namespace App\Controller;

use App\Service\Fleet\EspionageReportReader;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EspionageReportController extends AbstractController
{
    #[Route('/rapports/espionnage/{id}', name: 'app_espionage_report', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UniverseContext $context, EspionageReportReader $reader): Response
    {
        $membership = $context->membership();
        $report = $reader->findVisible($membership, $id);
        if (!$report) throw $this->createNotFoundException();
        return $this->render('espionage/report.html.twig', ['report' => $report, 'incoming_detection' => $report->sender->id !== $membership->id], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
}
