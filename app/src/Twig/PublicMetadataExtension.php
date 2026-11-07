<?php
namespace App\Twig;

use App\Service\PublicPageMetadata;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PublicMetadataExtension extends AbstractExtension
{
    public function __construct(private readonly PublicPageMetadata $metadata) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('public_metadata', $this->metadata->page(...))];
    }
}
