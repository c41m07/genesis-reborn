<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\{Player,PlayerUniverse};
use App\Service\Admin\EmpireEditorialService;
use App\Service\Player\ProfileCosmetics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminEmpireEditorialController extends AbstractController
{
    #[Route('/admin/joueurs/{id}/apparence',name:'app_admin_cosmetic_grant',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    public function cosmetics(Player $player, Request $request, ProfileCosmetics $cosmetics, EmpireEditorialService $service): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('grant_'.$player->id,$request->request->getString('_token'))) throw $this->createAccessDeniedException();
            try {
                $service->award($this->getUser(),$player,$request->request->all('codes'),$request->request->getString('operation'));
                $this->addFlash('success','Apparence mise à jour.');
            } catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
            return $this->redirectToRoute('app_admin_cosmetic_grant',['id'=>$player->id],303);
        }
        return $this->render('admin/empire_editorial.html.twig',['player'=>$player,'items'=>$cosmetics->definitions(),'owned'=>$cosmetics->owned($player)]);
    }

    #[Route('/admin/joueurs/{id}/empires/{memberId}/statut', name:'app_admin_empire_state',requirements:['id'=>'\d+','memberId'=>'\d+'],methods:['GET','POST'])]
    public function state(int $id,int $memberId,Request $request, EntityManagerInterface $em, EmpireEditorialService $service): Response
    {
        $member = $em->find(PlayerUniverse::class,$memberId);
        if (!$member || $member->player?->id !== $id) throw $this->createNotFoundException();
        $preview = $service->preview($member);
        if ($request->isMethod('POST')) {
            $signature = $request->request->getString('signature');
            if (!$this->isCsrfTokenValid('state_'.$id.'_'.$memberId.'_'.$signature,$request->request->getString('_token'))) throw $this->createAccessDeniedException();
            try {
                $action = $request->request->getString('action');
                if ($action === 'convert' && !$request->request->getBoolean('confirmConversion')) throw new \DomainException('Confirmez le détachement définitif du compte.');
                $service->change($this->getUser(),$id,$member,$action,$request->request->getString('reason'),$signature);
                $this->addFlash('success','Intervention enregistrée.');
                return $this->redirectToRoute('app_admin_player',['id'=>$id],303);
            } catch (\DomainException $e) {
                $this->addFlash('error',$e->getMessage());
                return $this->redirectToRoute('app_admin_empire_state',['id'=>$id,'memberId'=>$memberId],303);
            }
        }
        return $this->render('admin/empire_editorial.html.twig',['player'=>$member->player,'member'=>$member,'preview'=>$preview]);
    }
}
