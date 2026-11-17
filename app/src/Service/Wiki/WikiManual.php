<?php

namespace App\Service\Wiki;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Source unique du manuel ; aucune lecture ou écriture de données de jeu. */
final class WikiManual
{
    /** @var list<array<string,mixed>>|null */
    private ?array $sections = null;

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDirectory) {}

    /** @return list<array{id:string,title:string,part:string}> */
    public function chapters(): array
    {
        return array_map(static fn(array $section): array => [
            'id' => $section['id'], 'title' => $section['title'], 'part' => $section['part'],
        ], $this->sections());
    }

    /** @return array<string,mixed>|null */
    public function chapter(string $id): ?array
    {
        foreach ($this->sections() as $section) {
            if ($section['id'] === $id) return $section;
        }
        return null;
    }

    /** @return list<array{id:string,title:string,part:string,excerpt:string}> */
    public function search(string $query): array
    {
        if ($query === '') return [];
        $results = [];
        foreach ($this->sections() as $section) {
            // Conserver le périmètre de recherche historique, y compris les calculs repliés et les identifiants.
            if (mb_stripos(json_encode($section, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $query) === false) continue;
            $texts = [$section['title'], ...$section['paragraphs'], ...($section['steps'] ?? [])];
            foreach ($section['calculations'] ?? [] as $calculation) {
                $texts = [...$texts, $calculation['title'], $calculation['explanation'], $calculation['formula'], ...$calculation['variables'], $calculation['example']];
            }
            $text = $section['paragraphs'][0] ?? $section['title'];
            foreach ($texts as $candidate) {
                if (mb_stripos($candidate, $query) !== false) { $text = $candidate; break; }
            }
            $position = mb_stripos($text, $query);
            $start = max(0, ($position === false ? 0 : $position) - 60);
            $excerpt = ($start > 0 ? '…' : '').mb_substr($text, $start, 240).(mb_strlen($text) > $start + 240 ? '…' : '');
            $results[] = ['id'=>$section['id'], 'title'=>$section['title'], 'part'=>$section['part'], 'excerpt'=>$excerpt];
        }
        return $results;
    }

    /** @return array{referenceTitle:string,referenceIntro:string,referenceBlocks:list<array{title:string,text:string}>}|null */
    public function reference(string $source): ?array
    {
        // L’identifiant public ne devient jamais un chemin de fichier fourni par le visiteur.
        $file = match ($source) { 'regles'=>'REGLES.md', 'decisions'=>'DECISIONS.md', default=>null };
        if ($file === null) return null;
        $parts = preg_split('/^(## .+)$/m', $this->read($file), -1, PREG_SPLIT_DELIM_CAPTURE);
        $intro = array_shift($parts);
        $blocks = [];
        for ($index = 0; $index < count($parts); $index += 2) {
            $blocks[] = ['title'=>substr($parts[$index], 3), 'text'=>$parts[$index + 1] ?? ''];
        }
        return [
            'referenceTitle'=>$source === 'regles' ? 'Règles de référence' : 'Décisions du projet',
            'referenceIntro'=>$intro, 'referenceBlocks'=>$blocks,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function sections(): array
    {
        // Le fichier de référence fini reste unique ; seules les données de la page demandée vont au template.
        return $this->sections ??= json_decode($this->read('wiki.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function read(string $file): string
    {
        $text = file_get_contents(dirname($this->projectDirectory).'/docs/'.$file);
        if ($text === false) throw new \RuntimeException('Le document du wiki ne peut pas être lu.');
        return $text;
    }
}
