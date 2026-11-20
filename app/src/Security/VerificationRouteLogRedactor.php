<?php

namespace App\Security;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\{AsDecorator, AutowireDecorated};

#[AsDecorator(decorates: 'logger')]
final class VerificationRouteLogRedactor extends AbstractLogger
{
    public function __construct(#[AutowireDecorated] private LoggerInterface $inner)
    {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // RouterListener inclut le jeton dans deux champs, avant le
        // contrôleur. Garder méthode et nom de route, jamais l'URL ni ses
        // paramètres. Ce filtre ne régit pas les journaux des proxies.
        if (($context['route'] ?? null) === 'app_email_verify') {
            unset($context['route_parameters'], $context['request_uri']);
        }
        $this->inner->log($level, $message, $context);
    }
}
