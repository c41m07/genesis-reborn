<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Guild, GuildHallBuilding, PlayerUniverse};
use App\Repository\{GameRepository, GuildHallRepository};
use App\Service\Guild\{GuildHallBuildingOrders, GuildHallConvoyDeparture, GuildHallConvoyView, GuildHallFunding, GuildHallService, GuildHallWarehouseView};
use App\Service\Player\{DiplomacyAccessPolicy, RelationResolver, VacationCalendar};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class GuildHallController extends AbstractController
{
    public function __construct(
        private readonly UniverseContext $context,
        private readonly RelationResolver $relations,
        private readonly GuildHallFunding $funding,
        private readonly GuildHallRepository $halls,
    ) {
    }

    #[Route('/guildes/hall', name: 'app_guild_hall', methods: ['GET'])]
    public function show(
        Request $request,
        GameRepository $game,
        DiplomacyAccessPolicy $policy,
        VacationCalendar $vacations,
        GuildHallWarehouseView $warehouse,
        GuildHallConvoyView $convoy,
    ): Response
    {
        [$actor, $guild, $target] = $this->contextForHall();
        $hall = $this->halls->forGuild($guild);
        $target = $hall?->requiredStrength ?? $target;
        $remaining = $target - ($hall?->strength ?? 0);
        $mayAct = !$vacations->isActive($actor);
        $requiredDonors = $hall?->requiredDonors ?? $this->funding->requiredDonors;
        $minimum = $hall?->minimumDonorStrength ?? $this->funding->minimumDonorStrength;
        $qualified = $hall ? $this->halls->qualifiedDonors($hall) : 0;
        $roster = $this->halls->currentRosterSize($guild, $requiredDonors);
        $personal = $hall ? ($this->halls->donor($hall, $actor)?->strength ?? 0) : 0;
        $limits = $this->funding->donationLimits($mayAct ? $remaining : 0, $personal, $qualified, $requiredDonors, $minimum);
        $maximum = $limits['maximum'];
        $mayPlace = $mayAct && $remaining === 0 && $qualified >= $requiredDonors && $roster >= $requiredDonors
            && $hall->solarSystem === null && $policy->canCommand($actor, $guild);
        $query = mb_substr(trim($request->query->getString('q')), 0, 80);
        $after = max(0, $request->query->getInt('after'));
        $sites = $mayPlace ? $this->halls->availableSites($actor, $query, $after) : [];

        $warehouseData = $hall?->placedAt !== null ? $warehouse->describe($hall) : null;

        return $this->render('player/guild_hall.html.twig', [
            'guild' => $guild,
            'warehouse' => $warehouseData,
            'convoy' => $mayAct && ($warehouseData['enabled'] ?? false)
                ? $convoy->describe($actor, $request->query->getInt('convoy_origin')) : null,
            'may_build_at_hall' => $mayAct && $policy->canCommand($actor, $guild),
            'hall' => $hall,
            'required' => $target,
            'remaining' => $remaining,
            'required_donors' => $requiredDonors,
            'minimum_donor_strength' => $minimum,
            'qualified_donors' => $qualified,
            'current_roster' => $roster,
            'personal' => $personal,
            'personal_remaining' => max(0, $minimum - $personal),
            'maximum' => $maximum,
            'ordinary_limit' => $limits['ordinary'],
            'qualifies_from' => $limits['qualifiesFrom'],
            'resource_fields' => $this->funding->fields($maximum),
            'may_act' => $mayAct,
            'may_place' => $mayPlace,
            'colonies' => $maximum > 0 ? $game->planets($actor->universe, $actor) : [],
            'search' => $query,
            'after' => $after,
            'sites' => array_slice($sites, 0, GuildHallRepository::SITE_PAGE_SIZE),
            'next_site' => count($sites) > GuildHallRepository::SITE_PAGE_SIZE ? $sites[GuildHallRepository::SITE_PAGE_SIZE - 1]->id : null,
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/guildes/hall/donner', name: 'app_guild_hall_contribute', methods: ['POST'])]
    #[Route('/guildes/hall/placer', name: 'app_guild_hall_place', methods: ['POST'])]
    public function submit(Request $request, GuildHallService $service): Response
    {
        [$actor, $guild] = $this->contextForHall();
        $place = $request->attributes->get('_route') === 'app_guild_hall_place';
        $action = $place ? 'place' : 'donate';
        if (!$this->isCsrfTokenValid('guild_hall_'.$action.'_'.$guild->id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez le Hall.');
        }
        try {
            $revision = $request->request->getInt('revision', -1);
            if ($place) {
                if ($request->request->getString('confirm') !== '1') {
                    throw new \DomainException('Confirmez l’emplacement avant de placer le Hall.');
                }
                $service->place($actor, $guild->id, $request->request->getInt('system_id'), $revision);
                $this->addFlash('success', 'Votre Hall est installé dans le système choisi.');
            } else {
                $service->contribute($actor, $guild->id, $request->request->getInt('planet_id'), $revision, $request->request->all('resources'));
                $this->addFlash('success', 'Votre don a été ajouté à la construction du Hall.');
            }
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_guild_hall', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/guildes/hall/entrepot/construire', name: 'app_guild_hall_build_warehouse', methods: ['POST'])]
    public function buildWarehouse(Request $request, GuildHallBuildingOrders $orders): Response
    {
        [$actor, $guild] = $this->contextForHall();
        $hallId = $request->request->getInt('hall_id');
        if (!$this->isCsrfTokenValid('guild_hall_build_warehouse_'.$guild->id.'_'.$hallId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez le Hall.');
        }
        try {
            if ($request->request->getString('confirm') !== '1') {
                throw new \DomainException('Confirmez la dépense des matériaux de guilde.');
            }
            $orders->start($actor, $guild->id, $hallId, GuildHallBuilding::WAREHOUSE, $request->request->getInt('level', -1));
            $this->addFlash('success', 'La construction de l’entrepôt est lancée.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_guild_hall', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/guildes/hall/convoi', name: 'app_guild_hall_send_convoy', methods: ['POST'])]
    public function sendConvoy(Request $request, GuildHallConvoyDeparture $departure): Response
    {
        [$actor, $guild] = $this->contextForHall();
        $hallId = $request->request->getInt('hall_id');
        if (!$this->isCsrfTokenValid('guild_hall_convoy_'.$guild->id.'_'.$hallId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré. Rouvrez le Hall.');
        }
        $origin = $request->request->getInt('origin');
        try {
            if ($request->request->getString('confirm') !== '1') {
                throw new \DomainException('Confirmez le dépôt au Hall de guilde.');
            }
            $ships = [];
            foreach ($request->request->all('ships') as $slug => $ranks) {
                if (!is_array($ranks)) {
                    throw new \DomainException('Composition du convoi invalide.');
                }
                $ships[$slug] = array_map(self::convoyQuantity(...), $ranks);
            }
            $freight = array_map(self::convoyQuantity(...), $request->request->all('cargo'));
            $speed = $request->request->getInt('speed', 100);
            if ($speed < 10 || $speed > 100 || $speed % 10 !== 0) {
                throw new \DomainException('Choisissez une vitesse entre 10 et 100 %.');
            }

            $receipt = $departure->send($actor, $guild->id, $hallId, $origin, $ships, $speed / 100, $freight, $request->request->getString('request_key'));
            $this->addFlash('success', $receipt !== null
                ? 'Convoi envoyé. Le carburant aller-retour est réservé.'
                : 'Ce convoi a déjà été envoyé.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_guild_hall', ['convoy_origin' => $origin], Response::HTTP_SEE_OTHER);
    }

    /** Une saisie non entière ou hors bornes n’est jamais arrondie ni tronquée. */
    private static function convoyQuantity(mixed $amount): int
    {
        if ($amount === '' || $amount === '0') {
            return 0;
        }
        if (!is_string($amount) || !preg_match('/^[1-9][0-9]{0,8}$/D', $amount) || (int) $amount > 100_000_000) {
            throw new \DomainException('Saisissez une quantité entière entre 0 et 100 000 000.');
        }

        return (int) $amount;
    }

    /** @return array{PlayerUniverse, Guild, int} */
    private function contextForHall(): array
    {
        $actor = $this->context->membership();
        $guild = $this->relations->guild($actor);
        $target = $this->funding->target();
        if (!$guild || $target === null || !$this->funding->availableIn($actor->universe)) {
            throw $this->createNotFoundException('Hall de guilde indisponible.');
        }

        return [$actor, $guild, $target];
    }
}
