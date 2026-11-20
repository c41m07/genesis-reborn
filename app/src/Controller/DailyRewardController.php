<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Planet, Player, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Player\DailyRewardService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class DailyRewardController extends AbstractController
{
    public function __construct(
        private GameRepository $repo,
        private UniverseContext $context,
        private DailyRewardService $rewards,
    ) {}

    #[Route('/recompense-quotidienne', name: 'app_daily_reward', methods: ['GET'])]
    public function show(Request $request): Response
    {
        $membership = $this->context->membership();
        $planet = $request->query->has('colony')
            ? $this->ownedPlanet($membership, $request->query->getInt('colony'))
            : ($this->repo->planets($membership->universe, $membership)[0] ?? null);
        $status = $this->rewards->status($this->player());

        return $this->render('player/daily_reward.html.twig', [
            'planet' => $planet,
            'universe' => $membership->universe,
            'status' => $status,
            'tokenId' => $planet === null ? null : $this->tokenId($membership, $planet, $status['date']),
        ])->setPrivate()->setMaxAge(0);
    }

    #[Route('/recompense-quotidienne', name: 'app_daily_reward_claim', methods: ['POST'])]
    public function claim(Request $request): Response
    {
        $membership = $this->context->membership();
        if ($request->request->getInt('membership') !== $membership->id) {
            $this->addFlash('error', 'Votre univers actif a changé. Vérifiez la destination avant de récupérer le lot.');
            return $this->redirectToRoute('app_daily_reward', status: Response::HTTP_SEE_OTHER);
        }
        $planet = $this->ownedPlanet($membership, $request->request->getInt('colony'));
        $date = $request->request->getString('claim_date');
        if (!$this->isCsrfTokenValid($this->tokenId($membership, $planet, $date), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Actualisez la page avant de récupérer le lot.');
        }

        try {
            $result = $this->rewards->claim($this->player(), $membership, $planet, $date);
        } catch (\DomainException) {
            $this->addFlash('error', 'Cette planète ou cet univers ne peut plus recevoir le lot. Rien n’a été retiré.');
            return $this->redirectToRoute('app_daily_reward', status: Response::HTTP_SEE_OTHER);
        }

        $destination = ['colony' => $planet->id];
        switch ($result['state']) {
            case 'claimed':
                $this->addFlash('success', 'Jour '.$result['slot'].' récupéré : le lot est dans les stocks de '.$planet->name.'.');
                break;
            case 'already':
                $this->addFlash('success', 'Votre compte a déjà récupéré son lot de ce jour.');
                break;
            case 'full':
                $this->addFlash('error', $this->storageMessage($result['required'], $result['missing']));
                break;
            case 'busy':
                $this->addFlash('error', 'Une autre action est en cours sur votre compte. Aucun lot n’a été retiré ; réessayez.');
                break;
            default:
                $this->addFlash('error', 'Le jour ou l’univers actif a changé. Vérifiez le lot et sa destination.');
                $destination = [];
                break;
        }

        return $this->redirectToRoute('app_daily_reward', $destination, Response::HTTP_SEE_OTHER);
    }

    private function player(): Player
    {
        $player = $this->getUser();
        if (!$player instanceof Player) {
            throw $this->createAccessDeniedException();
        }

        return $player;
    }

    private function ownedPlanet(PlayerUniverse $membership, int $id): Planet
    {
        $planet = $this->repo->find(Planet::class, $id);
        if (!$planet instanceof Planet || $planet->owner?->id !== $membership->id
            || $planet->solarSystem->galaxy->universe->id !== $membership->universe->id) {
            throw $this->createNotFoundException('Cette planète ne peut pas recevoir votre lot.');
        }

        return $planet;
    }

    private function tokenId(PlayerUniverse $membership, Planet $planet, string $date): string
    {
        return 'daily_reward:'.$membership->id.':'.$planet->id.':'.$date;
    }

    /** @param array<string,int> $required @param array<string,int> $missing */
    private function storageMessage(array $required, array $missing): string
    {
        $details = [];
        foreach ($missing as $resource => $amount) {
            $name = ['metal' => 'métal', 'nano' => 'nano'][$resource];
            $details[] = $name.' : libérez '.$amount.' ('.$required[$resource].' de capacité libre nécessaire au total)';
        }

        return 'Stockage insuffisant. '.implode(' ; ', $details).'. Le même lot reste récupérable : rien n’a été crédité.';
    }
}
