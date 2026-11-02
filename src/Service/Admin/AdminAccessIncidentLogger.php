<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\AdminAuditLog;
use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

final class AdminAccessIncidentLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public function log(Request $request, ?Player $actor): void
    {
        $path = $request->getPathInfo();
        if ($path !== '/admin' && !str_starts_with($path, '/admin/')) {
            return;
        }

        $incident = new AdminAuditLog();
        $incident->actor = $actor;
        $incident->action = 'admin_access_denied';
        $incident->targetType = 'admin_route';
        $incident->targetId = crc32($path) & 0x7fffffff;
        $incident->details = [
            'path' => $path,
            'method' => $request->getMethod(),
            'authenticated' => $actor !== null,
        ];
        $incident->createdAt = $this->clock->now();

        $this->entityManager->persist($incident);
        $this->entityManager->flush();
    }
}
