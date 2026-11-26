<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\{GameRepository, ReportSummaryRepository};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DiplomaticReportController extends AbstractController
{
    #[Route('/rapports/diplomatie/{id}', name: 'app_diplomatic_report', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UniverseContext $context, ReportSummaryRepository $reports, GameRepository $repo): Response
    {
        $membership = $context->membership();
        $report = $reports->diplomatic($membership, [$id])[$id] ?? throw $this->createNotFoundException();
        if ($repo->isReportRemoved($membership, 'diplomatic', $id)) {
            throw $this->createNotFoundException();
        }

        return $this->render('diplomatic_report/show.html.twig', ['report' => $report], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
}
