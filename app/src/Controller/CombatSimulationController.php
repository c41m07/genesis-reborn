<?php

namespace App\Controller;

use App\Entity\PlayerUniverse;
use App\Repository\GameRepository;
use App\Service\Combat\CombatSimulationView;
use App\Service\Combat\PlayerCombatSimulation;
use App\Service\Combat\PlayerCombatSimulationQueue;
use App\Service\PirateCampaign\PirateReconReportReader;
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
        PlayerCombatSimulationQueue $simulationQueue,
        PirateReconReportReader $pirateReports,
        #[Autowire(service: 'limiter.combat_simulation')] RateLimiterFactory $limiter,
    ): Response {
        $owner = $context->membership();
        $choices = $simulation->choices($owner);
        $planets = $repo->planets($owner->universe, $owner);
        $activePlanet = $request->hasSession() ? $request->getSession()->get('selected_planet_'.$owner->id) : null;
        $planetIds = array_map(static fn ($planet) => $planet->id, $planets);
        $activePlanet = in_array($activePlanet, $planetIds, true) ? $activePlanet : ($planetIds[0] ?? null);
        $forces = [];
        $result = $error = $intel = $pirateIntel = $simulationJob = null;
        $seed = '42';
        $ownSource = 0;
        $ownCamp = 'attacker';
        $intelCamp = 'defender';
        $garrison = [];
        $status = 200;
        $step = 'ships';

        try {
            if ($request->isMethod('POST')) {
                if (!$this->isCsrfTokenValid('combat_simulation', $request->request->get('_token'))) {
                    throw $this->createAccessDeniedException('Formulaire expiré.');
                }
                $submitted = $request->request;
                $forces = $submitted->all('forces');
                foreach ($forces as $side) {
                    if (!is_array($side)) throw new \DomainException('Forces invalides.');
                }
                $seed = $submitted->getString('seed');
                $step = $submitted->getString('wizard_step');
                $step = in_array($step, ['ships', 'defenses', 'research', 'simulation'], true) ? $step : 'ships';
                $ownSource = $submitted->getInt('own_source');
                $ownCamp = $submitted->getString('own_camp') === 'defender' ? 'defender' : 'attacker';
                $intelCamp = $ownCamp === 'attacker' ? 'defender' : 'attacker';
                $saved = json_decode($submitted->getString('garrison'), true, 12);
                $garrison = is_array($saved) ? $saved : [];
                $reportId = $submitted->getInt('report');
                $pirateReportId = $submitted->getInt('pirate_report');
                $action = $submitted->getString('wizard_action', 'simulate');

                // Les imports ne calculent rien, ne sauvegardent rien et préservent l'autre camp.
                if ($pirateReportId > 0 && !in_array($action, ['manual_intel', 'load_intel'], true)) {
                    $ownCamp = 'attacker';
                    $intelCamp = 'defender';
                    $pirateIntel = $this->pirateIntel($pirateReports, $owner, $pirateReportId);
                    $forces['defender'] = $pirateIntel['forces'];
                    if (is_array($forces['attacker'] ?? null)) {
                        $forces['attacker']['arrival'] = 1;
                    }
                } elseif ($reportId > 0) {
                    $intel = $simulation->intel($owner, $reportId);
                }
                switch ($action) {
                    case 'swap':
                        if ($pirateIntel !== null) throw new \DomainException('Le camp pirate est fixé par le rapport.');
                        [$forces['attacker'], $forces['defender']] = [$forces['defender'] ?? [], $forces['attacker'] ?? []];
                        // Les défenses et unités fixes suivent leur propriétaire lors d’une double inversion.
                        $forces['defender'] = array_replace_recursive($forces['defender'], $garrison);
                        $ownCamp = $intelCamp;
                        $intelCamp = $ownCamp === 'defender' ? 'attacker' : 'defender';
                        $garrison = $this->extractGarrison($forces['attacker'], $choices);
                        break;
                    case 'load_own':
                        $planetId = $submitted->getInt('own_planet');
                        $loaded = $simulation->own($owner, $planetId, true);
                        $arrival = $forces[$ownCamp]['arrival'] ?? null;
                        $forces[$ownCamp] = $loaded;
                        if ($pirateIntel === null && $arrival !== null) {
                            $forces[$ownCamp]['arrival'] = $arrival;
                        }
                        if ($ownCamp === 'attacker') $garrison = $this->extractGarrison($forces[$ownCamp], $choices);
                        $ownSource = $planetId;
                        break;
                    case 'load_intel':
                        $intel = $simulation->intel($owner, $submitted->getInt('report_choice'));
                        $forces[$intelCamp] = $this->replaceIntel($forces[$intelCamp] ?? [], $intel['forces']);
                        if ($intelCamp === 'attacker') $garrison = $this->extractGarrison($forces[$intelCamp], $choices);
                        break;
                    case 'manual_intel':
                        $intel = null;
                        if ($intelCamp === 'attacker') $garrison = [];
                        $forces[$intelCamp] = $this->replaceIntel($forces[$intelCamp] ?? [], []);
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
                        $calculation = $simulation->withConfirmedUnknownAssumptions($forces, $intel, $choices, $intelCamp);
                        $prepared = $simulation->prepare($owner, $calculation, $number, $pirateIntel['scenario'] ?? null);
                        if (!$prepared['inline']) {
                            $job = $simulationQueue->enqueue(
                                $owner,
                                $prepared,
                                $simulationView->initialRows($prepared['forces'], $choices),
                                $number,
                                $intel['id'] ?? 0,
                                in_array($ownSource, $planetIds, true) ? $ownSource : 0,
                                $pirateIntel['id'] ?? 0,
                                $ownCamp,
                            );
                            $response = $this->redirectToRoute('app_combat_simulation', ['job' => $job->publicKey, '_fragment' => 'simulation-result'], Response::HTTP_SEE_OTHER);
                            $response->headers->set('Cache-Control', 'private, no-store');

                            return $response;
                        }
                        $result = $simulationView->prepare($simulation->runPreparedInline($prepared, $number), $prepared['forces'], $choices);
                        $step = 'simulation';
                        break;
                    default:
                        throw new \DomainException('Action de simulation inconnue.');
                }
            } elseif ($request->query->has('job')) {
                $job = $simulationQueue->visible($owner, $request->query->getString('job'));
                if ($job === null) {
                    throw $this->createNotFoundException('Simulation inaccessible ou expirée.');
                }
                $step = 'simulation';
                $forces = $job->scenario['prepared']['forces'];
                $seed = (string) $job->scenario['seed'];
                $ownSource = $job->scenario['own_source'];
                $ownCamp = $job->scenario['own_camp'] ?? 'attacker';
                $intelCamp = $ownCamp === 'attacker' ? 'defender' : 'attacker';
                if (($job->scenario['pirate_report'] ?? 0) > 0) {
                    $pirateIntel = $pirateReports->read($owner, $job->scenario['pirate_report'], forSimulation: true);
                    if ($pirateIntel === null) {
                        throw $this->createNotFoundException('Simulation inaccessible ou expirée.');
                    }
                } elseif ($job->scenario['report'] > 0) {
                    try {
                        $intel = $simulation->intel($owner, $job->scenario['report']);
                    } catch (\DomainException) {
                        // Le rapport source peut avoir disparu ; les hypothèses déjà confirmées restent lisibles.
                    }
                }
                $simulationJob = ['key' => $job->publicKey, 'status' => $job->status, 'completed' => count($job->trials)];
                if ($job->status === 'done') {
                    $result = $simulation->batch($job->trials);
                }
            } else {
                // Les liens historiques depuis un rapport gardent leur URL et le même contrôle propriétaire.
                $reportId = $request->query->getInt('report');
                $pirateReportId = $request->query->getInt('pirate_report');
                if ($pirateReportId > 0) {
                    $pirateIntel = $this->pirateIntel($pirateReports, $owner, $pirateReportId);
                    $forces['defender'] = $pirateIntel['forces'];
                } elseif ($reportId > 0) {
                    $intel = $simulation->intel($owner, $reportId);
                    $forces['defender'] = $intel['forces'];
                }
                $planetId = $request->query->getInt('own_planet');
                if ($planetId > 0) {
                    $forces['attacker'] = $simulation->own($owner, $planetId, true);
                    $garrison = $this->extractGarrison($forces['attacker'], $choices);
                    $ownSource = $planetId;
                }
            }
        } catch (\DomainException $e) {
            $error = $e->getMessage();
            $status = 422;
        }

        $ownSource = in_array($ownSource, $planetIds, true) ? $ownSource : 0;
        if ($pirateIntel !== null) {
            // Le modèle de présentation ne contient pas les profils internes ni le scénario de calcul.
            unset($pirateIntel['scenario'], $pirateIntel['forces']);
        }
        $response = $this->render('combat/simulation.html.twig', [
            'membership' => $owner,
            'choices' => $choices,
            'forces' => $forces,
            'seed' => $seed,
            'result' => $result,
            'error' => $error,
            'intel' => $intel,
            'pirate_intel' => $pirateIntel,
            'sources' => $intel ? $simulation->provenance($owner, $forces, $intel, $intelCamp) : [],
            'report_options' => $repo->recentEspionageReportOptions($owner),
            'planets' => $planets,
            'active_planet' => $activePlanet,
            'own_source' => $ownSource,
            'own_camp' => $ownCamp,
            'intel_camp' => $intelCamp,
            'garrison' => $garrison,
            'step' => $step,
            'fleet_limit' => $simulation->fleetLimit($owner),
            'defense_limit' => PlayerCombatSimulation::MAX_DEFENSE_QUANTITY,
            'simulation_job' => $simulationJob,
        ], new Response(status: $status));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function pirateIntel(PirateReconReportReader $reader, PlayerUniverse $owner, int $id): array
    {
        return $reader->read($owner, $id, forSimulation: true)
            ?? throw new \DomainException('Rapport pirate inaccessible ou non importable.');
    }

    private function extractGarrison(array &$forces, array $choices): array
    {
        $garrison = array_intersect_key($forces, ['defenses' => true, 'fortress' => true]);
        unset($forces['defenses'], $forces['fortress']);
        foreach ($choices['ships'] as $slug => $definition) {
            if (isset($definition['economic_unit'], $forces['ships'][$slug])) {
                $garrison['ships'][$slug] = $forces['ships'][$slug];
                unset($forces['ships'][$slug]);
            }
        }
        return $garrison;
    }

    private function replaceIntel(array $forces, array $replacement): array
    {
        $arrival = $forces['arrival'] ?? null;
        if ($arrival !== null) {
            $replacement['arrival'] = $arrival;
        }

        return $replacement;
    }
}
