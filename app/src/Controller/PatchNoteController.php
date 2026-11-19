<?php

declare (strict_types=1);
namespace App\Controller;

use App\Entity\{AdminAuditLog, PatchNote};
use App\Repository\PatchNoteRepository;
use App\Service\Admin\PatchNoteEditor;
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
            // L’ouverture de cette page enregistre la note affichée en tête comme lue.
            $player->preferences['last_patch_note_read_id'] = $patchNotes[0]->id;
            $em->flush();
        }
        return $this->render('patchnotes/index.html.twig', ['patchNotes' => $patchNotes]);
    }
    #[Route('/admin/patchnotes', name: 'app_admin_patchnotes', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(Request $request, PatchNoteRepository $notes, EntityManagerInterface $em, PatchNoteEditor $editor): Response
    {
        // Les anciens formulaires peuvent toujours créer une note à cette adresse.
        if ($request->isMethod('POST')) {
            return $this->create($request, $em, $editor);
        }
        $status = $request->query->getString('status');
        $search = trim($request->query->getString('q'));
        return $this->render('admin/patchnotes.html.twig', $notes->page($status, $search, $request->query->getInt('page', 1)) + [
            'statusFilter' => $status,
            'search' => $search,
        ]);
    }

    #[Route('/admin/patchnotes/nouvelle', name: 'app_admin_patchnote_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, EntityManagerInterface $em, PatchNoteEditor $editor): Response
    {
        $note = new PatchNote();
        $draft = $editor->draft($note);
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('patchnote_create', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $draft = $this->submittedDraft($request);
            try {
                $editor->apply($note, $draft);
                $em->persist($note);
                $em->flush();
                $this->audit($em, 'patchnote_created', $note);
                $em->flush();
                $this->addFlash('success', 'Patchnote créée.');
                return $this->redirectToRoute('app_admin_patchnotes');
            } catch (\DomainException $exception) {
                $error = $exception->getMessage();
            }
        }
        return $this->render('admin/patchnote_form.html.twig', [
            'draft' => $draft, 'editing' => null, 'error' => $error,
        ], new Response(status: $error === null ? 200 : 422));
    }

    #[Route('/admin/patchnotes/{id}', name: 'app_admin_patchnote_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(PatchNote $note, Request $request, EntityManagerInterface $em, PatchNoteEditor $editor): Response
    {
        $draft = $editor->draft($note);
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('patchnote_edit_' . $note->id, $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $draft = $this->submittedDraft($request);
            $old = $note->status;
            try {
                $editor->apply($note, $draft);
                $note->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $action = 'patchnote_updated';
                if ($old !== $note->status) {
                    $action = $note->status === 'published' ? 'patchnote_published' : 'patchnote_unpublished';
                }
                $this->audit($em, $action, $note);
                $em->flush();
                $this->addFlash('success', 'Patchnote mise à jour.');
                return $this->redirectToRoute('app_admin_patchnotes');
            } catch (\DomainException $exception) {
                $error = $exception->getMessage();
            }
        }
        return $this->render('admin/patchnote_form.html.twig', [
            'draft' => $draft, 'editing' => $note, 'error' => $error,
        ], new Response(status: $error === null ? 200 : 422));
    }

    #[Route('/admin/patchnotes/{id}/supprimer', name: 'app_admin_patchnote_remove', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function confirmDelete(PatchNote $note): Response
    {
        return $this->render('admin/patchnote_delete.html.twig', ['note' => $note]);
    }

    #[Route('/admin/patchnotes/{id}/delete', name: 'app_admin_patchnote_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(PatchNote $note, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('patchnote_delete_' . $note->id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $this->audit($em, 'patchnote_deleted', $note);
        $em->remove($note);
        $em->flush();
        $this->addFlash('success', 'Patchnote supprimée.');
        return $this->redirectToRoute('app_admin_patchnotes');
    }

    /** @return array{title:string, version:string, summary:string, content:string, status:string, publishedAt:string} */
    private function submittedDraft(Request $request): array
    {
        $draft = [];
        foreach (['title', 'version', 'summary', 'content', 'status', 'publishedAt'] as $field) {
            $draft[$field] = $request->request->getString($field);
        }
        return $draft;
    }

    private function audit(EntityManagerInterface $em, string $action, PatchNote $note): void
    {
        $entry = new AdminAuditLog();
        $entry->actor = $this->getUser();
        $entry->action = $action;
        $entry->targetType = 'patch_note';
        $entry->targetId = $note->id ?? 0;
        $entry->details = ['title' => $note->title, 'version' => $note->version];
        $entry->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $em->persist($entry);
    }
}
