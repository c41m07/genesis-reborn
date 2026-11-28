<?php
declare(strict_types=1);
namespace App\Twig;

use App\Entity\Universe;
use App\Repository\EditorialRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EditorialExtension extends AbstractExtension
{
    public function __construct(private readonly EditorialRepository $library) {}
    public function getFunctions(): array
    {
        return [new TwigFunction('editorial_page', fn(Universe $world,string $code): ?array => $this->library->published('page',$world,$code)[$code] ?? null)];
    }
}
