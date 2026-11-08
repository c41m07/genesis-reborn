<?php
namespace App\Controller;

use App\Entity\{AdminAuditLog, AdminCase, AdminCaseNote, Fleet, FleetMissionTrace, PlayerUniverse, Universe};
use App\Service\Admin\{AdminCaseService, AdminSurveillanceService};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminCaseController extends AbstractController
{
    #[Route('/admin/surveillance', name:'app_admin_surveillance', methods:['GET'])]
    public function surveillance(Request $request, EntityManagerInterface $em, AdminSurveillanceService $service): Response
    {
        $universes=$em->getRepository(Universe::class)->findBy([],['id'=>'ASC']);
        $id=$request->query->get('universe','');$universe=$id!=='' ? $em->find(Universe::class,$request->query->getInt('universe')) : ($universes[0] ?? null);
        if ($id!=='' && !$universe) throw $this->createNotFoundException('Univers introuvable.');
        $alerts=$universe ? $service->alerts($universe) : [];$cases=[];
        if ($alerts) foreach ($em->getRepository(AdminCase::class)->findBy(['alertKey'=>array_column($alerts,'key')]) as $case) $cases[$case->alertKey]=$case;
        return $this->render('admin/surveillance.html.twig',['universes'=>$universes,'universe'=>$universe,'alerts'=>$alerts,'cases'=>$cases,
            'thresholds'=>$universe ? $service->settings($universe) : [],'securityIncidents'=>$service->securityIncidents(),
            'firstTrace'=>$universe ? $em->getRepository(FleetMissionTrace::class)->findOneBy(['universe'=>$universe],['departedAt'=>'ASC']) : null]);
    }

    #[Route('/admin/surveillance/{id}/seuils', name:'app_admin_surveillance_thresholds', requirements:['id'=>'\d+'], methods:['POST'])]
    public function thresholds(int $id, Request $request, EntityManagerInterface $em, AdminCaseService $cases): Response
    {
        if (!$this->isCsrfTokenValid('admin_surveillance_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $universe=$em->find(Universe::class,$id);if (!$universe) throw $this->createNotFoundException();
        try { $cases->saveThresholds($this->getUser(),$universe,$request->request->all());$this->addFlash('success','Seuils de surveillance enregistrés.'); }
        catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_admin_surveillance',['universe'=>$id]);
    }

    #[Route('/admin/dossiers', name:'app_admin_cases', methods:['GET'])]
    public function index(Request $request, EntityManagerInterface $em, AdminCaseService $cases): Response
    {
        $filters=['status'=>(string)$request->query->get('status','active'),'universe'=>$this->optionalId($request,'universe'),'membership'=>$this->optionalId($request,'membership'),
            'fleet'=>$this->optionalId($request,'fleet'),'q'=>mb_substr(trim((string)$request->query->get('q','')),0,120)];
        return $this->render('admin/cases.html.twig',$cases->search($filters,$request->query->getInt('page',1))+['universes'=>$em->getRepository(Universe::class)->findBy([],['name'=>'ASC'])]);
    }

    #[Route('/admin/dossiers/nouveau', name:'app_admin_case_new', methods:['GET','POST'])]
    public function create(Request $request, EntityManagerInterface $em, AdminCaseService $cases, AdminSurveillanceService $surveillance): Response
    {
        $input=$request->isMethod('POST') ? $request->request : $request->query;
        $draft=['title'=>(string)$input->get('title',''),'description'=>(string)$input->get('description',''),'universe_id'=>$input->get('universe_id') ? $input->getInt('universe_id') : 0,
            'membership_id'=>$input->get('membership_id') ? $input->getInt('membership_id') : 0,'fleet_id'=>$input->get('fleet_id') ? $input->getInt('fleet_id') : 0,
            'alert_key'=>(string)$input->get('alert_key',''),'request_key'=>(string)$input->get('request_key',bin2hex(random_bytes(16)))];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_case_create',$input->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            try {
                $case=$cases->create($this->getUser(),$draft['title'],$draft['description'],$draft['universe_id'],$draft['membership_id'],$draft['fleet_id'],$draft['alert_key'],$draft['request_key']);
                $this->addFlash('success','Dossier ouvert.');return $this->redirectToRoute('app_admin_case',['id'=>$case->id]);
            } catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        }
        $fleet=$draft['fleet_id']>0 ? $em->find(Fleet::class,$draft['fleet_id']) : null;
        $membership=$draft['membership_id']>0 ? $em->find(PlayerUniverse::class,$draft['membership_id']) : $fleet?->playerUniverse;
        if ($membership && !$draft['universe_id']) $draft['universe_id']=$membership->universe->id;
        if ($membership && !$draft['membership_id']) $draft['membership_id']=$membership->id;
        if (!$request->isMethod('POST') && $draft['alert_key']!=='' && $draft['universe_id']>0) {
            $universe=$em->find(Universe::class,$draft['universe_id']);
            if (!$universe) throw $this->createNotFoundException();
            foreach ($surveillance->alerts($universe) as $alert) if ($alert['key']===$draft['alert_key']) $draft['title']=$alert['title'];
        }
        return $this->render('admin/case_new.html.twig',['draft'=>$draft,'membership'=>$membership,'fleet'=>$fleet,'universes'=>$em->getRepository(Universe::class)->findBy([],['name'=>'ASC'])],new Response(status:$request->isMethod('POST')?422:200));
    }

    #[Route('/admin/dossiers/{id}', name:'app_admin_case', requirements:['id'=>'\d+'], methods:['GET'])]
    public function show(int $id, EntityManagerInterface $em, ClockInterface $clock): Response
    {
        $case=$em->find(AdminCase::class,$id);if (!$case) throw $this->createNotFoundException();
        $entry=new AdminAuditLog();$entry->actor=$this->getUser();$entry->action='admin_case_view';$entry->targetType='admin_case';$entry->targetId=$id;$entry->createdAt=$clock->now();$em->persist($entry);$em->flush();
        return $this->render('admin/case.html.twig',['caseFile'=>$case,'notes'=>$em->getRepository(AdminCaseNote::class)->findBy(['caseFile'=>$case],['id'=>'ASC']),
            'statuses'=>AdminCaseService::STATUSES,'request_key'=>bin2hex(random_bytes(16))]);
    }

    #[Route('/admin/dossiers/{id}/suivi', name:'app_admin_case_update', requirements:['id'=>'\d+'], methods:['POST'])]
    public function update(int $id, Request $request, EntityManagerInterface $em, AdminCaseService $cases): Response
    {
        if (!$this->isCsrfTokenValid('admin_case_update_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $case=$em->find(AdminCase::class,$id);if (!$case) throw $this->createNotFoundException();
        try {
            $changed=$cases->update($this->getUser(),$case,(string)$request->request->get('status'),(string)$request->request->get('body'),(string)$request->request->get('request_key'),(string)$request->request->get('expected_status'));
            $this->addFlash('success',$changed ? 'Suivi du dossier enregistré.' : 'Ce suivi a déjà été enregistré.');
        } catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_admin_case',['id'=>$id]);
    }
    private function optionalId(Request $request,string $key): int { return $request->query->get($key,'')==='' ? 0 : $request->query->getInt($key); }
}
