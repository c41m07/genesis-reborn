<?php
namespace App\Controller;

use App\Entity\Galaxy;
use App\Repository\GameRepository;
use App\Service\Fleet\FleetVisibility;
use App\Service\Universe\UniverseContext;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class FleetSurveillanceController extends AbstractController
{
    #[Route('/flottes/surveillance',name:'app_fleet_surveillance',methods:['GET'])]
    public function surveillance(Request $request,UniverseContext $context,GameRepository $repo,FleetVisibility $visibility,ClockInterface $clock): Response
    {
        $member=$context->membership();
        $payload=['membership'=>$member->id,'serverNow'=>$clock->now()->format(DATE_ATOM),
            'html'=>$this->renderView('ui/incoming_attacks_list.html.twig',['attacks'=>$visibility->incoming($member)])];
        if($request->query->has('galaxy')) {
            $galaxy=$repo->find(Galaxy::class,$request->query->getInt('galaxy'));
            if(!$galaxy || $galaxy->universe->id!==$member->universe->id) throw $this->createNotFoundException('Galaxie inaccessible dans cet univers.');
            $flights=$visibility->forMap($member,$galaxy);
            $payload+=['galaxy'=>$galaxy->id,'flights'=>$flights,
                'fleetLog'=>$this->renderView('universe/_fleet_log.html.twig',['galaxy_v2_flights'=>$flights])];
        }
        return $this->json($payload,headers:['Cache-Control'=>'private, no-store']);
    }
}
