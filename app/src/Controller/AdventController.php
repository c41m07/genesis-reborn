<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Planet, Player};
use App\Repository\GameRepository;
use App\Service\Advent\{AdventCalendar, AdventEditions, AdventGifts, AdventRewards};
use App\Service\Universe\UniverseContext;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class AdventController extends AbstractController
{
    #[Route('/evenements/avent', name: 'app_advent', methods: ['GET'])]
    public function calendar(UniverseContext $context, AdventEditions $editions, AdventRewards $rewards, AdventCalendar $calendar, GameRepository $game, ClockInterface $clock): Response
    {
        $edition = $editions->visible();
        if ($edition === null) {
            throw $this->createNotFoundException('Aucune édition de l’Avent annoncée.');
        }
        $membership = $context->membership();
        $colonies = $game->planets($membership->universe, $membership);
        $now = $clock->now();
        $claims = $rewards->claims($this->player(), $edition);
        $doors = [];
        foreach ($edition->doors as $door => $definition) {
            $opensAt = new \DateTimeImmutable($definition['opens_at']);
            $doors[$door] = $definition + [
                'opensAt' => $opensAt,
                'open' => $calendar->canOpen($now, $opensAt, $edition->closesAt),
                'claim' => $claims[$door] ?? null,
            ];
        }

        $response = $this->render('player/advent.html.twig', [
            'edition' => $edition,
            'membership' => $membership,
            'doors' => $doors,
            'colonies' => $colonies,
            'colonyIds' => array_map(static fn (Planet $planet): int => $planet->id, $colonies),
            'resources' => AdventGifts::RESOURCES,
            'claimed' => count($claims),
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    #[Route('/evenements/avent/{id}/portes/{door}', name: 'app_advent_open', requirements: ['id' => '\d+', 'door' => '\d+'], methods: ['POST'])]
    public function open(Request $request, int $id, int $door, UniverseContext $context, AdventEditions $editions, AdventRewards $rewards, GameRepository $game): Response
    {
        $membership = $context->membership();
        $edition = $editions->visible();
        if ($edition === null || $edition->id !== $id || !isset($edition->doors[$door])) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('advent_'.$id.'_'.$membership->id.'_'.$door, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Rechargez la page pour vérifier votre univers et votre destination.');
        }
        $planet = $game->find(Planet::class, $request->request->getInt('colony'));
        if (!$planet instanceof Planet || $planet->owner?->id !== $membership->id
            || $planet->solarSystem->galaxy->universe->id !== $membership->universe->id) {
            throw $this->createNotFoundException('Cette colonie ne peut pas recevoir votre cadeau.');
        }

        try {
            $result = $rewards->open($this->player(), $membership, $planet, $edition, $door, $request->request->getInt('choice', -1));
        } catch (\DomainException) {
            $result = ['state' => 'stale'];
        }
        $message = match ($result['state']) {
            'delivered' => 'Porte '.$door.' : le cadeau a été versé à '.$planet->name.'.',
            'already' => 'Votre compte a déjà reçu le cadeau de cette porte.',
            'full' => 'Cadeau réservé : manque de place ('.$this->missing($result['missing']).'). Aucun versement partiel.',
            'reserved' => 'Cette porte a déjà un choix et une colonie destinataire réservés.',
            'busy' => 'Une autre action est en cours sur votre compte. Réessayez après avoir vérifié l’état de la porte.',
            'stale' => 'Cette destination ou cet univers n’est plus accessible. Aucun cadeau n’a été versé.',
            default => 'Cette porte ou ce choix de cadeau n’est pas disponible.',
        };
        $this->addFlash(in_array($result['state'], ['delivered', 'already'], true) ? 'success' : 'error', $message);

        return $this->redirectToRoute('app_advent', ['_fragment' => 'advent-door-'.$door], Response::HTTP_SEE_OTHER);
    }

    /** @param array<string, int> $missing */
    private function missing(array $missing): string
    {
        $parts = [];
        foreach ($missing as $resource => $quantity) {
            $parts[] = number_format($quantity, 0, ',', ' ').' '.AdventGifts::RESOURCES[$resource];
        }

        return implode(', ', $parts);
    }

    private function player(): Player
    {
        $player = $this->getUser();
        if (!$player instanceof Player) {
            throw $this->createAccessDeniedException();
        }

        return $player;
    }
}
