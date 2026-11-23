<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Planet, Player, Universe};
use App\Repository\GameRepository;
use App\Service\Advent\{AdventCalendar, AdventEditions, AdventGifts, AdventParticipation, AdventRewards};
use App\Service\Universe\UniverseContext;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class AdventController extends AbstractController
{
    #[Route('/evenements/avent', name: 'app_advent', methods: ['GET'])]
    #[Route('/evenements/avent/{id}', name: 'app_advent_edition', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function calendar(UniverseContext $context, AdventEditions $editions, AdventRewards $rewards, AdventCalendar $calendar, GameRepository $game, ClockInterface $clock, AdventParticipation $participation, GameCatalog $catalog, ?int $id = null): Response
    {
        $membership = $context->membership();
        $player = $this->player();
        $available = $editions->listedFor($player);
        $now = $clock->now();
        $edition = null;
        if ($id !== null) {
            $edition = $editions->forPlayer($player, $id);
        } else {
            // Retrouver d’abord l’édition ouverte ; les réservations des
            // saisons closes gardent chacune leur propre adresse.
            foreach ($available as $candidate) {
                if ($candidate->startsAt <= $now && $candidate->closesAt > $now) {
                    $edition = $candidate;
                    break;
                }
            }
            $edition ??= $available[0] ?? null;
        }
        if ($edition === null) {
            throw $this->createNotFoundException('Aucune édition de l’Avent annoncée.');
        }
        $colonies = $game->planets($membership->universe, $membership);
        $coloniesById = [];
        foreach ($colonies as $colony) {
            $coloniesById[$colony->id] = $colony;
        }
        $claims = $rewards->claims($player, $edition);
        $consolidated = $edition->plan === 'consolidated';
        $binding = $consolidated ? $participation->binding($player, $edition) : null;
        $progression = $consolidated ? $participation->progression($player, $edition, $binding?->universeId ?? $membership->universe->id) : null;
        $destinationsAllowed = !$consolidated || $binding?->universeId === $membership->universe->id;
        $bindingUniverse = $binding === null ? null : $game->find(Universe::class, $binding->universeId);
        $doors = [];
        foreach ($edition->doors as $door => $definition) {
            $opensAt = new \DateTimeImmutable($definition['opens_at']);
            // Une tranche non observée et les autres tranches ne deviennent
            // pas des données HTML du joueur via la projection des portes.
            $options = $consolidated ? ($definition['options'][$binding?->bracket ?? ''] ?? []) : $definition['options'];
            $doors[$door] = [
                'options' => $options,
                'opensAt' => $opensAt,
                'open' => $calendar->canOpen($now, $opensAt, $edition->closesAt),
                'claim' => $claims[$door] ?? null,
            ];
        }

        $response = $this->render('player/advent.html.twig', [
            'edition' => $edition,
            'otherEditions' => $available,
            'closed' => $now >= $edition->closesAt,
            'membership' => $membership,
            'doors' => $doors,
            'colonies' => $coloniesById,
            'resources' => AdventGifts::RESOURCES,
            'ships' => $consolidated ? $catalog->all('ships') : [],
            'consolidated' => $consolidated,
            'binding' => $binding,
            'bindingUniverse' => $bindingUniverse,
            'progression' => $progression,
            'destinationsAllowed' => $destinationsAllowed,
            'openPeriod' => $edition->startsAt <= $now && $now < $edition->closesAt,
            'bracketLabels' => ['beginner' => 'Débutant ×1', 'intermediate' => 'Intermédiaire ×6', 'veteran' => 'Vétéran ×24'],
            'claimed' => count($claims),
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    #[Route('/evenements/avent/{id}/univers', name: 'app_advent_bind', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function bind(Request $request, int $id, UniverseContext $context, AdventEditions $editions, AdventParticipation $participation): Response
    {
        $player = $this->player();
        $membership = $context->membership();
        $edition = $editions->forPlayer($player, $id);
        if ($edition === null) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('advent_bind_'.$id.'_'.$membership->id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Rechargez la page et vérifiez votre univers.');
        }
        try {
            $participation->bind($player, $membership, $edition);
            $this->addFlash('success', 'Univers choisi pour cette édition : '.$membership->universe->name.'.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_advent_edition', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/evenements/avent/{id}/portes/{door}', name: 'app_advent_open', requirements: ['id' => '\d+', 'door' => '\d+'], methods: ['POST'])]
    public function open(Request $request, int $id, int $door, UniverseContext $context, AdventEditions $editions, AdventRewards $rewards, GameRepository $game): Response
    {
        $membership = $context->membership();
        $player = $this->player();
        $edition = $editions->forPlayer($player, $id);
        if ($edition === null || !isset($edition->doors[$door])) {
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
            $result = $rewards->open($player, $membership, $planet, $edition, $door, $request->request->getInt('choice', -1));
        } catch (\DomainException) {
            $result = ['state' => 'stale'];
        }
        $message = match ($result['state']) {
            'delivered' => 'Porte '.$door.' : cadeau livré à '.$planet->name.'.',
            'partial' => 'Une partie du lot a été versée. Le reste est réservé jusqu’à ce que votre plafond initial soit levé.',
            'early_cap' => 'Le plafond de vos 48 premières heures est atteint. Le lot reste réservé.',
            'ships_reserved' => 'Lot réservé. Toutes les coques doivent pouvoir être construites sur la colonie choisie et le hangar doit pouvoir les recevoir.',
            'credit_ready' => 'Votre remise est acquise. Ouvrez son devis de construction pour l’utiliser.',
            'wrong_universe' => 'Consultez le calendrier dans l’univers choisi pour cette édition.',
            'already' => 'Votre compte a déjà reçu le cadeau de cette porte.',
            'full' => 'Cadeau réservé : manque de place ('.$this->missing($result['missing']).'). Aucun versement partiel.',
            'reserved' => 'Cette porte a déjà un lot réservé. Le choix du cadeau ne peut plus être modifié.',
            'busy' => 'Une autre action est en cours sur votre compte. Réessayez après avoir vérifié l’état de la porte.',
            'stale' => 'Cette destination ou cet univers n’est plus accessible. Aucun cadeau n’a été versé.',
            default => 'Cette porte ou ce choix de cadeau n’est pas disponible.',
        };
        $this->addFlash(in_array($result['state'], ['delivered', 'already', 'partial', 'credit_ready'], true) ? 'success' : 'error', $message);

        return $this->redirectToRoute('app_advent_edition', ['id' => $id, '_fragment' => 'advent-door-'.$door], Response::HTTP_SEE_OTHER);
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
