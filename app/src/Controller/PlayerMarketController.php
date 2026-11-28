<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ResourceMarketRepository;
use App\Service\Market\{MarketPresentation, MarketPricing, PlayerMarket};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class PlayerMarketController extends AbstractController
{
    #[Route('/bourse/joueurs', name: 'app_player_market', methods: ['GET', 'POST'])]
    public function page(Request $request, UniverseContext $context, ResourceMarketRepository $markets,
        PlayerMarket $players, MarketPresentation $presentation): Response
    {
        $member = $context->membership();
        $market = $markets->find($member->universe);
        $resource = $request->query->getString('resource', 'metal');
        if (!isset(MarketPricing::WEIGHTS[$resource])) {
            throw $this->createNotFoundException();
        }
        $session = $request->getSession();
        $draftKey = 'player_market_listing_'.$member->id.'_'.$resource;
        if ($request->isMethod('POST')) {
            $fields = $request->request;
            $key = $fields->getString('key');
            if (!$this->isCsrfTokenValid('exchange_'.$member->id.'_'.$key, $fields->getString('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }
            try {
                $action = $fields->getString('action');
                if ($action === 'preview' && $market?->enabled) {
                    $terms = PlayerMarket::listingTerms($fields->getInt('quantity'), $fields->getString('price'));
                    $session->set($draftKey, $terms + ['price' => $fields->getString('price'), 'key' => bin2hex(random_bytes(16))]);
                } elseif ($action === 'discard') {
                    $session->remove($draftKey);
                } elseif ($action === 'list') {
                    $draft = $session->get($draftKey);
                    if (!$draft || !hash_equals($draft['key'], $key)) {
                        throw new \DomainException('Vérifiez les conditions de mise en vente.');
                    }
                    $players->order($member, $key, 'list', $resource, $draft['quantity'], $draft['price'], 0);
                    $session->remove($draftKey);
                    $this->addFlash('success', 'Offre publiée. La taxe de mise en vente a été prélevée.');
                } else {
                    $players->order($member, $key, $action, $resource, $fields->getInt('quantity'), '', $fields->getInt('offer'));
                    $this->addFlash('success', $action === 'cancel' ? 'Invendus rendus à votre dépôt.' : 'Achat livré dans votre dépôt.');
                }
            } catch (\DomainException $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('app_player_market', ['resource' => $resource], Response::HTTP_SEE_OTHER);
        }

        $page = min(100000000, max(1, $request->query->getInt('page', 1)));
        $offers = $market?->enabled ? $markets->offers($member, $resource, $page) : [];
        $visibleOffers = array_slice($offers, 0, 20);
        $offerKeys = [];
        foreach ($visibleOffers as $offer) {
            $offerKeys[$offer->id] = bin2hex(random_bytes(16));
        }

        return $this->render('market/players.html.twig', [
            'member' => $member, 'market' => $market, 'account' => $players->account($member),
            'labels' => MarketPresentation::LABELS, 'resource' => $resource, 'page' => $page,
            'offers' => $visibleOffers, 'offerKeys' => $offerKeys, 'more' => count($offers) > 20,
            'draft' => $session->get($draftKey), 'rows' => $market?->enabled ? $presentation->rows($market) : [], 'key' => bin2hex(random_bytes(16)),
            'fee' => MarketPricing::FEE_PERCENT, 'maxLot' => MarketPricing::MAX_LOT,
        ]);
    }
}
