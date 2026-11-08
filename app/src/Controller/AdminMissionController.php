<?php
namespace App\Controller;

use App\Entity\{Fleet, Universe};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminMissionController extends AbstractController
{
    private const MISSIONS = ['explore'=>'Exploration', 'transport'=>'Transport', 'colonize'=>'Colonisation', 'attack'=>'Attaque', 'spy'=>'Espionnage', 'defend'=>'Défense alliée', 'orbit'=>'Orbite alliée', 'conquer'=>'Conquête JcE'];
    private const STATUSES = ['traveling'=>'Aller', 'returning'=>'Retour', 'attacking'=>'Combat', 'defending'=>'Défense', 'orbiting'=>'Orbite', 'orbit_pending'=>'En attente d’accord', 'occupying'=>'Occupation JcE', 'completed'=>'Terminée'];

    #[Route('/admin/flottes', name: 'app_admin_missions', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $universeId = $request->query->get('universe', '');
        $filters = ['universe'=>$universeId === '' ? 0 : $request->query->getInt('universe'), 'mission'=>(string) $request->query->get('mission', ''), 'status'=>(string) $request->query->get('status', '')];
        $query = $em->createQueryBuilder()->select('f', 'm', 'p', 'u', 'h', 'd', 's')->from(Fleet::class, 'f')
            ->join('f.playerUniverse', 'm')->leftJoin('m.player', 'p')->join('m.universe', 'u')
            ->join('f.homePlanet', 'h')->leftJoin('f.destination', 'd')->leftJoin('f.destinationSystem', 's')
            ->where('f.mission <> :idle')->setParameter('idle', 'idle');
        foreach (['universe'=>'u.id', 'mission'=>'f.mission', 'status'=>'f.status'] as $filter => $field) {
            if ($filters[$filter] !== '' && $filters[$filter] !== 0) $query->andWhere($field.' = :'.$filter)->setParameter($filter, $filters[$filter]);
        }
        $total = (int) (clone $query)->select('COUNT(f.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / 50));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));
        return $this->render('admin/missions.html.twig', [
            'fleets'=>$query->orderBy('f.id', 'DESC')->setFirstResult(($page - 1) * 50)->setMaxResults(50)->getQuery()->getResult(),
            'universes'=>$em->getRepository(Universe::class)->findBy([], ['name'=>'ASC']),
            'missions'=>self::MISSIONS, 'statuses'=>self::STATUSES, 'filters'=>$filters,
            'total'=>$total, 'page'=>$page, 'pages'=>$pages,
        ]);
    }
}
