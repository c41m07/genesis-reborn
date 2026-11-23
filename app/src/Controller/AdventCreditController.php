<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Planet, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Advent\{AdventGifts, AdventQueueCredits};
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\QueueService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class AdventCreditController extends AbstractController
{
    #[Route('/evenements/avent/{id}/avoirs/{claimId}', name: 'app_advent_credit', requirements: ['id' => '\d+', 'claimId' => '\d+'], methods: ['GET', 'POST'])]
    public function view(Request $request, int $id, int $claimId, UniverseContext $context, AdventQueueCredits $credits, QueueService $queue, GameRepository $game, GameCatalog $catalog): Response
    {
        $membership = $context->membership();
        $offer = $credits->offer($membership, $id, $claimId);
        if ($offer === null) {
            throw $this->createNotFoundException('Ce bon n’est pas disponible dans l’univers de cette édition.');
        }
        $research = $offer['kind'] === 'research_discount';
        $group = $research ? 'technologies' : 'ships';
        if ($request->isMethod('POST') && !$this->isCsrfTokenValid('advent_credit_'.$membership->id.'_'.$claimId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Rechargez le devis de l’Avent avant de confirmer.');
        }
        $input = $request->isMethod('POST') ? $request->request : $request->query;
        $planetId = $input->getInt('colony');
        $selection = $research ? $input->getString('item') : $offer['slug'].':'.$offer['tier'];
        $quote = null;
        $error = null;
        if ($planetId > 0) {
            $planet = $game->find(Planet::class, $planetId);
            if (!$planet instanceof Planet || !$this->owns($membership, $planet)) {
                throw $this->createNotFoundException('Cette colonie ne vous appartient pas.');
            }
            try {
                if (!preg_match('/\A([a-z0-9_]+):(basic|intermediate|superior)\z/', $selection, $parts)) {
                    throw new \DomainException('Choisissez une technologie et son rang.');
                }
                [, $slug, $tier] = $parts;
                if ($request->isMethod('POST')) {
                    $key = $input->getString('quote');
                    if (!preg_match('/\A[a-f0-9]{64}\z/', $key)) {
                        throw new \DomainException('Vérifiez d’abord les conditions du devis.');
                    }
                    $queue->add($membership, $planet, $group, $slug, 1, $input->getString('request_key'), $tier,
                        adventCreditId: $claimId, adventQuote: $key);
                    $this->addFlash('success', 'Commande enregistrée dans la file au prix du devis Avent.');

                    return $this->redirectToRoute('app_advent_edition', ['id' => $id], Response::HTTP_SEE_OTHER);
                }
                $quote = $queue->adventPreview($membership, $planet, $group, $slug, $tier, $offer);
                $quote['proof'] = AdventQueueCredits::quoteKey($claimId, $membership->id, $planetId, $group, $slug, $tier, $quote['level'], $quote['duration'], $quote['paid']);
                $quote['label'] = $catalog->getTierForUniverse($membership->universe, $group, $slug, $tier)['label'];
            } catch (\DomainException $exception) {
                $error = $exception->getMessage();
            }
        } elseif ($request->isMethod('POST')) {
            $error = 'Choisissez une colonie qui recevra cette commande.';
        }
        $response = $this->render('player/advent_credit.html.twig', [
            'editionId' => $id, 'claimId' => $claimId, 'membership' => $membership, 'offer' => $offer, 'research' => $research,
            'planets' => $game->planets($membership->universe, $membership), 'selectedPlanet' => $planetId, 'selection' => $selection,
            'technologies' => $research ? $catalog->forUniverse($membership->universe)['catalog']['technologies'] : [],
            'ranks' => $catalog->tiers(), 'ships' => $catalog->all('ships'), 'resources' => AdventGifts::RESOURCES,
            'quote' => $quote, 'error' => $error, 'requestKey' => $quote === null ? null : bin2hex(random_bytes(16)),
        ], new Response(status: $error === null ? 200 : 422));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    private function owns(PlayerUniverse $membership, Planet $planet): bool
    {
        return $planet->owner?->id === $membership->id
            && $planet->solarSystem->galaxy->universe->id === $membership->universe->id;
    }
}
