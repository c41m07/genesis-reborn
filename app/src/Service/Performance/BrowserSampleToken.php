<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class BrowserSampleToken
{
    public const LIFETIME_SECONDS = 300;

    public function __construct(
        private readonly ClockInterface $clock,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function issue(string $route): string
    {
        $data = $route . '.' . $this->clock->now()->getTimestamp() . '.' . bin2hex(random_bytes(12));

        return $data . '.' . $this->sign($data);
    }

    /** @return array{route:string,issued:int,receipt:string}|null */
    public function verify(string $token): ?array
    {
        if (!preg_match('/\A([a-z0-9_]{1,40})\.(\d{10})\.([a-f0-9]{24})\.([a-f0-9]{64})\z/D', $token, $match)
            || !isset(PerformanceMetrics::PAGES[$match[1]])) {
            return null;
        }
        [, $route, $time, $nonce, $signature] = $match;
        $issued = (int) $time;
        $age = $this->clock->now()->getTimestamp() - $issued;
        if ($age < 0 || $age > self::LIFETIME_SECONDS || !hash_equals($this->sign($route . '.' . $time . '.' . $nonce), $signature)) {
            return null;
        }

        // Le reçu ne contient ni route, ni identifiant de compte, ni données de navigation.
        return ['route' => $route, 'issued' => $issued, 'receipt' => hash('sha256', $token)];
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', 'genesis.page-performance.v1:' . $payload, $this->secret);
    }
}
