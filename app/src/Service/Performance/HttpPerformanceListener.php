<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class HttpPerformanceListener
{
    private const STARTED_ATTRIBUTE = '_gr_performance_started';
    private const FINISHED_ATTRIBUTE = '_gr_performance_finished';

    public function __construct(
        private readonly PerformanceStore $store,
        private readonly ClockInterface $clock,
        private readonly BrowserSampleToken $browserTokens,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(int:APP_PERFORMANCE_SAMPLE_PERCENT)%')]
        private readonly int $samplePercent,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 128)]
    public function begin(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->samplePercent < 1 || !$event->isMainRequest() || !$request->isMethod('GET')
            || $request->isXmlHttpRequest() || $request->headers->has('Turbo-Frame')) {
            return;
        }
        if (random_int(1, 100) <= min(100, $this->samplePercent)) {
            $request->attributes->set(self::STARTED_ATTRIBUTE, hrtime(true));
        }
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
    public function chooseRoute(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $started = $request->attributes->get(self::STARTED_ATTRIBUTE);
        $route = $request->attributes->get('_route');
        if (!$event->isMainRequest() || !is_int($started) || !is_string($route)) {
            return;
        }
        // La seule dimension supplémentaire est une section canonique, jamais la query string complète.
        $querySection = $request->query->all()['section'] ?? null;
        $page = PerformanceMetrics::page($route, is_string($querySection) ? $querySection : null);
        if ($page !== null) {
            $request->attributes->set(RequestPerformance::ATTRIBUTE, new RequestPerformance($page, $started));
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -256)]
    public function finish(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if ($event->isMainRequest()
            && $request->attributes->get(RequestPerformance::ATTRIBUTE) instanceof RequestPerformance
            && $response->getStatusCode() === 200
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            $request->attributes->set(self::FINISHED_ATTRIBUTE, hrtime(true));
            // Seul un jeton court et signé sort de l’application, jamais les chronos du SQL.
            $profile = $request->attributes->get(RequestPerformance::ATTRIBUTE);
            $response->headers->set('Server-Timing', 'grperf;desc="' . $this->browserTokens->issue($profile->route) . '"', false);
        }
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function persist(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $profile = $request->attributes->get(RequestPerformance::ATTRIBUTE);
        $finished = $request->attributes->get(self::FINISHED_ATTRIBUTE);
        $request->attributes->remove(RequestPerformance::ATTRIBUTE);
        $request->attributes->remove(self::FINISHED_ATTRIBUTE);
        if (!$profile instanceof RequestPerformance || !is_int($finished)) {
            return;
        }

        try {
            // Une seule mise à jour groupée après envoi de la page. Une panne de collecte ne bloque pas le jeu.
            $this->store->record($this->clock->now(), $profile->route, 'server', $profile->measurements($finished));
        } catch (Exception $error) {
            // Une base inaccessible ne doit pas empêcher la réponse, même pendant une migration.
            $this->logger->warning('Stockage des mesures HTTP indisponible.', ['type' => $error::class]);
        }
    }
}
