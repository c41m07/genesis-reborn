<?php
namespace App\Controller;
use App\Service\Universe\UniverseContext;
use App\Service\Notification\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mercure\Authorization;
final class NotificationController extends AbstractController
{
    #[Route('/notifications/abonnement',name:'app_events',methods:['GET'])]
    public function subscribe(Request $request,UniverseContext $context,Authorization $authorization): JsonResponse
    {
        $topic=NotificationService::topic($context->membership());
        $authorization->setCookie($request,[$topic],additionalClaims:['exp'=>new \DateTimeImmutable('+15 minutes')]);
        return $this->json(['url'=>'/.well-known/mercure?'.http_build_query(['topic'=>$topic])],200,['Cache-Control'=>'private, no-store']);
    }
}
