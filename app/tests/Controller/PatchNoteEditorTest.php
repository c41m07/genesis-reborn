<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\PatchNote;
use App\Service\Admin\PatchNoteEditor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PatchNoteEditorTest extends TestCase
{
    private function values(): array
    {
        return ['title' => 'Titre', 'version' => 'Alpha-test', 'summary' => 'Résumé', 'content' => 'Détails', 'status' => 'draft', 'publishedAt' => ''];
    }

    public static function invalidDrafts(): iterable
    {
        foreach (['title', 'version', 'summary', 'content'] as $field) {
            yield $field.' vide' => [$field, '   '];
        }
        yield 'titre trop long' => ['title', str_repeat('é', 161)];
        yield 'version trop longue' => ['version', str_repeat('é', 41)];
        yield 'résumé trop long' => ['summary', str_repeat('é', 501)];
        yield 'statut inconnu' => ['status', 'inconnu'];
        foreach (['pas-une-date', '2026-02-30T12:00', '2026-11-19T25:00', "2026-11-19T12:00\0", '0001-01-01T00:00'] as $index => $date) {
            yield 'date invalide '.$index => ['publishedAt', $date];
        }
    }

    #[DataProvider('invalidDrafts')]
    public function testInvalidDraftDoesNotMutateTheNote(string $field, string $value): void
    {
        $note = new PatchNote();
        $note->title = 'Titre conservé';
        $note->status = 'published';
        $note->publishedAt = new \DateTimeImmutable('2026-11-01T10:00:00Z');
        $before = clone $note;
        $editor = new PatchNoteEditor();
        try {
            $editor->apply($note, array_replace($this->values(), [$field => $value]));
            self::fail('Le brouillon invalide doit être refusé.');
        } catch (\DomainException) {
            self::assertEquals($before, $note);
        }
    }

    public function testPublicationDatesAndFieldLimits(): void
    {
        $note = new PatchNote();
        $editor = new PatchNoteEditor();
        $draft = array_replace($this->values(), ['title' => str_repeat('é', 160), 'version' => str_repeat('é', 40), 'summary' => str_repeat('é', 500), 'status' => 'published', 'publishedAt' => '2030-02-28T12:15']);
        $editor->apply($note, $draft);
        self::assertSame('2030-02-28T12:15:00+00:00', $note->publishedAt->format('c'));
        self::assertSame($draft, $editor->draft($note));
        $editor->apply($note, array_replace($this->values(), ['publishedAt' => '2030-02-28T12:15']));
        self::assertNull($note->publishedAt);
        $before = time();
        $editor->apply($note, array_replace($this->values(), ['status' => 'published']));
        self::assertGreaterThanOrEqual($before, $note->publishedAt->getTimestamp());
        self::assertLessThanOrEqual(time(), $note->publishedAt->getTimestamp());
    }
}
