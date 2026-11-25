<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Player, Universe};
use App\Repository\{AdventWindowRepository, GameRepository};
use App\Service\Advent\{AdventAdminCaptureStatus, AdventCalendar, AdventEditions, AdventGifts};
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminAdventController extends AbstractController
{
    private const PREVIEW = 'admin_advent_preview';

    #[Route('/admin/events/avent', name: 'app_admin_game_event_advent', methods: ['GET', 'POST'])]
    public function form(Request $request, AdventEditions $editions, GameRepository $game, AdventWindowRepository $windows, ClockInterface $clock, AdventAdminCaptureStatus $captureStatus): Response
    {
        $season = (int) $clock->now()->setTimezone(new \DateTimeZone(AdventCalendar::PARIS))->format('Y');
        $draft = ['name' => 'Calendrier de l’Avent', 'season' => $season, 'universe' => null,
            'from' => $season.'-12-01T00:00', 'until' => $season.'-12-31T00:00'];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(self::PREVIEW, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }
            $draft = [
                'name' => $request->request->getString('name'),
                'season' => $request->request->getInt('season'),
                'universe' => $request->request->getInt('universe'),
                'from' => $request->request->getString('from'),
                'until' => $request->request->getString('until'),
            ];
            try {
                $prepared = $editions->prepare(...$draft);
                $key = bin2hex(random_bytes(16));
                // Une seule prévisualisation bornée par session. Les lignes de
                // cadeaux ne sont pas ressoumises par le bouton de publication.
                $request->getSession()->set(self::PREVIEW, ['key' => $key, 'draft' => $prepared]);

                return $this->redirectToRoute('app_admin_game_event_advent_preview', ['key' => $key], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $error) {
                $this->addFlash('error', $error->getMessage());
            }
        } elseif ($request->query->getBoolean('edit')) {
            $preview = $request->getSession()->get(self::PREVIEW);
            if (isset($preview['draft']['universe'])) {
                $draft = $preview['draft'];
            }
        }

        $now = $clock->now();
        $scheduled = $windows->scheduled($now);
        $readouts = [];
        foreach ($scheduled as $window) {
            $readouts[$window->edition->id] ??= $captureStatus->forEdition($window->edition, $now);
        }
        foreach ($windows->recentlyClosedEditions($now) as $edition) {
            $readouts[$edition->id] ??= $captureStatus->forEdition($edition, $now);
        }

        return $this->render('admin/advent_form.html.twig', [
            'draft' => $draft,
            'published' => $scheduled,
            'readouts' => $readouts,
            'universes' => $game->all(Universe::class, [], ['name' => 'ASC']),
        ], new Response(status: $request->isMethod('POST') ? 422 : 200));
    }

    #[Route('/admin/events/avent/apercu/{key}', name: 'app_admin_game_event_advent_preview', requirements: ['key' => '[a-f0-9]{32}'], methods: ['GET', 'POST'])]
    public function preview(Request $request, string $key, AdventEditions $editions, GameCatalog $catalog): Response
    {
        $saved = $request->getSession()->get(self::PREVIEW);
        if (!isset($saved['draft']['universe']) || !hash_equals($saved['key'], $key)) {
            throw $this->createNotFoundException('Cet aperçu a expiré.');
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(self::PREVIEW.'_'.$key, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }
            $actor = $this->getUser();
            if (!$actor instanceof Player) {
                throw $this->createAccessDeniedException();
            }
            try {
                $editions->publish($actor, $saved['draft']);
                $request->getSession()->remove(self::PREVIEW);
                $this->addFlash('success', 'Calendrier programmé pour '.$saved['draft']['universeName'].'.');

                return $this->redirectToRoute('app_admin_game_event_advent', status: Response::HTTP_SEE_OTHER);
            } catch (\DomainException $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        $response = $this->render('admin/advent_preview.html.twig', [
            'draft' => $saved['draft'], 'key' => $key, 'resources' => AdventGifts::RESOURCES, 'ships' => $catalog->all('ships'),
            'brackets' => ['beginner' => 'Débutant ×1', 'intermediate' => 'Intermédiaire ×6', 'veteran' => 'Vétéran ×24'],
        ], new Response(status: $request->isMethod('POST') ? 422 : 200));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
