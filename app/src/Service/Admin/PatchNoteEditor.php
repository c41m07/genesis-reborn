<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\PatchNote;

final class PatchNoteEditor
{
    /** @return array{title:string, version:string, summary:string, content:string, status:string, publishedAt:string} */
    public function draft(PatchNote $note): array
    {
        return [
            'title' => $note->title,
            'version' => $note->version,
            'summary' => $note->summary,
            'content' => $note->content,
            'status' => $note->status,
            'publishedAt' => $note->publishedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i') ?? '',
        ];
    }

    /** @param array{title:string, version:string, summary:string, content:string, status:string, publishedAt:string} $draft */
    public function apply(PatchNote $note, array $draft): void
    {
        // Valider entièrement le brouillon avant de modifier une entité suivie par Doctrine.
        // trim() efface aussi les caractères nuls : les refuser avant cette normalisation.
        if (str_contains($draft['publishedAt'], "\0")) {
            throw new \DomainException('Renseignez une date de publication UTC valide.');
        }
        $values = array_map(trim(...), $draft);
        foreach (['title', 'version', 'summary', 'content'] as $field) {
            if ($values[$field] === '') {
                throw new \DomainException('Tous les champs sont obligatoires.');
            }
        }
        foreach (['title' => 160, 'version' => 40, 'summary' => 500] as $field => $limit) {
            if (mb_strlen($values[$field]) > $limit) {
                $label = ['title' => 'Le titre', 'version' => 'La version', 'summary' => 'Le résumé'][$field];
                throw new \DomainException($label.' dépasse '.$limit.' caractères.');
            }
        }
        if (!in_array($values['status'], ['draft', 'published'], true)) {
            throw new \DomainException('Choisissez un statut valide.');
        }

        $publishedAt = null;
        if ($values['publishedAt'] !== '') {
            if (!preg_match('/^[1-9]\\d{3}-\\d{2}-\\d{2}T\\d{2}:\\d{2}(?::\\d{2})?$/D', $values['publishedAt'])) {
                throw new \DomainException('Renseignez une date de publication UTC valide.');
            }
            // Le champ datetime-local exprime ici une date UTC, sans conversion implicite du navigateur.
            $format = strlen($values['publishedAt']) === 19 ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i';
            $publishedAt = \DateTimeImmutable::createFromFormat('!'.$format, $values['publishedAt'], new \DateTimeZone('UTC'));
            if ($publishedAt === false || $publishedAt->format($format) !== $values['publishedAt']) {
                throw new \DomainException('Renseignez une date de publication UTC valide.');
            }
        }
        if ($values['status'] === 'draft') {
            $publishedAt = null;
        } elseif ($publishedAt === null) {
            $publishedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        $note->title = $values['title'];
        $note->version = $values['version'];
        $note->summary = $values['summary'];
        $note->content = $values['content'];
        $note->status = $values['status'];
        $note->publishedAt = $publishedAt;
    }
}
