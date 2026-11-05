<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\{AdminAuditLog, PatchNote};
use App\Repository\PatchNoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PatchNoteController extends AbstractController
{
    #[Route('/patchnotes', name: 'app_patchnotes', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(PatchNoteRepository $notes, EntityManagerInterface $em): Response
    {
        $patchNotes = $notes->published();
        $player = $this->getUser();
        if ($player instanceof \App\Entity\Player && $patchNotes !== []) {
            $player->preferences['last_patch_note_read_id'] = $patchNotes[0]->id;
            $em->flush();
        }
        return $this->render('patchnotes/index.html.twig', ['patchNotes' => $patchNotes]);
    }

    #[Route('/admin/patchnotes', name: 'app_admin_patchnotes', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(Request $request, PatchNoteRepository $notes, EntityManagerInterface $em): Response
    {
        $note = new PatchNote();
        $status = $request->query->getString('status');
        $search = trim($request->query->getString('q'));
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('patchnote_create', $request->request->get('_token'))) throw $this->createAccessDeniedException();
            $this->fill($note, $request);
            if ($note->title === '' || $note->version === '' || $note->summary === '' || $note->content === '') {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
            } else {
                $em->persist($note);
                $em->flush();
                $this->audit($em, 'patchnote_created', $note);
                $em->flush();
                $this->addFlash('success', 'Patchnote créée.');
                return $this->redirectToRoute('app_admin_patchnotes');
            }
        }
        return $this->render('admin/patchnotes.html.twig', ['patchNotes' => $notes->filtered($status, $search), 'draft' => $note, 'statusFilter' => $status, 'search' => $search]);
    }

    #[Route('/admin/patchnotes/{id}', name: 'app_admin_patchnote_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(PatchNote $note, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('patchnote_edit_'.$note->id, $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $old = $note->status;
        $this->fill($note, $request);
        $note->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $action = $old !== $note->status ? ($note->status === 'published' ? 'patchnote_published' : 'patchnote_unpublished') : 'patchnote_updated';
        $this->audit($em, $action, $note);
        $em->flush();
        $this->addFlash('success', 'Patchnote mise à jour.');
        return $this->redirectToRoute('app_admin_patchnotes');
    }

    #[Route('/admin/patchnotes/{id}/delete', name: 'app_admin_patchnote_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(PatchNote $note, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('patchnote_delete_'.$note->id, $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $this->audit($em, 'patchnote_deleted', $note);
        $em->remove($note);
        $em->flush();
        $this->addFlash('success', 'Patchnote supprimée.');
        return $this->redirectToRoute('app_admin_patchnotes');
    }

    private function fill(PatchNote $note, Request $request): void
    {
        $data = $request->request;
        $note->title = trim($data->getString('title'));
        $note->version = trim($data->getString('version'));
        $note->summary = trim($data->getString('summary'));
        $note->content = trim($data->getString('content'));
        $note->status = $data->getString('status') === 'published' ? 'published' : 'draft';
        $date = trim($data->getString('publishedAt'));
        $note->publishedAt = $date !== '' ? new \DateTimeImmutable($date, new \DateTimeZone('UTC')) : ($note->status === 'published' ? new \DateTimeImmutable('now', new \DateTimeZone('UTC')) : null);
        if ($note->status === 'draft') $note->publishedAt = null;
    }

    private function audit(EntityManagerInterface $em, string $action, PatchNote $note): void
    {
        $entry = new AdminAuditLog();
        $entry->actor = $this->getUser(); $entry->action = $action; $entry->targetType = 'patch_note'; $entry->targetId = $note->id ?? 0;
        $entry->details = ['title' => $note->title, 'version' => $note->version]; $entry->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $em->persist($entry);
    }
}
