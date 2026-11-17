<?php

namespace App\Controller;

use App\Service\Wiki\WikiManual;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class WikiController extends AbstractController
{
    public function __construct(private readonly WikiManual $manual) {}

    #[Route('/wiki', name: 'app_wiki', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Les anciens formulaires GET restent utilisables, y compris sans JavaScript.
        $chapter = $request->query->getString('chapter');
        if ($chapter !== '') {
            if ($this->manual->chapter($chapter) === null) throw $this->createNotFoundException('Chapitre inconnu.');
            return $this->redirectToRoute('app_wiki_chapter', ['chapter'=>$chapter]);
        }
        $query = $this->query($request);
        if ($query !== '') return $this->redirectToRoute('app_wiki_search', ['q'=>$query]);
        return $this->render('wiki/index.html.twig', $this->pageData());
    }

    #[Route('/wiki/chapitres/{chapter}', name: 'app_wiki_chapter', methods: ['GET'])]
    public function chapter(string $chapter, Request $request): Response
    {
        $section = $this->manual->chapter($chapter);
        if ($section === null) throw $this->createNotFoundException('Chapitre inconnu.');
        $data = $this->pageData();
        $position = array_search($chapter, array_column($data['chapters'], 'id'), true);
        return $this->render('wiki/chapter.html.twig', array_replace($data, [
            'section'=>$section, 'currentChapter'=>$chapter, 'query'=>$this->query($request),
            'previousChapter'=>$data['chapters'][$position - 1] ?? null,
            'nextChapter'=>$data['chapters'][$position + 1] ?? null,
        ]));
    }

    #[Route('/wiki/recherche', name: 'app_wiki_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $this->query($request);
        return $this->render('wiki/search.html.twig', array_replace($this->pageData(), [
            'query'=>$query, 'results'=>$this->manual->search($query),
        ]));
    }

    #[Route('/wiki/references/{source}', name: 'app_wiki_reference', methods: ['GET'])]
    public function reference(string $source): Response
    {
        $reference = $this->manual->reference($source);
        if ($reference === null) throw $this->createNotFoundException('Référence inconnue.');
        return $this->render('wiki/reference.html.twig', array_replace($this->pageData(), $reference, ['source'=>$source]));
    }

    private function query(Request $request): string
    {
        return mb_substr(trim($request->query->getString('q')), 0, 150);
    }

    /** @return array{chapters:list<array{id:string,title:string,part:string}>,chapterDestinations:array<string,string>,currentChapter:null,source:null,query:string} */
    private function pageData(): array
    {
        $chapters = $this->manual->chapters();
        $destinations = [];
        foreach ($chapters as $chapter) $destinations[$chapter['id']] = $this->generateUrl('app_wiki_chapter', ['chapter'=>$chapter['id']]);
        return ['chapters'=>$chapters, 'chapterDestinations'=>$destinations, 'currentChapter'=>null, 'source'=>null, 'query'=>''];
    }
}
