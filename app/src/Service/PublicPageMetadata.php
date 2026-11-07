<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicPageMetadata
{
    private const PAGES = [
        'app_home' => ['title' => 'Genesis Reborn · Jeu de stratégie spatiale', 'description' => 'Genesis Reborn, jeu de stratégie spatiale par navigateur : bâtis ta colonie, explore des systèmes inconnus et forge ton empire.'],
        'app_register' => ['title' => 'Inscription · Genesis Reborn', 'description' => 'Crée ton compte Genesis Reborn pour développer tes colonies, explorer la galaxie et rejoindre plusieurs univers de stratégie spatiale.'],
        'app_legal_mentions' => ['title' => 'Mentions légales · Genesis Reborn', 'description' => 'Informations disponibles sur l’éditeur, les contacts et l’hébergement de Genesis Reborn. Page provisoire à compléter.', 'indexable' => false],
        'app_legal_terms' => ['title' => 'CGU · Genesis Reborn', 'description' => 'Présentation provisoire des conditions d’utilisation de Genesis Reborn et des éléments restant à finaliser.', 'indexable' => false],
        'app_legal_privacy' => ['title' => 'Politique de confidentialité · Genesis Reborn', 'description' => 'Données personnelles utilisées par Genesis Reborn, contacts et droits des joueurs. Information provisoire à compléter.', 'indexable' => false],
        'app_legal_cookies' => ['title' => 'Cookies et connexion · Genesis Reborn', 'description' => 'Information provisoire sur les cookies, la mémorisation de connexion et les services externes de Genesis Reborn.', 'indexable' => false],
    ];

    public function __construct(
        private readonly UrlGeneratorInterface $router,
        #[Autowire('%env(DEFAULT_URI)%')] private readonly string $publicUri,
    ) {}

    public function pages(): array
    {
        $routes = array_keys(array_filter(self::PAGES, static fn (array $page): bool => $page['indexable'] ?? true));
        return array_map(fn (string $route): array => $this->page($route), $routes);
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
