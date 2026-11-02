<?php
namespace App\Controller;

use App\Entity\Fleet;
use App\Entity\QueueItem;
use App\Repository\GameRepository;
use App\Service\Boost\BoostGrantService;
use App\Service\Boost\BoostUseService;
use App\Service\Queue\QueueService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BoostController extends AbstractController
{
    #[Route('/boosts', name: 'app_boosts', methods: ['GET'])]
    public function index(
        UniverseContext $context,
        GameRepository $repo,
        BoostGrantService $grants,
        QueueService $queues,
    ): Response {
        $membership = $context->membership();
        $queues->synchronize($membership);
        $activeEvents = $repo->boostEventsForUniverse('active', $membership->universe);
        $eventProgress = [];
        foreach ($activeEvents as $event) {
            $eventProgress[$event->id] = $repo->boostEventContributionTotal($event, $membership);
        }

        return $this->render('boosts/index.html.twig', [
            'membership' => $membership,
            'boosts' => $grants->inventory($membership),
            'inventory_capacity' => $grants->inventoryCapacity(),
            'queue_items' => $repo->activeQueueItemsFor($membership),
            'active_fleets' => $repo->activeFleetsForBoost($membership),
            'active_events' => $activeEvents,
            'event_progress' => $eventProgress,
            'scheduled_events' => $repo->boostEventsForUniverse('scheduled', $membership->universe),
        ]);
    }

    #[Route('/boosts/utiliser/{type}/{id}', name: 'app_boost_use', methods: ['POST'], requirements: ['type' => 'queue|fleet', 'id' => '\\d+'])]
    public function use(
        string $type,
        int $id,
        Request $request,
        UniverseContext $context,
        BoostUseService $boosts,
    ): Response {
        if (!$this->isCsrfTokenValid('boost-' . $type . '-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }

        try {
            $membership = $context->membership();
            $requestKey = (string) $request->request->get('request_key');
            if ($type === 'queue') {
                $boosts->useOnQueue($membership, $id, $requestKey);
            } else {
                $boosts->useOnFleet($membership, $id, $requestKey);
            }
            $this->addFlash('success', 'Boost appliqué : 25 % du temps restant a été retiré.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_boosts');
    }
}
