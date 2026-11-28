<?php
declare(strict_types=1);
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog, Player};
use App\Manager\GameManager;
use Psr\Clock\ClockInterface;

final readonly class EditorialActions
{
    public function __construct(private GameManager $manager, private ClockInterface $clock) {}

    public function authorizeLocked(Player $actor): void
    {
        $this->manager->lockCurrent($actor);
        if ($actor->lockedAt !== null || !in_array('ROLE_ADMIN', $actor->getRoles(), true)) {
            throw new \DomainException('Un compte administrateur actif est nécessaire.');
        }
    }

    public function log(Player $actor, string $action, string $type, int $id, array $details): void
    {
        $entry = new AdminAuditLog();
        $entry->actor = $actor;
        $entry->action = $action;
        $entry->targetType = $type;
        $entry->targetId = $id;
        $entry->details = $details;
        $entry->createdAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $this->manager->save($entry);
    }
}
