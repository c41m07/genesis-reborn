<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ReportNoticeRepository;
use App\Service\Universe\UniverseContext;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class ReportNoticeController extends AbstractController
{
    private const PRIVATE = ['Cache-Control' => 'private, no-store'];

    #[Route('/rapports/non-lus', name: 'app_report_notices', methods: ['GET'])]
    public function unread(UniverseContext $context, ReportNoticeRepository $notices): JsonResponse
    {
        $membership = $context->membership();

        return $this->json([
            'membership' => $membership->id,
            'count' => $notices->count($membership),
        ], headers: self::PRIVATE);
    }

    #[Route('/rapports/{kind}/{id}/consultation', name: 'app_report_notice_consult', requirements: ['kind' => 'combat|espionage|transport|diplomatic', 'id' => '\d+'], methods: ['POST'])]
    public function consult(string $kind, int $id, Request $request, UniverseContext $context, ReportNoticeRepository $notices, ClockInterface $clock): Response
    {
        $membership = $context->membership();
        $intent = 'report-notice-'.$membership->id.'-'.$kind.'-'.$id;
        if (!$this->isCsrfTokenValid($intent, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez le rapport.');
        }
        if (!$notices->consult($membership, $kind, $id, $clock->now())) {
            throw $this->createNotFoundException();
        }

        if ($request->getPreferredFormat() === 'json') {
            return $this->json(['consulted' => true], headers: self::PRIVATE);
        }

        $route = match ($kind) {
            'combat' => 'app_combat_report',
            'espionage' => 'app_espionage_report',
            'transport' => 'app_transport_report',
            'diplomatic' => 'app_diplomatic_report',
        };
        // Revenir à la fiche connue, sans accepter d’URL de redirection fournie par le client.
        $parameters = ['id' => $id];
        foreach (['type', 'view', 'page', 'planet', 'participant', 'from', 'until'] as $key) {
            $value = $request->query->getString($key);
            if ($value !== '' && strlen($value) <= 100) {
                $parameters[$key] = $value;
            }
        }
        return $this->redirectToRoute($route, $parameters, Response::HTTP_SEE_OTHER);
    }
}
