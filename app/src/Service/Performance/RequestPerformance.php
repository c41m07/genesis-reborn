<?php

declare(strict_types=1);

namespace App\Service\Performance;

/** État éphémère attaché à une seule requête, jamais à un compte. */
final class RequestPerformance
{
    public const ATTRIBUTE = '_gr_performance';

    public int $sqlNanoseconds = 0;
    public int $sqlExecutions = 0;
    private int $twigDepth = 0;
    private ?int $twigStarted = null;
    private ?int $twigNanoseconds = null;

    public function __construct(public readonly string $route, public readonly int $started)
    {
    }

    public function duringSql(callable $sql): mixed
    {
        $start = hrtime(true);
        try {
            return $sql();
        } finally {
            $this->sqlNanoseconds += hrtime(true) - $start;
            ++$this->sqlExecutions;
        }
    }

    public function enterTwig(): void
    {
        if ($this->twigDepth++ === 0) {
            $this->twigStarted = hrtime(true);
        }
    }

    public function leaveTwig(): void
    {
        // Un template enfant est inclus dans la durée de son parent une seule fois.
        if ($this->twigDepth > 0 && --$this->twigDepth === 0 && $this->twigStarted !== null) {
            $this->twigNanoseconds = ($this->twigNanoseconds ?? 0) + hrtime(true) - $this->twigStarted;
            $this->twigStarted = null;
        }
    }

    /** @return array<string,int> */
    public function measurements(int $finished): array
    {
        $php = max(0, $finished - $this->started);
        $sql = min($php, $this->sqlNanoseconds);
        $values = [
            'http_php' => (int) round($php / 1_000_000),
            'http_sql' => (int) round($sql / 1_000_000),
            'php_pair' => 0,
        ];
        if ($php >= 100_000_000 && $sql / $php >= .5) {
            $values['sql_dominates'] = 0;
        }

        if ($this->twigNanoseconds !== null) {
            $twig = min($php, $this->twigNanoseconds);
            $values['http_twig'] = (int) round($twig / 1_000_000);
            $values['twig_pair'] = 0;
            if ($php >= 100_000_000 && $twig / $php >= .5) {
                $values['twig_dominates'] = 0;
            }
        }

        return $values;
    }
}
