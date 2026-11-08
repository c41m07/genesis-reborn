<?php
namespace App\Controller;

use App\Entity\Fleet;
use App\Entity\QueueItem;
use App\Service\Boost\BoostGrantService;
use App\Service\Boost\BoostUseService;
use App\Service\Universe\UniverseContext;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\QueueService;
use App\Service\Player\VacationCalendar;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BoostController extends AbstractController
{
    #[Route('/boosts', name: 'app_boosts', methods: ['GET'])]
    public function index(
        UniverseContext $context,
        BoostGrantService $grants,
        GameRepository $repo,
        GameCatalog $catalog,
        QueueService $queues,
        VacationCalendar $vacations,
        ClockInterface $clock,
    ): Response {
        $membership = $context->membership();
        $queues->synchronize($membership);
        $vacation = $vacations->currentPeriod($membership);
        $definitions = array_merge($catalog->all('buildings'), $catalog->all('technologies'), $catalog->all('ships'), $catalog->all('defenses'));
        $activeQueues = [];
        foreach ($repo->activeQueueItemsFor($membership) as $item) {
            if ($item->finishAt() > $clock->now()) {
                $definition = $definitions[$item->itemSlug] ?? null;
                $activeQueues[] = ['item' => $item, 'label' => $definition ? $catalog->withTier($definition, $item->tier)['label'] : $item->itemSlug];
            }
        }
        $activeFleets = array_values(array_filter($repo->activeFleetsForBoost($membership), static fn (Fleet $fleet): bool => $fleet->arrivalAt > $clock->now()));

        return $this->render('boosts/index.html.twig', [
            'membership' => $membership,
            'boosts' => $grants->inventory($membership),
            'pending_boost_count' => $grants->pendingCount($membership),
            'inventory_capacity' => $grants->inventoryCapacity(),
            'duration_reduction_percent' => $grants->durationReductionRatio() * 100,
            'active_queues' => $activeQueues,
            'active_fleets' => $activeFleets,
            'vacation' => $vacation,
        ]);
    }

    #[Route('/boosts/utiliser/{type}/{id}', name: 'app_boost_use', methods: ['POST'], requirements: ['type' => 'queue|fleet', 'id' => '\\d+'])]
    public function use(
        string $type,
        int $id,
        Request $request,
        UniverseContext $context,
        BoostUseService $boosts,
        BoostGrantService $grants,
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
            $this->addFlash('success', 'Boost appliqué : ' . number_format($grants->durationReductionRatio() * 100, 2, ',', ' ') . ' % du temps restant a été retiré.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_boosts');
    }
}
