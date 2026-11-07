<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicPageMetadata
{
    private const PAGES = [
        'app_home' => ['title' => 'Genesis Reborn · Jeu de stratégie spatiale', 'description' => 'Genesis Reborn, jeu de stratégie spatiale par navigateur : bâtis ta colonie, explore des systèmes inconnus et forge ton empire.'],
        'app_register' => ['title' => 'Inscription · Genesis Reborn', 'description' => 'Crée ton compte Genesis Reborn pour développer tes colonies, explorer la galaxie et rejoindre plusieurs univers de stratégie spatiale.'],
    ];

    public function __construct(
        private readonly UrlGeneratorInterface $router,
        #[Autowire('%env(DEFAULT_URI)%')] private readonly string $publicUri,
    ) {}

    public function pages(): array
    {
        return array_map(fn (string $route): array => $this->page($route), array_keys(self::PAGES));
    }

    public function page(string $route): ?array
    {
        if (!isset(self::PAGES[$route])) return null;
        return self::PAGES[$route] + [
            'url' => $this->url($route),
            'image' => rtrim($this->publicUri, '/').'/art/genesis-reborn-emblem.webp',
        ];
    }

    public function url(string $route): string
    {
        return rtrim($this->publicUri, '/').$this->router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
