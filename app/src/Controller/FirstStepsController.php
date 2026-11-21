<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Player\FirstStep;
use App\Service\Player\FirstSteps;
use App\Service\Player\PirateExercise;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FirstStepsController extends AbstractController
{
    #[Route('/premiers-pas', name: 'app_first_steps', methods: ['GET'])]
    public function index(UniverseContext $context, FirstSteps $firstSteps): Response
    {
        $membership = $context->membership();
        $steps = FirstStep::cases();
        $completed = $firstSteps->completed($membership);
        $next = null;
        foreach ($steps as $step) {
            if (!isset($completed[$step->value])) {
                $next = $step;
                break;
            }
        }

        return $this->render('player/first_steps.html.twig', [
            'steps' => $steps,
            'completed' => $completed,
            'next' => $next,
        ]);
    }

    #[Route('/premiers-pas/exercice-pirate', name: 'app_first_steps_exercise', methods: ['GET', 'POST'])]
    public function pirateExercise(Request $request, UniverseContext $context, FirstSteps $firstSteps, PirateExercise $exercise): Response
    {
        $membership = $context->membership();
        $tokenId = 'first-steps-pirate-exercise-'.$membership->id;
        $report = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré. Ouvrez de nouveau l’exercice.');
            }

            // Aucune composition, cible, graine ou identité n’est acceptée du formulaire.
            $report = $exercise->report(random_int(0, 0xffffffff));
        }

        $response = $this->render('player/first_steps_exercise.html.twig', [
            'membership' => $membership,
            'exerciseTokenId' => $tokenId,
            'report' => $report,
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));

        if ($report !== null) {
            $firstSteps->recordPirateExercise($membership);
        }

        return $response;
    }
}
