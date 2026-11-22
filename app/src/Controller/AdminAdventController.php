<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Player;
use App\Service\Advent\{AdventCalendar, AdventEditions, AdventGifts};
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
    public function form(Request $request, AdventEditions $editions, AdventCalendar $calendar, ClockInterface $clock): Response
    {
        if (!$editions->enabled) {
            throw $this->createNotFoundException();
        }
        $draft = $calendar->defaults($clock->now()) + ['name' => 'Calendrier de l’Avent', 'options' => []];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(self::PREVIEW, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }
            $draft = [
                'name' => $request->request->getString('name'),
                'first' => $request->request->getString('first'),
                'last' => $request->request->getString('last'),
                'options' => $request->request->all('options'),
            ];
            try {
                $prepared = $editions->prepare($draft['name'], $draft['first'], $draft['last'], $draft['options']);
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
            if ($preview !== null) {
                $draft = $preview['draft'] + ['options' => array_map(static fn (array $door): array => $door['options'], $preview['draft']['doors'])];
            }
        }

        return $this->render('admin/advent_form.html.twig', [
            'draft' => $draft,
            'doors' => AdventCalendar::DOORS,
            'choices' => AdventGifts::OPTIONS_PER_DOOR,
            'resources' => AdventGifts::RESOURCES,
            'published' => $editions->visible(),
        ], new Response(status: $request->isMethod('POST') ? 422 : 200));
    }

    #[Route('/admin/events/avent/apercu/{key}', name: 'app_admin_game_event_advent_preview', requirements: ['key' => '[a-f0-9]{32}'], methods: ['GET', 'POST'])]
    public function preview(Request $request, string $key, AdventEditions $editions): Response
    {
        $saved = $request->getSession()->get(self::PREVIEW);
        if (!$editions->enabled || $saved === null || !hash_equals($saved['key'], $key)) {
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
                $this->addFlash('success', 'Édition de l’Avent publiée pour tous les univers accessibles.');

                return $this->redirectToRoute('app_admin_game_event_advent', status: Response::HTTP_SEE_OTHER);
            } catch (\DomainException $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        $response = $this->render('admin/advent_preview.html.twig', [
            'draft' => $saved['draft'], 'key' => $key, 'resources' => AdventGifts::RESOURCES,
        ], new Response(status: $request->isMethod('POST') ? 422 : 200));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
