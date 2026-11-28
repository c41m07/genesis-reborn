<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Universe;
use App\Repository\{GameRepository, ResourceMarketRepository};
use App\Service\Market\{MarketPresentation, MarketPricing, WeeklyMerchantDelivery, StationPolicy};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminMarketController extends AbstractController
{
    #[Route('/admin/univers/{id}/bourse', name: 'app_admin_market', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function settings(
        int $id, Request $request, GameRepository $repo, ResourceMarketRepository $markets,
        WeeklyMerchantDelivery $merchants, MarketPresentation $presentation,
    ): Response {
        $universe = $repo->find(Universe::class, $id) ?? throw $this->createNotFoundException('Univers introuvable.');
        $policy = StationPolicy::read($universe);
        $example = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('market_admin_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }
            try {
                $targets = [];
                foreach (array_keys(MarketPricing::WEIGHTS) as $resource) {
                    $targets[$resource] = $request->request->getInt($resource, -1);
                }
                if ($request->request->has('policy')) $policy = StationPolicy::validate($request->request->all('policy'));
                if ($request->request->getString('action') === 'preview') {
                    $example = $policy['fee'];
                } else {
                    $merchants->configure($this->getUser(), $universe, $request->request->getBoolean('enabled'), $targets, $policy);
                    $this->addFlash('success', 'Réglages de la Bourse enregistrés.');
                }
            } catch (\DomainException $error) {
                $this->addFlash('error', $error->getMessage());
            }
            if ($example === null) return $this->redirectToRoute('app_admin_market', ['id' => $id], Response::HTTP_SEE_OTHER);
        }
        $market = $markets->find($universe);
        return $this->render('market/admin.html.twig', [
            'universe' => $universe, 'market' => $market, 'rows' => $market ? $presentation->rows($market) : [],
            'labels' => MarketPresentation::LABELS, 'merchantLog' => $markets->merchantLog($universe),
            'maxTarget' => MarketPricing::MAX_TARGET,'policy'=>$policy,'policyLabels'=>StationPolicy::LABELS,'bounds'=>StationPolicy::MAX,'example'=>$example,'draftTargets'=>$targets ?? [],'enabled'=>$request->isMethod('POST') ? $request->request->getBoolean('enabled') : $market?->enabled,
        ]);
    }
}
