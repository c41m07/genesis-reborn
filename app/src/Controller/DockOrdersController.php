<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GuildDockOrder;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Guild\GuildDockSettlement;
use App\Service\Universe\UniverseContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

/** Les ordres déjà payés restent personnels, même après un départ de guilde. */
final class DockOrdersController extends AbstractController
{
    #[Route('/flottes/modernisations', name: 'app_dock_orders', methods: ['GET'])]
    public function index(UniverseContext $context, EntityManagerInterface $em, GameRepository $game, GameCatalog $catalog, ClockInterface $clock): Response
    {
        $actor = $context->membership();
        $pending = $em->getRepository(GuildDockOrder::class)->findBy(
            ['membershipId' => $actor->id, 'universe' => $actor->universe, 'completedAt' => null],
            ['dueAt' => 'ASC', 'id' => 'ASC'],
        );
        $completed = $em->createQueryBuilder()->select('dock')->from(GuildDockOrder::class, 'dock')
            ->where('dock.membershipId = :owner AND dock.universe = :universe AND dock.completedAt IS NOT NULL')
            ->setParameter('owner', $actor->id)->setParameter('universe', $actor->universe)
            ->orderBy('dock.completedAt', 'DESC')->addOrderBy('dock.id', 'DESC')
            ->setMaxResults(12)->getQuery()->getResult();

        return $this->render('fleet/dock_orders.html.twig', [
            'owner_id' => $actor->id, 'pending' => $pending, 'completed' => $completed,
            'colonies' => $pending === [] ? [] : $game->planets($actor->universe, $actor),
            'ships' => $catalog->forUniverse($actor->universe)['catalog']['ships'],
            'now' => $clock->now(),
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/flottes/modernisations/{id}/recuperer', name: 'app_dock_recover', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function recover(int $id, Request $request, UniverseContext $context, GuildDockSettlement $dock): Response
    {
        $actor = $context->membership();
        if (!$this->isCsrfTokenValid('dock_recover_'.$actor->id.'_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez les modernisations.');
        }
        try {
            if ($dock->recover($actor, $id, $request->request->getInt('colony'))) {
                $this->addFlash('success', 'Les vaisseaux modernisés sont à quai sur la colonie choisie.');
            } else {
                $this->addFlash('error', 'Ce lot ne peut pas être récupéré ici. Vérifiez son état et choisissez une colonie personnelle disposant de place.');
            }
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_dock_orders', [], Response::HTTP_SEE_OTHER);
    }
}
