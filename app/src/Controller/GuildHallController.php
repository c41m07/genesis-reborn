<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Guild, PlayerUniverse};
use App\Repository\{GameRepository, GuildHallRepository};
use App\Service\Guild\{GuildHallFunding, GuildHallService};
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
    public function show(Request $request, GameRepository $game, DiplomacyAccessPolicy $policy, VacationCalendar $vacations): Response
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

        return $this->render('player/guild_hall.html.twig', [
            'guild' => $guild,
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
