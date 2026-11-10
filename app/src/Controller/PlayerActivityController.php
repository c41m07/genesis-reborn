<?php
namespace App\Controller;

use App\Service\Player\PlayerActivityService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class PlayerActivityController extends AbstractController
{
    #[Route('/activite/presence', name: 'app_activity_presence', methods: ['POST'])]
    public function sample(Request $request, UniverseContext $context, PlayerActivityService $activity): Response
    {
        if (!$this->isCsrfTokenValid('activity-presence', $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $membership = $context->membership();
        if ($request->request->getInt('membership') !== $membership->id) return new Response('', 409);
        $idle = $request->request->getInt('idle_seconds');
        if ($idle < 0 || $idle >= 300) return new Response('', 422);
        $activity->record($membership, $idle);
        return new Response('', 204, ['Cache-Control'=>'no-store']);
    }
}
