<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Player;
use App\Service\Admin\AdminAccessIncidentLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

final class AdminAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private readonly AdminAccessIncidentLogger $incidentLogger,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if ($request->getPathInfo() !== '/admin' && !str_starts_with($request->getPathInfo(), '/admin/')) {
            return null;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        $this->incidentLogger->log($request, $user instanceof Player ? $user : null);
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('error', 'Accès refusé. Vérifiez le compte utilisé ou rechargez le formulaire depuis sa page d’origine. La tentative a été consignée.');
        }

        return new RedirectResponse($this->urlGenerator->generate('app_access_denied'));
    }
}
