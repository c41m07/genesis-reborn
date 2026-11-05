<?php
declare(strict_types=1);

namespace App\Twig;

use App\Repository\PatchNoteRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PatchNoteExtension extends AbstractExtension
{
    public function __construct(private PatchNoteRepository $patchNotes) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('latest_patch_note_id', fn (): int => $this->patchNotes->latestPublishedId())];
    }
}
