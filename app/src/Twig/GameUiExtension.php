<?php
namespace App\Twig;
use App\Service\Colony\{CatalogueItemLink, GameShell};
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
final class GameUiExtension extends AbstractExtension
{
    public function __construct(private GameShell $shell, private \App\Service\Universe\UniverseClosingClock $closingClock, private CatalogueItemLink $catalogueLinks) {}
    public function getFunctions():array
    {
        return [new TwigFunction('universe_closing', [$this->closingClock, 'snapshot']),new TwigFunction('game_shell',[$this->shell,'get']),new TwigFunction('catalogue_item_url',[$this->catalogueLinks,'focus']),new TwigFunction('duration_label',fn(int $s)=>($s>=3600?intdiv($s,3600).' h ':'').($s>=60?intdiv($s%3600,60).' min ': '').($s%60).' s')];
    }
}
