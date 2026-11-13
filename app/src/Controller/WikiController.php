<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WikiController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDirectory) {}

    #[Route('/wiki', name: 'app_wiki', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $sections = json_decode(file_get_contents(dirname($this->projectDirectory).'/docs/wiki.json'), true, 512, JSON_THROW_ON_ERROR);
        $chapters = $sections;
        $chapter = $request->query->getString('chapter');
        if ($chapter !== '') {
            if (!in_array($chapter, array_column($chapters, 'id'), true)) throw $this->createNotFoundException('Chapitre inconnu.');
            return $this->redirectToRoute('app_wiki', ['_fragment' => 'wiki-'.$chapter]);
        }
        $query = mb_substr(trim($request->query->getString('q')), 0, 150);
        if ($query !== '') {
            $sections = array_values(array_filter($sections, static fn(array $section): bool =>
                mb_stripos(json_encode($section, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $query) !== false
            ));
        }
        return $this->render('wiki/index.html.twig', ['sections' => $sections, 'chapters' => $chapters, 'query' => $query]);
    }

    #[Route('/wiki/references/{source}', name: 'app_wiki_reference', methods: ['GET'])]
    public function reference(string $source): Response
    {
        $file = match ($source) {
            'regles' => 'REGLES.md',
            'decisions' => 'DECISIONS.md',
            default => throw $this->createNotFoundException('Référence inconnue.'),
        };
        $text=file_get_contents(dirname($this->projectDirectory).'/docs/'.$file);
        $parts=preg_split('/^(## .+)$/m',$text,-1,PREG_SPLIT_DELIM_CAPTURE);
        $intro=array_shift($parts);$blocks=[];
        for($index=0;$index<count($parts);$index+=2) $blocks[]=['title'=>substr($parts[$index],3),'text'=>$parts[$index+1]??''];
        return $this->render('wiki/reference.html.twig', [
            'referenceTitle' => $source === 'regles' ? 'Règles de référence' : 'Décisions du projet',
            'referenceIntro'=>$intro,'referenceBlocks'=>$blocks,
        ]);
    }
}
