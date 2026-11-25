<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsMiddleware]
final class SqlPerformanceMiddleware implements Middleware
{
    public function __construct(
        private readonly RequestStack $requests,
        #[Autowire('%env(int:APP_PERFORMANCE_SAMPLE_PERCENT)%')]
        private readonly int $samplePercent,
    ) {
    }

    public function wrap(Driver $driver): Driver
    {
        if ($this->samplePercent < 1) {
            return $driver;
        }

        return new class ($driver, $this->requests) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly RequestStack $requests)
            {
                parent::__construct($driver);
            }

            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);

                return new class ($connection, $this->requests) extends AbstractConnectionMiddleware {
                    public function __construct(Connection $connection, private readonly RequestStack $requests)
                    {
                        parent::__construct($connection);
                    }

                    public function query(string $sql): Result
                    {
                        $profile = $this->profile();

                        return $profile === null ? parent::query($sql) : $profile->duringSql(fn () => parent::query($sql));
                    }

                    public function exec(string $sql): int|string
                    {
                        $profile = $this->profile();

                        return $profile === null ? parent::exec($sql) : $profile->duringSql(fn () => parent::exec($sql));
                    }

                    public function prepare(string $sql): Statement
                    {
                        $statement = parent::prepare($sql);
                        $profile = $this->profile();
                        if ($profile === null) {
                            return $statement;
                        }

                        // Mesurer l’exécution et ne jamais lire ni conserver le texte SQL ou ses paramètres.
                        return new class ($statement, $profile) extends AbstractStatementMiddleware {
                            public function __construct(Statement $statement, private readonly RequestPerformance $profile)
                            {
                                parent::__construct($statement);
                            }

                            public function execute(): Result
                            {
                                return $this->profile->duringSql(fn () => parent::execute());
                            }
                        };
                    }

                    private function profile(): ?RequestPerformance
                    {
                        $value = $this->requests->getMainRequest()?->attributes->get(RequestPerformance::ATTRIBUTE);

                        return $value instanceof RequestPerformance ? $value : null;
                    }
                };
            }
        };
    }
}
