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
        $note = $this->notes->latestPublished();
        if ($note === null) {
            return false;
        }

        $latest = $this->position($note);
        $cursor = $this->cursor($player);

        return $latest !== null && ($cursor === null || $latest > $cursor);
    }

    public function markRead(Player $player, PatchNote $note): void
    {
        $position = $this->position($note);
        $stored = $this->storedCursor($player);
        if ($position === null || ($stored !== null && $position <= $stored)) {
            return;
        }

        $this->manager->transaction(function () use ($player, $position): void {
            // Relire sous verrou : une consultation concurrente peut avoir enregistré une note plus récente.
            $this->manager->lockCurrent($player);
            $cursor = $this->cursor($player);
            if ($cursor !== null && $position < $cursor) {
                return;
            }

            [$player->preferences[self::READ_TIME], $player->preferences[self::READ_ID]] = $position;
        });
    }

    /** @return array{int, int}|null */
    private function position(PatchNote $note): ?array
    {
        $time = $note->publishedAt?->getTimestamp();
        $id = $note->id;

        return $time === null || $id === null ? null : [$time, $id];
    }

    /** @return array{int, int}|null */
    private function storedCursor(Player $player): ?array
    {
        $id = (int) ($player->preferences[self::READ_ID] ?? 0);
        $time = $player->preferences[self::READ_TIME] ?? null;

        return $id > 0 && is_int($time) ? [$time, $id] : null;
    }

    /** @return array{int, int}|null */
    private function cursor(Player $player): ?array
    {
        $stored = $this->storedCursor($player);
        if ($stored !== null) {
            return $stored;
        }

        // L’ancien ID n’est qu’un repli de lecture. Ne prendre que la date d’une note encore
        // visible et ne pas la sauvegarder sans une véritable consultation de la liste.
        $id = (int) ($player->preferences[self::READ_ID] ?? 0);
        $legacyNote = $id > 0 ? $this->notes->publishedById($id) : null;

        return $legacyNote === null ? null : $this->position($legacyNote);
    }
}
