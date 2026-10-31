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
        $report = $reader->findVisible($context->membership(), $id);
        if (!$report) throw $this->createNotFoundException();
        return $this->render('espionage/report.html.twig', ['report' => $report]);
    }
}
