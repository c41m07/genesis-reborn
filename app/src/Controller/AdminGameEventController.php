<?php

namespace App\Controller;

use App\Entity\{GameEvent, Player, Universe};
use App\Repository\GameRepository;
use App\Service\Event\GameEventService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminGameEventController extends AbstractController
{
    #[Route('/admin/events', name: 'app_admin_game_events', methods: ['GET', 'POST'])]
    public function index(Request $request, GameRepository $repo, GameEventService $events): Response
    {
        $draft = ['name' => '', 'description' => '', 'type' => 'announcement', 'status' => 'draft', 'starts_at' => '', 'ends_at' => '', 'universe_id' => 0, 'percentage' => '', 'visible' => false];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('game_event_create', $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $draft = array_replace($draft, $request->request->all());
            try {
                $events->save($this->admin(), null, $draft + ['parameters' => ['percentage' => $draft['percentage'] ?? null]]);
                $this->addFlash('success', 'Événement créé.');
                return $this->redirectToRoute('app_admin_game_events');
            } catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        }
        return $this->render('admin/events.html.twig', ['events' => $repo->all(GameEvent::class, [], ['startsAt' => 'DESC']), 'universes' => $repo->all(Universe::class), 'draft' => $draft, 'types' => GameEventService::TYPES, 'statuses' => GameEventService::STATUSES]);
    }

    #[Route('/admin/events/{id}/edit', name: 'app_admin_game_event_edit', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function edit(int $id, Request $request, GameEventService $events): Response
    {
        if (!$this->isCsrfTokenValid('game_event_edit_'.$id, $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try { $events->save($this->admin(), $id, $request->request->all() + ['parameters' => ['percentage' => $request->request->get('percentage')]]); $this->addFlash('success', 'Événement modifié.'); }
        catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_game_events');
    }

    #[Route('/admin/events/{id}/cancel', name: 'app_admin_game_event_cancel', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function cancel(int $id, Request $request, GameEventService $events): Response
    {
        if (!$this->isCsrfTokenValid('game_event_cancel_'.$id, $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try { $events->cancel($this->admin(), $id); $this->addFlash('success', 'Événement annulé.'); } catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_game_events');
    }

    #[Route('/admin/events/{id}/delete', name: 'app_admin_game_event_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(int $id, Request $request, GameEventService $events): Response
    {
        if (!$this->isCsrfTokenValid('game_event_delete_'.$id, $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try { $events->deleteDraft($this->admin(), $id); $this->addFlash('success', 'Brouillon supprimé.'); } catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_game_events');
    }

    private function admin(): Player
    {
        $user = $this->getUser();
        if (!$user instanceof Player) throw $this->createAccessDeniedException();
        return $user;
    }
}
