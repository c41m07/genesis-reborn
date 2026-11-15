<?php

namespace App\Service\Colony;

use Symfony\Component\HttpFoundation\{AcceptHeader, JsonResponse, Request};

/** Transport des fragments de page ; aucun calcul économique ni endpoint de ressources. */
final class LiveRegionResponse
{
    /** @param array<string,string> $regions */
    public function create(Request $request, array $regions): JsonResponse
    {
        // Ces fragments dépendent du compte courant : aucun cache partagé ne doit les conserver.
        $response = new JsonResponse(['regions'=>$regions], 200, ['Cache-Control'=>'private, no-store']);
        // Ce JSON est lu par fetch, jamais intégré dans un script HTML ; Twig échappe déjà les données.
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->setVary(['Accept', 'X-Genesis-Live', 'Accept-Encoding']);
        $accepted = AcceptHeader::fromString($request->headers->get('Accept-Encoding', ''));
        // Un refus explicite de gzip (q=0) garde la priorité sur l’acceptation générique '*'.
        $gzip = $accepted->get('gzip') ?? $accepted->get('*');
        $content = $response->getContent();
        if ($gzip !== null && $gzip->getQuality() > 0 && strlen($content) >= 1024 && function_exists('gzencode')) {
            $compressed = gzencode($content, 6);
            if ($compressed !== false) {
                $response->setContent($compressed);
                $response->headers->set('Content-Encoding', 'gzip');
            }
        }
        return $response;
    }
}
