<?php
namespace App\Controller;

use App\Entity\{AdminAuditLog, Planet, Player, PlayerActivity, PlayerUniverse};
use App\Service\Player\PlayerActivityService;
use App\Service\Admin\AdminInterventionService;
use App\Service\Colony\ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminPlayerController extends AbstractController
{
    #[Route('/admin/joueurs/{id}', name: 'app_admin_player', requirements: ['id'=>'\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em, PlayerActivityService $activity, ClockInterface $clock, ResourceService $resources): Response
    {
        $player = $em->find(Player::class, $id);
        if (!$player) throw $this->createNotFoundException('Joueur introuvable.');
        $memberships = [];$stocks = [];
        foreach ($em->getRepository(PlayerUniverse::class)->findBy(['player'=>$player], ['id'=>'ASC']) as $membership) {
            $planets = $em->getRepository(Planet::class)->findBy(['owner'=>$membership], ['id'=>'ASC']);
            foreach ($planets as $planet) $stocks[$planet->id] = ['resources'=>$resources->snapshot($planet), 'request_key'=>bin2hex(random_bytes(16))];
            $memberships[] = ['membership'=>$membership, 'activity'=>$activity->snapshot($em->find(PlayerActivity::class, $membership->id)),
                'planets'=>$planets];
        }
        $entry = new AdminAuditLog();
        $entry->actor = $this->getUser();$entry->action = 'admin_player_view';$entry->targetType = 'player';$entry->targetId = $id;$entry->createdAt = $clock->now();
        $em->persist($entry);$em->flush();
        return $this->render('admin/player.html.twig', ['player'=>$player, 'memberships'=>$memberships, 'stocks'=>$stocks]);
    }

    #[Route('/admin/joueurs/{id}/colonies/{planetId}/ressources', name: 'app_admin_player_resources', requirements: ['id'=>'\d+', 'planetId'=>'\d+'], methods: ['POST'])]
    public function resources(int $id, int $planetId, Request $request, EntityManagerInterface $em, AdminInterventionService $interventions): Response
    {
        if (!$this->isCsrfTokenValid('admin_resources_'.$planetId, $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $planet = $em->find(Planet::class, $planetId);
        if (!$planet || !$em->find(Player::class, $id)) throw $this->createNotFoundException();
        try {
            if (!$request->request->getBoolean('confirmed')) throw new \DomainException('Confirmez explicitement la correction avant de l’appliquer.');
            $changed = $interventions->resources($this->getUser(), $id, $planet, (string) $request->request->get('operation'), $request->request->all('amounts'), (string) $request->request->get('reason'), (string) $request->request->get('request_key'));
            $this->addFlash('success', $changed ? 'Correction des ressources enregistrée.' : 'Cette correction a déjà été traitée.');
        } catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('app_admin_player', ['id'=>$id, '_fragment'=>'colony-'.$planetId]);
    }
}
