<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, Galaxy, GuildHallBuilding, PlayerUniverse};
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Le Relais ajoute des contacts locaux, sans modifier les droits du radar ordinaire. */
final readonly class GuildRelayTraffic
{
    private const CANDIDATE_LIMIT = 120;
    private const DISPLAY_LIMIT = 24;

    public function __construct(
        private EntityManagerInterface $em,
        private GameCatalog $catalog,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<int> $visibleIds Flottes déjà visibles avec un trajet précis sur la carte.
     * @return array{systemId:int,contacts:list<array>,limited:bool}|null
     */
    public function observe(PlayerUniverse $viewer, ?Galaxy $galaxy = null, array $visibleIds = []): ?array
    {
        $now = $this->clock->now();
        if (!$viewer->isPlayerControlled() || $viewer->player->lockedAt !== null
            || $viewer->id === null || !$viewer->universe->isAccessible($now)
            || ($galaxy !== null && $galaxy->universe->id !== $viewer->universe->id)
            || !$this->catalog->forUniverse($viewer->universe)['features']['guilds_enabled']) {
            return null;
        }

        // Repartir de l’adhésion actuelle et du bâtiment effectivement terminé.
        // Un marqueur public du Hall ou le début d’un chantier ne donne aucun accès.
        $site = $this->em->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT site.id, site.galaxy_id
                FROM guild_member roster
                JOIN guild g ON g.id = roster.guild_id AND g.archived_at IS NULL
                JOIN guild_hall hall ON hall.guild_id = g.id AND hall.placed_at IS NOT NULL
                JOIN guild_hall_building relay ON relay.hall_id = hall.id AND relay.slug = :relay AND relay.level > 0
                JOIN solar_system site ON site.id = hall.solar_system_id
                JOIN galaxy area ON area.id = site.galaxy_id AND area.universe_id = g.universe_id
                WHERE roster.player_universe_id = :viewer AND g.universe_id = :universe
                  AND (EXISTS (SELECT 1 FROM player_discovery sight WHERE sight.player_universe_id = roster.player_universe_id AND sight.solar_system_id = site.id)
                       OR EXISTS (SELECT 1 FROM planet colony WHERE colony.owner_id = roster.player_universe_id AND colony.solar_system_id = site.id))
                SQL,
            ['relay' => GuildHallBuilding::RELAY, 'viewer' => $viewer->id, 'universe' => $viewer->universe->id],
        );
        if ($site === false || ($galaxy !== null && (int) $site['galaxy_id'] !== $galaxy->id)) {
            return null;
        }
        $system = (int) $site['id'];

        // Limiter la lecture aux départs, destinations et retours de ce système ;
        // ne jamais scanner l’ensemble des flottes actives de l’univers.
        $builder = $this->em->createQueryBuilder()->select('flight', 'landing', 'landingSystem')
            ->from(Fleet::class, 'flight')->join('flight.playerUniverse', 'pilot')
            ->join('flight.homePlanet', 'home')->leftJoin('flight.destination', 'target')
            ->leftJoin('flight.returnPlanet', 'landing')->leftJoin('landing.solarSystem', 'landingSystem')
            ->where('pilot.universe = :universe AND flight.status IN (:states) AND flight.mission IN (:missions)')
            ->andWhere('flight.departureAt <= :now AND flight.arrivalAt > :now')
            ->andWhere('(IDENTITY(home.solarSystem) = :system OR IDENTITY(target.solarSystem) = :system
                OR IDENTITY(flight.destinationSystem) = :system OR IDENTITY(landing.solarSystem) = :system)')
            ->setParameter('universe', $viewer->universe)
            ->setParameter('states', ['traveling', 'returning'])
            ->setParameter('missions', GuildRelayProjection::MISSIONS)
            ->setParameter('system', $system)
            ->setParameter('now', $now->setTimezone(new \DateTimeZone('UTC')))
            ->orderBy('flight.arrivalAt', 'ASC')->addOrderBy('flight.id', 'ASC')
            ->setMaxResults(self::CANDIDATE_LIMIT + 1);
        $candidates = $builder->getQuery()->getResult();
        $alreadyVisible = array_fill_keys($visibleIds, true);
        $bounded = count($candidates) > self::CANDIDATE_LIMIT;
        $contacts = [];
        foreach (array_slice($candidates, 0, self::CANDIDATE_LIMIT) as $fleet) {
            if (isset($alreadyVisible[$fleet->id])) {
                continue;
            }
            $contact = GuildRelayProjection::contact($fleet, $system, $now);
            if ($contact !== null) {
                $contacts[] = $contact;
            }
        }
        usort($contacts, static fn (array $a, array $b): int => $a['until'] <=> $b['until']);

        return [
            'systemId' => $system,
            'contacts' => array_slice($contacts, 0, self::DISPLAY_LIMIT),
            'limited' => $bounded || count($contacts) > self::DISPLAY_LIMIT,
        ];
    }
}
