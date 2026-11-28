<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\{EditorialRevision, Universe};
use App\Repository\EditorialRepository;
use App\Service\Admin\EditorialLibrary;
use App\Service\Player\FirstStepsView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminEditorialController extends AbstractController
{
    #[Route('/admin/contenus', name:'app_admin_editorial', methods:['GET','POST'])]
    public function index(Request $request, EntityManagerInterface $em, EditorialRepository $repo, EditorialLibrary $library): Response
    {
        $page = max(1,min(100000,$request->query->getInt('page',1)));
        $id = $request->query->getInt('version');
        $source = $id ? $em->find(EditorialRevision::class,$id) : null;
        if ($id && !$source) throw $this->createNotFoundException();
        $kind = $source?->kind ?? $request->query->getString('type','title');
        if (!isset(EditorialLibrary::KINDS[$kind])) throw $this->createNotFoundException();
        $fields = $source?->content ?? [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('editorial_'.$kind.'_'.$id,$request->request->getString('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $fields = $request->request->all();
            try {
                if ($request->request->getString('action') === 'publish' && $source) {
                    $library->publish($this->getUser(),$source,$request->request->getString('when'));
                } else {
                    $universeId = $request->request->getInt('universe');
                    $universe = $universeId ? $em->find(Universe::class,$universeId) : null;
                    $source = $library->draft($this->getUser(),$kind,$universe,$fields,$request->files->get('image'),$source);
                }
                return $this->redirectToRoute('app_admin_editorial',['version'=>$source->id],303);
            } catch (\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        }
        $history = $repo->history($page);
        return $this->render('admin/editorial.html.twig',[
            'kind'=>$kind,'kinds'=>EditorialLibrary::KINDS,'source'=>$source,'fields'=>$fields,
            'pages'=>EditorialLibrary::PAGES,'steps'=>FirstStepsView::HINTS,'art'=>EditorialLibrary::ART,'colors'=>EditorialLibrary::COLORS,
            'universes'=>$em->getRepository(Universe::class)->findBy([],['name'=>'ASC']),
            'history'=>array_slice($history,0,25),'page'=>$page,'more'=>count($history)>25,
        ]);
    }
}
