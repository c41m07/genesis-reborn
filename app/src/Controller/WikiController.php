<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WikiController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDirectory) {}

    #[Route('/wiki', name: 'app_wiki', methods: ['GET'])]
    public function index(): Response
    {
        $sections = json_decode(file_get_contents(dirname($this->projectDirectory).'/docs/wiki.json'), true, 512, JSON_THROW_ON_ERROR);
        return $this->render('wiki/index.html.twig', ['sections' => $sections]);
    }

    #[Route('/wiki/references/{source}', name: 'app_wiki_reference', methods: ['GET'])]
    public function reference(string $source): Response
    {
        $file = match ($source) {
            'regles' => 'REGLES.md',
            'decisions' => 'DECISIONS.md',
            default => throw $this->createNotFoundException('Référence inconnue.'),
        };
        return $this->render('wiki/reference.html.twig', [
            'referenceTitle' => $source === 'regles' ? 'Règles de référence' : 'Décisions du projet',
            'referenceText' => file_get_contents(dirname($this->projectDirectory).'/docs/'.$file),
        ]);
    }
}
