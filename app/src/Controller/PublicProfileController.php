<?php
namespace App\Controller;

use App\Service\Player\{SocialProfileView,DiplomacyAccessPolicy,GuildProfileService,GuildImageStorage};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response,StreamedResponse};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

final class PublicProfileController extends AbstractController
{
    #[Route('/joueurs/{id}',name:'app_player_public_profile',requirements:['id'=>'\d+'],methods:['GET'])]
    public function player(int $id,UniverseContext $context,SocialProfileView $profiles): Response
    {
        $member=$context->membership();
        try {$profile=$profiles->player($member,$id);} catch(\DomainException) {throw $this->createNotFoundException('Commandant inaccessible.');}
        return $this->render('player/public_profile.html.twig',['membership'=>$member,'profile'=>$profile],new Response(headers:['Cache-Control'=>'private, no-store']));
    }

    #[Route('/guildes/{id}',name:'app_guild_public_profile',requirements:['id'=>'\d+'],methods:['GET'])]
    public function guild(int $id,UniverseContext $context,SocialProfileView $profiles,DiplomacyAccessPolicy $access): Response
    {
        $member=$context->membership();
        try {$guild=$profiles->accessibleGuild($member,$id);$profile=$profiles->guild($member,$id);} catch(\DomainException) {throw $this->createNotFoundException('Guilde inaccessible.');}
        return $this->render('player/public_guild.html.twig',['membership'=>$member,'profile'=>$profile,'can_edit'=>$access->canCommand($member,$guild)],new Response(headers:['Cache-Control'=>'private, no-store']));
    }

    #[Route('/guildes/{id}/personnaliser',name:'app_guild_profile_edit',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    public function edit(int $id,Request $request,UniverseContext $context,SocialProfileView $profiles,DiplomacyAccessPolicy $access,GuildProfileService $service): Response
    {
        $member=$context->membership();
        try {$guild=$profiles->accessibleGuild($member,$id);} catch(\DomainException) {throw $this->createNotFoundException('Guilde inaccessible.');}
        if(!$access->canCommand($member,$guild)) throw $this->createAccessDeniedException('Seul le commandant actuel peut personnaliser la guilde.');
        $draftKey='guild_profile_draft_'.$member->id.'_'.$id;
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('guild_profile_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            try {
                $logo=$request->files->get('logo');$banner=$request->files->get('banner');
                if(($logo!==null && !$logo instanceof UploadedFile) || ($banner!==null && !$banner instanceof UploadedFile)) throw new \DomainException('Fichier invalide. Choisissez une image JPEG, PNG ou WebP.');
                $service->save($member,$id,$request->request->getString('description'),$request->request->getInt('version'),$logo,$banner);
                $this->addFlash('success','Présentation de la guilde enregistrée.');
                return $this->redirectToRoute('app_guild_public_profile',['id'=>$id]);
            } catch(\DomainException $error) {
                $request->getSession()->set($draftKey,mb_substr($request->request->getString('description'),0,2000));
                $this->addFlash('error',$error->getMessage().' Votre texte est conservé ; sélectionnez de nouveau les fichiers souhaités.');
                return $this->redirectToRoute('app_guild_profile_edit',['id'=>$id]);
            }
        }
        $profile=$profiles->guild($member,$id);$draft=$request->getSession()->remove($draftKey);
        return $this->render('player/guild_profile_edit.html.twig',['membership'=>$member,'profile'=>$profile,'description'=>$draft??$profile['description']],new Response(headers:['Cache-Control'=>'private, no-store']));
    }

    #[Route('/guildes/{id}/image/{kind}',name:'app_guild_media',requirements:['id'=>'\d+','kind'=>'logo|banner'],methods:['GET'])]
    public function media(int $id,string $kind,UniverseContext $context,SocialProfileView $profiles,GuildImageStorage $images): Response
    {
        try {$filename=$profiles->media($context->membership(),$id,$kind);} catch(\DomainException) {throw $this->createNotFoundException('Image inaccessible.');}
        if($filename===null) throw $this->createNotFoundException('Image inaccessible.');
        try {$path=$images->path($filename);$mime=$images->mime($filename);} catch(\DomainException) {throw $this->createNotFoundException('Image inaccessible.');}
        $handle=@fopen($path,'rb');
        if($handle===false) throw $this->createNotFoundException('Image inaccessible.');
        $response=new StreamedResponse(static function()use($handle): void {try {fpassthru($handle);} finally {fclose($handle);}});
        $response->headers->set('Content-Type',$mime);
        $response->headers->set('Content-Disposition','inline; filename="'.$kind.'.'.pathinfo($filename,PATHINFO_EXTENSION).'"');
        $response->headers->set('Cache-Control','private, no-store');$response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Content-Security-Policy',"default-src 'none'; sandbox");
        return $response;
    }
}
