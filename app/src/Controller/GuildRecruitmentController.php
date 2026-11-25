<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Player\DiplomacyService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class GuildRecruitmentController extends AbstractController
{
    #[Route('/guildes/{id}/candidater', name: 'app_guild_apply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function apply(int $id, Request $request, UniverseContext $context, DiplomacyService $diplomacy): Response
    {
        $this->requireToken($request, 'guild_apply_'.$id);
        try {
            $diplomacy->applyToGuild($context->membership(), $id);
            $this->addFlash('success', 'Candidature en attente de validation par la guilde.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_guild_public_profile', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/joueurs/{id}/inviter-guilde', name: 'app_guild_invite', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function invite(int $id, Request $request, UniverseContext $context, DiplomacyService $diplomacy): Response
    {
        $this->requireToken($request, 'guild_invite_'.$id);
        try {
            $diplomacy->inviteToGuild($context->membership(), $id);
            $this->addFlash('success', 'Invitation envoyée. Le commandant doit l’accepter.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_player_public_profile', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/guildes/recrutement/{id}/{answer}', name: 'app_guild_recruitment_answer',
        requirements: ['id' => '\d+', 'answer' => 'accept|decline|withdraw'], methods: ['POST'])]
    public function answer(int $id, string $answer, Request $request, UniverseContext $context, DiplomacyService $diplomacy): Response
    {
        $this->requireToken($request, 'guild_recruitment_'.$id.'_'.$answer);
        try {
            $diplomacy->answerRecruitment($context->membership(), $id, $answer);
            $this->addFlash('success', match ($answer) {
                'accept' => 'Demande acceptée : l’adhésion est enregistrée.',
                'decline' => 'Demande refusée.',
                'withdraw' => 'Demande retirée.',
            });
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_guilds', [], Response::HTTP_SEE_OTHER);
    }

    private function requireToken(Request $request, string $action): void
    {
        if (!$this->isCsrfTokenValid($action, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
    }
}
