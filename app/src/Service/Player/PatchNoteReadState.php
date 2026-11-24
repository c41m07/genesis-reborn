<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\{PatchNote, Player};
use App\Manager\GameManager;
use App\Repository\PatchNoteRepository;

final class PatchNoteReadState
{
    private const READ_ID = 'last_patch_note_read_id';
    private const READ_TIME = 'last_patch_note_read_at';

    public function __construct(private PatchNoteRepository $notes, private GameManager $manager)
    {
    }

    public function hasUnread(Player $player): bool
    {
        $latest = $this->notes->latestPublished();

        return $latest !== null && $this->isAfterCursor($latest, $player);
    }

    public function markRead(Player $player, PatchNote $note): void
    {
        $this->manager->transaction(function () use ($player, $note): void {
            // Ne pas faire reculer le curseur si une lecture plus récente a déjà été enregistrée.
            $this->manager->lockCurrent($player);
            $time = $note->publishedAt?->getTimestamp();
            $id = $note->id;
            if ($time === null || $id === null) {
                return;
            }
            $cursor = $this->cursor($player);
            if ($cursor !== null && [$time, $id] < $cursor) {
                return;
            }

            $player->preferences[self::READ_TIME] = $time;
            $player->preferences[self::READ_ID] = $id;
        });
    }

    private function isAfterCursor(PatchNote $note, Player $player): bool
    {
        $time = $note->publishedAt?->getTimestamp();
        $id = $note->id;
        if ($time === null || $id === null) {
            return false;
        }
        $cursor = $this->cursor($player);

        return $cursor === null || [$time, $id] > $cursor;
    }

    /** @return array{int, int}|null */
    private function cursor(Player $player): ?array
    {
        $id = (int) ($player->preferences[self::READ_ID] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $time = $player->preferences[self::READ_TIME] ?? null;
        if (!is_int($time)) {
            // Les anciens comptes n’ont que l’ID. Lire sa date si la publication existe encore.
            $time = $this->notes->find($id)?->publishedAt?->getTimestamp();
        }

        return $time === null ? null : [$time, $id];
    }
}
