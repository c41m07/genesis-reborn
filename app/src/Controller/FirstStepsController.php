<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Planet, PlanetBuilding, Player, PlayerTechnology};
use App\Repository\GameRepository;
use App\Service\Player\FirstStep;
use App\Service\Player\FirstSteps;
use App\Service\Player\FirstStepsView;
use App\Service\Player\FirstStepRewards;
use App\Service\Player\PirateExercise;
use App\Service\Player\PreferencesService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FirstStepsController extends AbstractController
{
    #[Route('/premiers-pas', name: 'app_first_steps', methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, FirstStepsView $view, FirstStepRewards $rewards, GameRepository $repo): Response
    {
        $membership = $context->membership();
        $planets = $repo->planets($membership->universe, $membership);
        $selected = $request->getSession()->get('selected_planet_'.$membership->id);
        $planet = $planets[0] ?? null;
        foreach ($planets as $candidate) {
            if ($candidate->id === $selected) {
                $planet = $candidate;
                break;
            }
        }
        $levels = $planet === null ? [] : $repo->levelsByTier(PlanetBuilding::class, ['planet' => $planet]);
        $technologies = $repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);

        return $this->render('player/first_steps.html.twig', [
            'training' => $view->progress($membership, $levels, $technologies),
            'trainingMembership' => $membership,
            'trainingPlanet' => $planet,
            'trainingRewards' => $rewards->available($membership),
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/premiers-pas/preference', name: 'app_first_steps_preference', methods: ['POST'])]
    public function guidePreference(Request $request, PreferencesService $preferences): Response
    {
        $player = $this->getUser();
        $setting = $request->request->getString('guide_enabled');
        if (!$player instanceof Player || !in_array($setting, ['0', '1'], true)
            || !$this->isCsrfTokenValid('first-steps-preference', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez le guide.');
        }

        $enabled = $setting === '1';
        $preferences->setFirstStepsGuide($player, $enabled);
        $this->addFlash('success', $enabled
            ? 'Guide réactivé. Vous retrouverez vos étapes déjà acquises.'
            : 'Guide désactivé. Vous pouvez le réactiver dans Préférences sans perdre votre progression.');

        return $this->redirectToRoute('app_preferences', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/premiers-pas/recuperer', name: 'app_first_steps_claim', methods: ['POST'])]
    public function claim(Request $request, UniverseContext $context, FirstStepRewards $rewards, GameRepository $repo): Response
    {
        $membership = $context->membership();
        $planet = $repo->find(Planet::class, $request->request->getInt('planet'));
        $step = FirstStep::tryFrom($request->request->getString('step'));
        if (!$planet instanceof Planet || $planet->owner?->id !== $membership->id || $step === null
            || !$this->isCsrfTokenValid('first-steps-reward-'.$membership->id.'-'.$planet->id.'-'.$step->value, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez le guide.');
        }

        try {
            $state = $rewards->claim($membership, $planet, $step);
            match ($state) {
                'claimed' => $this->addFlash('success', 'Remboursement ajouté aux stocks de '.$planet->name.'.'),
                'full' => $this->addFlash('error', 'Faites de la place en métal et nanos : le lot reste en attente et rien n’a été perdu.'),
                default => $this->addFlash('error', 'Ce remboursement n’est pas disponible dans cet empire.'),
            };
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_first_steps', status: Response::HTTP_SEE_OTHER);
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
