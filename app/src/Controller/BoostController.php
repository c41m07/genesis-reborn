<?php
namespace App\Controller;

use App\Service\Boost\InventoryGiftNotices;
use App\Service\Boost\ResourceBoxUseService;
use App\Service\Boost\BoxOverflowConfirmation;
use App\Service\Boost\BoostGrantService;
use App\Service\Boost\BoostUseService;
use App\Service\Universe\UniverseContext;
use App\Service\Queue\QueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BoostController extends AbstractController
{
    #[Route('/boosts', name: 'app_boosts', defaults: ['inventory_page' => 'boosts'], methods: ['GET'])]
    #[Route('/inventaire/boites', name: 'app_inventory_boxes', defaults: ['inventory_page' => 'boxes'], methods: ['GET'])]
    #[Route('/inventaire/recompenses', name: 'app_inventory_pending', defaults: ['inventory_page' => 'pending'], methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, QueueService $queues, \App\Service\Boost\InventoryPageView $view, InventoryGiftNotices $notices): Response
    {
        $membership = $context->membership();
        // Conserver le règlement des commandes échues avant de lire les opérations disponibles.
        $queues->synchronize($membership);
        $data = $view->prepare($membership, $request->attributes->getString('inventory_page'), $request->query->getInt('page', 1));
        if (isset($data['displayed_unread_boxes']) && $request->isMethod('GET')) {
            $data['overflow'] = $request->getSession()->get('box_overflow_'.$membership->id);
            $notices->acknowledgeDisplayedBoxes($membership, $data['displayed_unread_boxes']);
        }

        return $this->render('boosts/index.html.twig', $data, new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/inventaire/boites/{id}/ouvrir', name:'app_resource_box_open', requirements:['id'=>'\d+'], methods:['POST'])]
    public function openBox(int $id, Request $request, UniverseContext $context, ResourceBoxUseService $boxes): Response
    {
        if (!$this->isCsrfTokenValid('box_open_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $membership = $context->membership();
        $planet = $request->request->getInt('planet_id');
        $key = $request->request->getString('request_key');
        $session = $request->getSession();
        $slot = 'box_overflow_'.$membership->id;
        $preview = $session->get($slot);
        $confirmed = $request->request->getString('confirm_loss') === '1'
            && $preview !== null && $preview['id'] === $id && $preview['planet'] === $planet && $preview['key'] === $key;
        try {
            $changed = $boxes->open($membership, $id, $planet, $key, $confirmed ? $preview['loss'] : []);
            $session->remove($slot);
            $this->addFlash('success', $changed ? 'Boîte ouverte. Les ressources pouvant être stockées ont été déposées.' : 'Cette ouverture a déjà été traitée.');
        } catch (BoxOverflowConfirmation $warning) {
            $session->set($slot, ['id' => $id, 'planet' => $planet, 'key' => $key, 'estimate' => $warning->estimate, 'loss' => $warning->loss, 'colony' => $warning->colony]);
        } catch (\DomainException $error) {
            $session->remove($slot);
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_inventory_boxes', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/boosts/temporaires/{id}/activer', name: 'app_timed_boost_activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function activateTimed(int $id, Request $request, UniverseContext $context, \App\Service\Boost\TimedBoostUseService $boosts): Response
    {
        if (!$this->isCsrfTokenValid('timed_boost_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $changed = $boosts->activate($context->membership(), $id, $request->request->getString('request_key'));
            $this->addFlash('success', $changed ? 'Bonus actif pendant cinq heures.' : 'Cette activation a déjà été traitée.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_boosts', status: Response::HTTP_SEE_OTHER);
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

        // Un formulaire ouvert avant le retrait garde une réponse explicite, sans reçu ni écriture de jeu.
        if ($type === 'fleet') {
            $this->addFlash('error', 'Les boosts de l’inventaire ne s’appliquent plus aux missions de flotte.');
            return $this->redirectToRoute('app_boosts');
        }

        try {
            $membership = $context->membership();
            $requestKey = (string) $request->request->get('request_key');
            $boosts->useOnQueue($membership, $id, $requestKey);
            $this->addFlash('success', 'Boost appliqué : ' . number_format($grants->durationReductionRatio() * 100, 2, ',', ' ') . ' % du temps restant a été retiré.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_boosts');
    }
}
