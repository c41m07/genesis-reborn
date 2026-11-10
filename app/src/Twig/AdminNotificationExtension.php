<?php
namespace App\Twig;

use App\Service\Admin\AdminNotificationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminNotificationExtension extends AbstractExtension
{
    public function __construct(private AdminNotificationService $notifications) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('admin_notifications', [$this->notifications, 'counts'])];
    }
}
