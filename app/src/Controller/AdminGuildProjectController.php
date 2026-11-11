<?php
namespace App\Controller;

use App\Entity\{GuildProjectDefinition,Player,Universe};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\{GuildProjectDefinitionService,GuildProjectRules};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminGuildProjectController extends AbstractController
{
    #[Route('/admin/projets-guilde',name:'app_admin_guild_projects',methods:['GET','POST'])]
    public function index(Request $request,GameRepository $repo,GameCatalog $catalog,GuildProjectDefinitionService $definitions,GuildProjectRules $rules): Response
    {
        $id=$request->query->getInt('edit');$definition=$id>0?$repo->find(GuildProjectDefinition::class,$id):null;
        if($id>0 && !$definition) throw $this->createNotFoundException();
        $draft=$definition ? ['name'=>$definition->name,'description'=>$definition->description,'universe_id'=>$definition->universe->id,'enabled'=>$definition->enabled,'version'=>$definition->version,'stages'=>$definition->stages,'minimums'=>$definition->minimums] : ['name'=>'','description'=>'','universe_id'=>0,'enabled'=>false,'version'=>0,'stages'=>[['name'=>'','costs'=>[]]],'minimums'=>[]];
        $focusStage=null;
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('guild_project_definition_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $draft=['name'=>$request->request->getString('name'),'description'=>$request->request->getString('description'),'universe_id'=>$request->request->getInt('universe_id'),'enabled'=>$request->request->getBoolean('enabled'),'version'=>$request->request->getInt('version'),'stages'=>array_values($request->request->all('stages')),'minimums'=>$request->request->all('minimums')];
            try {
                $intent=$request->request->getString('intent','save');
                if($intent==='add_stage') {
                    if(count($draft['stages'])>=GuildProjectRules::MAX_STAGES) throw new \DomainException('Dix paliers maximum.');
                    $draft['stages'][]=['name'=>'','costs'=>[]];$focusStage=count($draft['stages'])-1;
                } elseif($intent==='remove_stage') {
                    if(count($draft['stages'])<=1) throw new \DomainException('Conservez au moins un palier.');
                    array_pop($draft['stages']);$focusStage=count($draft['stages'])-1;
                } elseif($intent==='save') {
                    $actor=$this->getUser();if(!$actor instanceof Player) throw $this->createAccessDeniedException();
                    $definitions->save($actor,$id,$draft,$draft['version']);
                    $this->addFlash('success','Modèle enregistré. Les projets déjà lancés conservent leurs coûts et minimums.');
                    return $this->redirectToRoute('app_admin_guild_projects');
                } else throw new \DomainException('Action inconnue.');
            } catch(\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        }
        return $this->render('admin/guild_projects.html.twig',['draft'=>$draft,'editing'=>$definition,'focusStage'=>$focusStage,'definitions'=>$repo->all(GuildProjectDefinition::class,[],['id'=>'DESC']),'universes'=>$repo->all(Universe::class),'resources'=>$catalog->rules()['resources'],'projectRules'=>$rules]);
    }
}
