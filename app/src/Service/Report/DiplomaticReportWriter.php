<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\{DiplomaticReport, PlayerUniverse, ReportNotice};
use App\Manager\GameManager;
use Psr\Clock\ClockInterface;

/** Un rapport privé par transition validée, dans la transaction métier et sans autre envoi. */
final readonly class DiplomaticReportWriter
{
    public function __construct(private GameManager $manager, private ClockInterface $clock)
    {
    }

    public function record(
        PlayerUniverse $recipient,
        PlayerUniverse $counterpart,
        string $subject,
        string $outcome,
        ?string $location = null,
        ?string $guildName = null,
    ): void {
        // Les appelants détiennent les verrous et ne passent ici qu’après une transition réussie.
        if ($recipient->id === null || $counterpart->id === null || $recipient->id === $counterpart->id
            || !$recipient->isPlayerControlled() || !$counterpart->isPlayerControlled()
            || $recipient->universe->id !== $counterpart->universe->id) {
            return;
        }

        $report = new DiplomaticReport($recipient, $counterpart, $subject, $outcome, $location, $guildName, $this->clock->now());
        $this->manager->save($report);
        $this->manager->save(new ReportNotice($recipient, $report));
    }
}
