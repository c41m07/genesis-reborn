<?php
namespace App\Controller;

use App\Entity\{AdminAuditLog, Player, UserFeedback};
use App\Manager\GameManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{File\Exception\FileException, File\File, HeaderUtils, Request, Response, BinaryFileResponse};
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class FeedbackController extends AbstractController
{
    #[Route('/feedback', name: 'app_feedback', methods: ['GET','POST'])]
    public function create(Request $request, EntityManagerInterface $em, GameManager $manager, ClockInterface $clock, KernelInterface $kernel): Response
    {
        $player = $this->getUser();
        if (!$player instanceof Player) throw $this->createAccessDeniedException();
        $errors = [];
        $contextUrl = trim((string) ($request->isMethod('POST')
            ? $request->request->get('context_url', '')
            : $request->query->get('context_url', $request->headers->get('referer', ''))));
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_feedback', $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $type=(string)$request->request->get('type'); $priority=(string)$request->request->get('priority');
            $title=trim((string)$request->request->get('title')); $description=trim((string)$request->request->get('description'));
            if (!in_array($type,['bug','suggestion','improvement','other'],true)) $errors[]='Choisissez un type de feedback valide.';
            if (!in_array($priority,['low','medium','high'],true)) $errors[]='Choisissez une priorité valide.';
            if ($title==='' || mb_strlen($title)>120) $errors[]='Le titre est obligatoire (120 caractères maximum).';
            if ($description==='' || mb_strlen($description)>5000) $errors[]='La description est obligatoire (5 000 caractères maximum).';
            if ($contextUrl !== '' && (mb_strlen($contextUrl)>500 || !filter_var($contextUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($contextUrl, PHP_URL_SCHEME)), ['http','https'], true))) $errors[]='Le lien de la page concernée doit être une URL HTTP ou HTTPS valide (500 caractères maximum).';
            $screenshot=$request->files->get('screenshot');
            $extension=null;
            if ($screenshot !== null) {
                if (!$screenshot instanceof \Symfony\Component\HttpFoundation\File\UploadedFile || !$screenshot->isValid()) $errors[]='La capture n’a pas pu être téléversée.';
                elseif (($screenshot->getSize() ?? 0) > 5 * 1024 * 1024) $errors[]='La capture doit faire 5 Mo maximum.';
                else {
                    $extension=match ($screenshot->getMimeType()) {'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>null};
                    if ($extension === null) $errors[]='Format refusé. Utilisez une image JPEG, PNG ou WebP.';
                }
            }
            $since=$clock->now()->modify('-1 hour');
            $recent=(int)$em->createQuery('SELECT COUNT(f.id) FROM App\\Entity\\UserFeedback f WHERE f.player = :player AND f.createdAt >= :since')->setParameter('player',$player)->setParameter('since',$since)->getSingleScalarResult();
            if ($recent>=3) $errors[]='Vous avez déjà envoyé plusieurs retours récemment. Réessayez dans une heure.';
            if (!$errors) {
                $feedback=new UserFeedback(); $feedback->player=$player; $feedback->universe=$player->currentPlayerUniverse?->universe;
                $feedback->type=$type; $feedback->priority=$priority; $feedback->title=$title; $feedback->description=$description;
                $feedback->contextUrl=$contextUrl !== '' ? $contextUrl : null; $feedback->createdAt=$clock->now();
                if ($screenshot && $extension) {
                    $directory=$kernel->getProjectDir().'/var/feedback-attachments';
                    if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new \RuntimeException('Impossible de créer le dossier privé des captures.');
                    $filename=bin2hex(random_bytes(24)).'.'.$extension;
                    try { $screenshot->move($directory,$filename); }
                    catch (FileException $error) { throw new \RuntimeException('Impossible d’enregistrer la capture.', previous:$error); }
                    $feedback->attachmentFilename=$filename;
                }
                $manager->save($feedback); $manager->flush();
                $this->addFlash('success','Votre feedback a bien été envoyé. Merci pour votre aide !');
                return $this->redirectToRoute('app_feedback');
            }
        }
        return $this->render('feedback/create.html.twig',['errors'=>$errors, 'contextUrl'=>$contextUrl]);
    }

    #[Route('/feedback/{id}/capture', name: 'app_feedback_attachment', methods: ['GET'])]
    public function attachment(int $id, EntityManagerInterface $em, KernelInterface $kernel): Response
    {
        $feedback=$em->find(UserFeedback::class,$id);
        if (!$feedback || !$feedback->attachmentFilename) throw $this->createNotFoundException();
        $viewer=$this->getUser();
        if (!$viewer instanceof Player || ($viewer->id !== $feedback->player->id && !$this->isGranted('ROLE_ADMIN'))) throw $this->createAccessDeniedException();
        $path=$kernel->getProjectDir().'/var/feedback-attachments/'.$feedback->attachmentFilename;
        if (!is_file($path)) throw $this->createNotFoundException();
        $response=new BinaryFileResponse($path);
        $response->headers->set('Content-Type', (new File($path))->getMimeType() ?: 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE,'capture.'.$this->attachmentExtension($feedback->attachmentFilename));
        return $response;
    }

    private function attachmentExtension(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {'jpg'=>'jpg','png'=>'png','webp'=>'webp',default=>'bin'};
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/feedbacks', name: 'app_admin_feedbacks', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $qb=$em->createQueryBuilder()->select('f','p','u')->from(UserFeedback::class,'f')->join('f.player','p')->leftJoin('f.universe','u')->orderBy('f.createdAt','DESC');
        foreach (['type','status','priority'] as $field) if ($value=$request->query->get($field)) $qb->andWhere('f.'.$field.' = :'.$field)->setParameter($field,$value);
        if ($universe=$request->query->get('universe')) $qb->andWhere('u.id = :universe')->setParameter('universe',(int)$universe);
        if ($search=trim((string)$request->query->get('q'))) $qb->andWhere('f.title LIKE :q OR p.username LIKE :q')->setParameter('q','%'.$search.'%');
        $feedbacks=$qb->getQuery()->getResult();
        return $this->render('admin/feedbacks.html.twig',['feedbacks'=>$feedbacks,'universes'=>$em->getRepository(\App\Entity\Universe::class)->findBy([],['name'=>'ASC']),'total'=>count($feedbacks),'openCount'=>count(array_filter($feedbacks,static fn(UserFeedback $f)=>$f->status==='open'))]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/feedbacks/{id}', name: 'app_admin_feedback', methods: ['GET','POST'])]
    public function detail(int $id, Request $request, EntityManagerInterface $em, GameManager $manager, ClockInterface $clock): Response
    {
        $feedback=$em->find(UserFeedback::class,$id); if (!$feedback) throw $this->createNotFoundException();
        $actor=$this->getUser(); if (!$actor instanceof Player) throw $this->createAccessDeniedException();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('feedback_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $status=(string)$request->request->get('status'); $note=trim((string)$request->request->get('internalNote'));
            if (!in_array($status,['open','in_progress','resolved','rejected','archived'],true) || mb_strlen($note)>5000) $this->addFlash('error','Statut ou note invalide.');
            else {
                $feedback->status=$status; $feedback->internalNote=$note ?: null;
                $audit=new AdminAuditLog(); $audit->actor=$actor; $audit->action='feedback_updated'; $audit->targetType='user_feedback'; $audit->targetId=$id; $audit->details=['status'=>$status]; $audit->createdAt=$clock->now();
                $manager->save($audit); $manager->flush(); $this->addFlash('success','Feedback mis à jour.');
            }
            return $this->redirectToRoute('app_admin_feedback',['id'=>$id]);
        }
        $audit=new AdminAuditLog(); $audit->actor=$actor; $audit->action='feedback_viewed'; $audit->targetType='user_feedback'; $audit->targetId=$id; $audit->details=[]; $audit->createdAt=$clock->now(); $manager->save($audit); $manager->flush();
        return $this->render('admin/feedback_detail.html.twig',['feedback'=>$feedback]);
    }
}
