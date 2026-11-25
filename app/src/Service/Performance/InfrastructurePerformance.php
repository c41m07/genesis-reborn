<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class InfrastructurePerformance
{
    private int $nextPruneAt = 0;

    public function __construct(
        private readonly PerformanceStore $store,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(int:APP_PERFORMANCE_SAMPLE_PERCENT)%')]
        private readonly int $samplePercent,
    ) {
    }

    public function sample(): bool
    {
        return $this->samplePercent > 0 && random_int(1, 100) <= min(100, $this->samplePercent);
    }

    public function enabled(): bool
    {
        return $this->samplePercent > 0;
    }

    public static function millisecondsSince(int $start): int
    {
        return max(0, (int) round((hrtime(true) - $start) / 1_000_000));
    }

    /** @param array<string,int> $sample */
    public function record(string $scope, array $sample): void
    {
        if (!$this->enabled()) {
            return;
        }
        try {
            $this->store->record($this->clock->now(), $scope, 'server', $sample);
        } catch (Exception $error) {
            $this->logger->warning('Métriques d’infrastructure indisponibles.', ['scope' => $scope, 'type' => $error::class]);
        }
    }
    public function pruneIfDue(): void
    {
        $now = $this->clock->now();
        if (!$this->enabled() || $now->getTimestamp() < $this->nextPruneAt) {
            return;
        }
        $this->nextPruneAt = $now->getTimestamp() + 1800;
        try {
            $this->store->prune($now);
        } catch (Exception $error) {
            $this->logger->warning('Entretien des métriques indisponible.', ['type' => $error::class]);
        }
    }

}
