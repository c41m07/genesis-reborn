<?php

declare(strict_types=1);

namespace App\Service\Notification;

final class OutboxPublicationFailed extends \RuntimeException
{
    /** @param non-empty-list<string> $failed Références internes, sans paramètres ni jetons. */
    public function __construct(int $published, array $failed)
    {
        parent::__construct(sprintf(
            '%d tâche(s) publiée(s), %d non confirmée(s) conservée(s) : %s%s.',
            $published,
            count($failed),
            implode(', ', array_slice($failed, 0, 10)),
            count($failed) > 10 ? ', …' : '',
        ));
    }
}
