<?php
namespace App\Twig;
use App\Service\Colony\GameShell;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
final class GameUiExtension extends AbstractExtension
{
    public function __construct(private GameShell $shell) {}
    public function getFunctions():array
    {
        return [new TwigFunction('game_shell',[$this->shell,'get']),new TwigFunction('duration_label',fn(int $s)=>($s>=3600?intdiv($s,3600).' h ':'').($s>=60?intdiv($s%3600,60).' min ': '').($s%60).' s')];
    }
}
