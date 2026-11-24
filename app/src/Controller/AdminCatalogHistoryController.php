<?php

namespace App\Controller;

use App\Entity\Universe;
use App\Repository\CatalogHistoryRepository;
use App\Repository\GameRepository;
use App\Service\Admin\CatalogBalanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminCatalogHistoryController extends AbstractController
{
    #[Route('/admin/universes/{id}/catalogue/{group}/{slug}/historique', name: 'app_admin_catalogue_history', requirements: ['id' => '\d+', 'group' => 'buildings|technologies|ships|defenses'], methods: ['GET'])]
    public function history(int $id, string $group, string $slug, Request $request, GameRepository $repository, CatalogBalanceService $balance, CatalogHistoryRepository $history): Response
    {
        $universe = $repository->find(Universe::class, $id);
        if (!$universe instanceof Universe) {
            throw $this->createNotFoundException('Univers introuvable.');
        }
        $entries = $balance->entries($universe, $group, $slug);
        if (empty($entries[$group])) {
            throw $this->createNotFoundException('Entrée introuvable.');
        }
        $item = $entries[$group][0];
        $page = $history->page($id, $group, $slug, max(0, $request->query->getInt('before')));
        foreach ($page['rows'] as &$row) {
            // Une ancienne trace peut ne contenir que le delta. Ne jamais inventer son état complet.
            $row['restorable'] = ($row['details']['version']['format'] ?? null) === 1;
            $row['comparison'] = [];
            foreach ($item['fields'] as $field) {
                $values = [];
                foreach (['before', 'after'] as $side) {
                    $value = $row['details'][$side] ?? null;
                    foreach (explode('.', $field['path']) as $part) {
                        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : null;
                    }
                    $values[$side] = is_int($value) || is_float($value) ? $value : null;
                }
                $row['comparison'][] = ['label' => $field['label'], 'current' => $field['value']] + $values;
            }
            $row['reason'] = is_string($row['details']['reason'] ?? null) ? $row['details']['reason'] : 'Motif non enregistré';
            unset($row['details']);
        }
        unset($row);
        return $this->render('admin/catalogue_history.html.twig', [
            'universe' => $universe, 'group' => $group, 'item' => $item, 'page' => $page,
        ]);
    }

    #[Route('/admin/universes/{id}/catalogue/{group}/{slug}/restaurer/{source}', name: 'app_admin_catalogue_restore', requirements: ['id' => '[0-9]+', 'source' => '[0-9]+', 'group' => 'buildings|technologies|ships|defenses'], methods: ['GET', 'POST'])]
    public function restore(int $id, string $group, string $slug, int $source, Request $request, GameRepository $repository, CatalogBalanceService $balance): Response
    {
        $universe = $repository->find(Universe::class, $id);
        if (!$universe instanceof Universe) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $item = $balance->entries($universe, $group, $slug, false)[$group][0] ?? null;
        if ($item === null) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $key = 'catalogue_restore_'.$id.'_'.$group.'_'.$slug.'_'.$source;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid($key, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }
            try {
                $revision = $request->request->getString('revision');
                // L’aperçu est attaché à cette session, cette source et cet état du catalogue.
                // Ni une requête directe ni une confirmation d’un autre onglet ne le remplacent.
                if (!$request->request->getBoolean('confirmed') || $revision === ''
                    || $request->getSession()->get($key) !== $revision) {
                    throw new \DomainException('Ouvrez l’aperçu et confirmez explicitement la restauration.');
                }
                $balance->save($this->getUser(), $id, $group, $slug, [], $revision, $request->request->getString('reason'), $source);
                $request->getSession()->remove($key);
                $this->addFlash('success', 'Valeurs historiques réappliquées et journalisées pour cette fiche.');
            } catch (\DomainException $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('app_admin_catalogue_history', ['id' => $id, 'group' => $group, 'slug' => $slug]);
        }
        try {
            $preview = $balance->previewRestoration($universe, $group, $slug, $source);
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
            return $this->redirectToRoute('app_admin_catalogue_history', ['id' => $id, 'group' => $group, 'slug' => $slug]);
        }
        $request->getSession()->set($key, $preview['revision']);
        return $this->render('admin/catalogue_restore.html.twig', [
            'universe' => $universe, 'group' => $group, 'slug' => $slug, 'source' => $source,
            'preview' => $preview, 'tokenKey' => $key, 'itemLabel' => $item['label'],
        ]);
    }
}
