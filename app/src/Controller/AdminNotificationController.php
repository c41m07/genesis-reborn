<?php
namespace App\Controller;

use App\Service\Admin\AdminNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminNotificationController extends AbstractController
{
    #[Route('/admin/notifications', name: 'app_admin_notifications', methods: ['GET'])]
    public function index(AdminNotificationService $notifications): JsonResponse
    {
        return new JsonResponse($notifications->counts($this->getUser()), 200, ['Cache-Control' => 'private, no-store']);
    }
}
