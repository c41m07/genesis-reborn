<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Player\FirstStep;
use App\Service\Player\FirstSteps;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
