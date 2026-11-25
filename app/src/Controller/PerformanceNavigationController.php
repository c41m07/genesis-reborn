<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Performance\BrowserSampleToken;
use App\Service\Performance\PerformanceStore;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PerformanceNavigationController
{
    #[Route('/_performance/navigation', name: 'app_performance_navigation', methods: ['POST'])]
    public function __invoke(
        Request $request,
        BrowserSampleToken $tokens,
        PerformanceStore $store,
        LoggerInterface $logger,
        #[Autowire('%env(int:APP_PERFORMANCE_SAMPLE_PERCENT)%')] int $samplePercent,
    ): Response {
        $response = new Response('', Response::HTTP_NO_CONTENT, ['Cache-Control' => 'private, no-store']);
        if ($samplePercent < 1 || $request->headers->get('Sec-Fetch-Site') !== 'same-origin'
            || ($request->headers->has('Origin') && $request->headers->get('Origin') !== $request->getSchemeAndHttpHost())
            || $request->getContentTypeFormat() !== 'json'
            || (int) $request->headers->get('Content-Length', '0') > 800) {
            return $response;
        }
        $body = $request->getContent();
        if (strlen($body) > 800) {
            return $response;
        }
        $data = json_decode($body, true, 3);
        if (!is_array($data) || !is_string($data['sample'] ?? null)
            || !in_array($data['device'] ?? null, ['mobile', 'desktop'], true)) {
            return $response;
        }
        $sample = $tokens->verify($data['sample']);
        if ($sample === null) {
            return $response;
        }
        $times = [];
        foreach (['ttfb', 'html_transfer', 'lcp'] as $field) {
            $value = $data[$field] ?? null;
            if (is_int($value) && $value >= 0 && $value <= 600_000) {
                $times[$field] = $value;
            }
        }
        if ($times !== []) {
            try {
                $store->recordBrowser($sample, $data['device'], $times);
            } catch (Exception $error) {
                $logger->warning('Stockage des mesures navigateur indisponible.', ['type' => $error::class]);
            }
        }

        return $response;
    }
}
