<?php
namespace App\Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
final class RequestKeyExtension extends AbstractExtension
{
    public function getFunctions(): array { return [new TwigFunction('request_key',fn()=>bin2hex(random_bytes(16)))]; }
}
