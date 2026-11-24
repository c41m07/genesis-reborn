<?php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Service\Combat\CombatSimulationView;
use App\Service\Combat\PlayerCombatSimulation;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class CombatSimulationController extends AbstractController
{
    #[Route('/simulateur', name: 'app_combat_simulation', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UniverseContext $context,
        PlayerCombatSimulation $simulation,
        GameRepository $repo,
        CombatSimulationView $simulationView,
        #[Autowire(service: 'limiter.combat_simulation')] RateLimiterFactory $limiter,
    ): Response {
        $owner = $context->membership();
        $choices = $simulation->choices($owner);
        $planets = $repo->planets($owner->universe, $owner);
        $activePlanet = $request->hasSession() ? $request->getSession()->get('selected_planet_'.$owner->id) : null;
        $planetIds = array_map(static fn ($planet) => $planet->id, $planets);
        $activePlanet = in_array($activePlanet, $planetIds, true) ? $activePlanet : ($planetIds[0] ?? null);
        $forces = [];
        $result = $error = $intel = null;
        $seed = '42';
        $ownSource = 0;
        $status = 200;
        $step = 'ships';

        try {
            if ($request->isMethod('POST')) {
                if (!$this->isCsrfTokenValid('combat_simulation', $request->request->get('_token'))) {
                    throw $this->createAccessDeniedException('Formulaire expiré.');
                }
                $submitted = $request->request;
                $forces = $submitted->all('forces');
                $seed = $submitted->getString('seed');
                $step = $submitted->getString('wizard_step');
                $step = in_array($step, ['ships', 'defenses', 'research', 'simulation'], true) ? $step : 'ships';
                $ownSource = $submitted->getInt('own_source');
                $reportId = $submitted->getInt('report');
                $action = $submitted->getString('wizard_action', 'simulate');

                // Les imports ne calculent rien, ne sauvegardent rien et préservent l'autre camp.
                if ($reportId > 0) {
                    $intel = $simulation->intel($owner, $reportId);
                }
                switch ($action) {
                    case 'load_own':
                        $planetId = $submitted->getInt('own_planet');
                        $loaded = $simulation->own($owner, $planetId);
                        $arrival = $forces['attacker']['arrival'] ?? null;
                        $forces['attacker'] = $loaded;
                        if ($arrival !== null) {
                            $forces['attacker']['arrival'] = $arrival;
                        }
                        $ownSource = $planetId;
                        break;
                    case 'load_intel':
                        $intel = $simulation->intel($owner, $submitted->getInt('report_choice'));
                        $forces['defender'] = $this->replaceDefender($forces, $intel['forces']);
                        break;
                    case 'manual_intel':
                        $intel = null;
                        $forces['defender'] = $this->replaceDefender($forces, []);
                        break;
                    case 'simulate':
                        if (!$limiter->create((string) $owner->player->id)->consume()->isAccepted()) {
                            throw new \DomainException('Limite de calcul atteinte. Attendez avant une nouvelle simulation.');
                        }
                        if ($submitted->get('assumptions') !== '1') {
                            throw new \DomainException('Confirmez les hypothèses avant de simuler.');
                        }
                        $number = filter_var($seed, FILTER_VALIDATE_INT);
                        if ($number === false) {
                            throw new \DomainException('Graine invalide.');
                        }
                        $calculation = $simulation->withConfirmedUnknownAssumptions($forces, $intel, $choices);
                        $result = $simulationView->prepare($simulation->runBatch($owner, $calculation, $number), $calculation, $choices);
                        $step = 'simulation';
                        break;
                    default:
                        throw new \DomainException('Action de simulation inconnue.');
                }
            } else {
                // Les liens historiques depuis un rapport gardent leur URL et le même contrôle propriétaire.
                $reportId = $request->query->getInt('report');
                if ($reportId > 0) {
                    $intel = $simulation->intel($owner, $reportId);
                    $forces['defender'] = $intel['forces'];
                }
                $planetId = $request->query->getInt('own_planet');
                if ($planetId > 0) {
                    $forces['attacker'] = $simulation->own($owner, $planetId);
                    $ownSource = $planetId;
                }
            }
        } catch (\DomainException $e) {
            $error = $e->getMessage();
            $status = 422;
        }

        $ownSource = in_array($ownSource, $planetIds, true) ? $ownSource : 0;
        $response = $this->render('combat/simulation.html.twig', [
            'membership' => $owner,
            'choices' => $choices,
            'forces' => $forces,
            'seed' => $seed,
            'result' => $result,
            'error' => $error,
            'intel' => $intel,
            'sources' => $intel ? $simulation->provenance($owner, $forces, $intel) : [],
            'report_options' => $repo->recentEspionageReportOptions($owner),
            'planets' => $planets,
            'active_planet' => $activePlanet,
            'own_source' => $ownSource,
            'step' => $step,
            'max_units' => PlayerCombatSimulation::MAX_UNITS,
        ], new Response(status: $status));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function replaceDefender(array $forces, array $replacement): array
    {
        $arrival = $forces['defender']['arrival'] ?? null;
        if ($arrival !== null) {
            $replacement['arrival'] = $arrival;
        }

        return $replacement;
    }
}
