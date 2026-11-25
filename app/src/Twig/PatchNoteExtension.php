<?php
declare(strict_types=1);

namespace App\Twig;

use App\Service\Player\PatchNoteReadState;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PatchNoteExtension extends AbstractExtension
{
    public function __construct(private PatchNoteReadState $readState) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('has_unread_patch_note', $this->readState->hasUnread(...))];
    }
}
