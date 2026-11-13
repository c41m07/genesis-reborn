<?php
namespace App\Controller;

use App\Service\Search\{SearchCriteria,MessageSearch};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class MessageSearchController extends AbstractController
{
    #[Route('/messages/recherche',name:'app_message_search',methods:['GET'])]
    public function index(Request $request,UniverseContext $context,MessageSearch $search): Response
    {
        $owner=$context->membership();$error=null;$status=200;$channel=$request->query->getString('channel','all');$peer=$request->query->getInt('with');
        try {
            $criteria=new SearchCriteria($request->query->all(),$owner->player->preferences['timezone']??'Europe/Paris');
            $data=$search->page($owner,$criteria,$channel,$peer,$request->query->getInt('page',1));
        } catch(\DomainException $e) {
            $error=$e->getMessage();$status=422;$criteria=new SearchCriteria([]);$data=['entries'=>[],'count'=>0,'page'=>1,'pages'=>1];
        }
        return $this->render('player/message_search.html.twig',$data+['criteria'=>$criteria->values,'filters'=>$criteria->url(),'channel'=>$channel,'peerId'=>$peer,'membership'=>$owner,'error'=>$error],new Response(status:$status,headers:['Cache-Control'=>'private, no-store']));
    }
}
