<?php
namespace App\Controller;

use App\Entity\{AdminAuditLog, Planet, Player, PlayerActivity, PlayerUniverse};
use App\Service\Player\PlayerActivityService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminPlayerController extends AbstractController
{
    #[Route('/admin/joueurs/{id}', name: 'app_admin_player', requirements: ['id'=>'\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em, PlayerActivityService $activity, ClockInterface $clock): Response
    {
        $player = $em->find(Player::class, $id);
        if (!$player) throw $this->createNotFoundException('Joueur introuvable.');
        $memberships = [];
        foreach ($em->getRepository(PlayerUniverse::class)->findBy(['player'=>$player], ['id'=>'ASC']) as $membership) {
            $memberships[] = ['membership'=>$membership, 'activity'=>$activity->snapshot($em->find(PlayerActivity::class, $membership->id)),
                'planets'=>$em->getRepository(Planet::class)->findBy(['owner'=>$membership], ['id'=>'ASC'])];
        }
        $entry = new AdminAuditLog();
        $entry->actor = $this->getUser();$entry->action = 'admin_player_view';$entry->targetType = 'player';$entry->targetId = $id;$entry->createdAt = $clock->now();
        $em->persist($entry);$em->flush();
        return $this->render('admin/player.html.twig', ['player'=>$player, 'memberships'=>$memberships]);
    }
}
