<?php
namespace App\EventListener;
use App\Service\Universe\UniverseNotAccessible;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
#[AsEventListener]
final class UniverseExceptionListener
{
    public function __construct(private UrlGeneratorInterface $router) {}
    public function __invoke(ExceptionEvent $event): void
    {
        if ($event->getThrowable() instanceof UniverseNotAccessible) $event->setResponse(new RedirectResponse($this->router->generate('app_universes')));
    }
}
