<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Planet;
use App\Repository\{GameRepository, ResourceMarketRepository};
use App\Service\Catalog\GameCatalog;
use App\Service\Market\{MarketConvoys, MarketPresentation, MarketPricing, MarketTerms, PlayerMarket};
use App\Service\Universe\UniverseContext;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class MarketController extends AbstractController
{
    #[Route('/bourse', name: 'app_market', methods: ['GET', 'POST'])]
    public function page(
        Request $request, UniverseContext $context, GameRepository $repo, ResourceMarketRepository $markets,
        PlayerMarket $players, MarketTerms $terms, MarketConvoys $convoys, MarketPresentation $presentation, ClockInterface $clock, GameCatalog $catalog,
    ): Response {
        $member = $context->membership();
        $universe = $member->universe;
        $market = $markets->find($universe);
        $session = $request->getSession();
        $sessionKey = 'market_quote_'.$member->id;
        $draftKey = 'market_draft_'.$member->id;
        $draft = $session->get($draftKey, []);
        $quote = $session->get($sessionKey);
        if ($quote && (!isset($quote['details']['composition']) || $quote['expires'] <= $clock->now()->getTimestamp())) {
            $session->remove($sessionKey);
            $quote = null;
        }
        $colonies = $repo->planets($universe, $member);
        $byId = [];
        foreach ($colonies as $planet) {
            $byId[$planet->id] = $planet;
        }

        if ($request->isMethod('POST')) {
            $fields = $request->request;
            if (!$this->isCsrfTokenValid('market_'.$member->id, $fields->getString('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }

            $action = $fields->getString('action');
            try {
                if ($action === 'recall') {
                    $convoys->recall($member, $fields->getInt('trade'));
                    $this->addFlash('success', 'Convoi rappelé. Le fret initial revient à la colonie.');
                } elseif ($action === 'reroute') {
                    $target = $byId[$fields->getInt('planet')] ?? throw new \DomainException('Choisissez une colonie de votre empire.');
                    $convoys->reroute($member, $fields->getInt('trade'), $target);
                    $this->addFlash('success', 'Nouvelle destination enregistrée. Le convoi reprend son trajet.');
                } elseif ($action === 'clear') {
                    if ($quote) $session->set($draftKey, $quote['details']);
                    $session->remove($sessionKey);
                } elseif (in_array($action, ['quote', 'estimate'], true)) {
                    $planet = $byId[$fields->getInt('planet')] ?? throw new \DomainException('Choisissez une colonie de votre empire.');
                    $ships = $fields->all('ships');
                    if (array_diff_key($ships, ['transport' => true])) {
                        throw new \DomainException('Seuls les cargos peuvent rejoindre la station.');
                    }
                    $composition = $ships['transport'] ?? [];
                    if (!is_array($composition)) {
                        $composition = ['basic' => $composition];
                    }
                    if (array_filter($composition, 'is_array')) {
                        throw new \DomainException('Indiquez un nombre de cargos par rang.');
                    }
                    if (!$ships && $fields->has('rank')) {
                        $composition = [$fields->getString('rank') => $fields->getInt('cargos')];
                    }
                    $draft = [
                        'operation' => $fields->getString('operation', 'barter'),
                        'pay' => $fields->getString('pay'), 'receive' => $fields->getString('receive', $fields->getString('pay')),
                        'quantity' => $fields->getInt('quantity'), 'composition' => $composition,
                    ];
                    if ($action === 'estimate') {
                        $estimate = $convoys->preview($member, $planet, $draft, $draft['quantity'] === 0);
                        $arrival = $clock->now()->modify('+'.MarketTerms::LEG_SECONDS.' seconds');
                        return $this->json($estimate + ['arrival' => $arrival->format(\DateTimeInterface::ATOM)]);
                    }
                    $session->set($draftKey, $draft + ['planet' => $planet->id]);
                    $details = $convoys->preview($member, $planet, $draft);
                    $session->set($sessionKey, [
                        'key' => bin2hex(random_bytes(16)), 'expires' => $clock->now()->getTimestamp() + 120, 'details' => $details,
                    ]);
                } elseif ($action === 'send') {
                    $planet = $quote ? ($byId[$quote['details']['planet']] ?? null) : null;
                    if (!$quote || !$planet || !hash_equals($quote['key'], $fields->getString('quote_key'))) {
                        throw new \DomainException('Le devis a expiré. Recalculez-le avant de lancer les cargos.');
                    }
                    $convoys->depart($member, $planet, $quote['details'], $quote['key']);
                    $session->remove($draftKey);
                    $session->remove($sessionKey);
                    $this->addFlash('success', 'Convoi lancé. Arrivée à la station dans 30 minutes.');
                }
            } catch (\DomainException $error) {
                if ($action === 'estimate') {
                    return $this->json(['error' => $error->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                if (in_array($action, ['quote', 'send'], true)) {
                    $session->remove($sessionKey);
                }
                $this->addFlash('error', $error->getMessage());
            }

            return $this->redirectToRoute('app_market', [], Response::HTTP_SEE_OTHER);
        }

        $operations = ['barter' => 'Troc direct', 'sell' => 'Vendre à la station', 'buy' => 'Acheter à la station',
            'deposit' => 'Déposer à la station', 'collect' => 'Rapatrier du dépôt'];
        if ($request->query->has('operation') && isset($operations[$request->query->getString('operation')])) {
            $draft['operation'] = $request->query->getString('operation');
            $resource = $request->query->getString('resource');
            if (isset(MarketPricing::WEIGHTS[$resource])) {
                $draft['pay'] = $resource;
            }
            if ($draft['operation'] === 'barter' && isset($draft['receive']) && $draft['receive'] === ($draft['pay'] ?? 'metal')) {
                $draft['receive'] = $draft['receive'] === 'nano' ? 'metal' : 'nano';
            }
            $quote = null;
        }
        if ($quote && !isset($byId[$quote['details']['planet']])) {
            $session->remove($sessionKey);
            $quote = null;
        }
        $planet = $byId[$request->query->getInt('planet', $quote['details']['planet'] ?? $draft['planet'] ?? 0)] ?? ($colonies[0] ?? null);
        $ships = [];
        if ($market?->enabled && $planet && !$quote) {
            $stock = $terms->cargos($member, $planet);
            foreach (GameCatalog::TIERS as $tier) {
                $ships[] = ['slug' => 'transport', 'tier' => $tier, 'quantity' => $stock[$tier] ?? 0];
            }
        }
        return $this->render('market/index.html.twig', [
            'account' => $players->account($member), 'operations' => $operations, 'draft' => $draft, 'member' => $member, 'market' => $market, 'rows' => $market ? $presentation->rows($market) : [],
            'colonies' => $colonies, 'planet' => $planet, 'trades' => $markets->trades($universe, $member->id),
            'quote' => $quote,
            'cargoShips' => $ships, 'cargoCatalog' => $ships ? ['transport' => $catalog->getForUniverse($universe, 'ships', 'transport')] : [],
            'fleetLimit' => $ships ? $catalog->forUniverse($universe)['limits']['max_fleet_size'] : 0, 'fee' => MarketPricing::FEE_PERCENT,
            'labels' => MarketPresentation::LABELS, 'ranks' => array_combine(GameCatalog::TIERS, ['Basique', 'Intermédiaire', 'Supérieur']),
            'maxLot' => MarketPricing::MAX_LOT, 'utcNow' => $clock->now(),
        ]);
    }
}
