<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Guild, GuildHall, GuildHallContribution, Planet, PlayerUniverse, SolarSystem};
use App\Manager\GameManager;
use App\Repository\GuildHallRepository;
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Player\{DiplomacyAccessPolicy, VacationCalendar};
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class GuildHallService
{
    public function __construct(
        private GameManager $manager,
        private EntityManagerInterface $em,
        private ActionGuard $guard,
        private ResourceService $resources,
        private VacationCalendar $vacations,
        private DiplomacyAccessPolicy $access,
        private GuildHallFunding $funding,
        private GuildHallRepository $halls,
        private ClockInterface $clock,
    ) {
    }

    /** @param array<string, mixed> $amounts */
    public function contribute(PlayerUniverse $actor, int $guildId, int $planetId, int $revision, array $amounts): GuildHall
    {
        return $this->manager->transaction(function () use ($actor, $guildId, $planetId, $revision, $amounts): GuildHall {
            $guild = $this->lockGuild($actor, $guildId);
            $hall = $this->halls->lockForGuild($guild);
            $this->assertRevision($hall, $revision);
            if ($hall !== null && $hall->strength === $hall->requiredStrength) {
                throw new \DomainException('La construction du Hall est déjà financée.');
            }

            $planet = $this->em->find(Planet::class, $planetId);
            if (!$planet instanceof Planet) {
                throw new \DomainException('Colonie inaccessible.');
            }
            $this->guard->lock($actor, $planet, currentReads: true);

            $target = $hall?->requiredStrength ?? $this->funding->target();
            $requiredDonors = $hall?->requiredDonors ?? $this->funding->requiredDonors;
            $minimumDonorStrength = $hall?->minimumDonorStrength ?? $this->funding->minimumDonorStrength;
            $contribution = $hall ? $this->halls->donor($hall, $actor, lock: true) : null;
            $limits = $this->funding->donationLimits(
                $target - ($hall?->strength ?? 0),
                $contribution?->strength ?? 0,
                $hall ? $this->halls->qualifiedDonors($hall, lock: true) : 0,
                $requiredDonors,
                $minimumDonorStrength,
            );
            $donation = $this->funding->donation($amounts, $limits);
            // Même transaction que la progression : ni double débit après un renvoi
            // du formulaire, ni construction créditée si le stock n'a pas été payé.
            $this->resources->consume($planet, $donation['cost'], lockStocks: true);
            if ($hall === null) {
                $hall = new GuildHall();
                $hall->guild = $guild;
                $hall->requiredStrength = $target;
                $hall->requiredDonors = $requiredDonors;
                $hall->minimumDonorStrength = $minimumDonorStrength;
                $hall->startedAt = $this->clock->now();
                $this->manager->save($hall);
            }
            if ($contribution === null) {
                $contribution = new GuildHallContribution();
                $contribution->hall = $hall;
                $contribution->memberId = $actor->id;
                $this->manager->save($contribution);
            }
            $contribution->strength += $donation['strength'];
            $hall->strength += $donation['strength'];
            ++$hall->revision;

            return $hall;
        });
    }

    public function place(PlayerUniverse $actor, int $guildId, int $systemId, int $revision): GuildHall
    {
        return $this->manager->transaction(function () use ($actor, $guildId, $systemId, $revision): GuildHall {
            $guild = $this->lockGuild($actor, $guildId);
            if (!$this->access->canCommand($actor, $guild)) {
                throw new \DomainException('Seul le commandant actuel peut installer le Hall.');
            }

            $hall = $this->halls->lockForGuild($guild);
            $this->assertRevision($hall, $revision);
            if ($hall === null || $hall->strength !== $hall->requiredStrength || $hall->solarSystem !== null) {
                throw new \DomainException('Il faut un Hall entièrement financé et non installé.');
            }
            // Les reçus acquis conservent leur valeur après un départ, mais
            // une guilde réduite ne peut pas installer un Hall en solitaire.
            if ($this->halls->qualifiedDonors($hall, lock: true) < $hall->requiredDonors) {
                throw new \DomainException('Le nombre de donateurs ayant atteint le minimum personnel est insuffisant.');
            }
            if ($this->halls->currentRosterSize($guild, $hall->requiredDonors) < $hall->requiredDonors) {
                throw new \DomainException('Il manque des membres actuels pour installer le Hall.');
            }
            $system = $this->em->find(SolarSystem::class, $systemId);
            if (!$system instanceof SolarSystem || $system->galaxy->universe->id !== $guild->universe->id) {
                throw new \DomainException('Système inaccessible.');
            }
            $this->manager->lockCurrent($system);
            $connection = $this->em->getConnection();
            if (!$connection->fetchOne(
                'SELECT id FROM player_discovery WHERE solar_system_id = ? AND player_universe_id = ? FOR UPDATE',
                [$system->id, $actor->id],
            )) {
                throw new \DomainException('Système inaccessible.');
            }
            if ($this->halls->isClaimed($system->id) || $connection->fetchOne(
                'SELECT id FROM planet WHERE solar_system_id = ? AND position = 0 FOR UPDATE',
                [$system->id],
            )) {
                throw new \DomainException('La position 0 de ce système est déjà occupée.');
            }

            // La position 0 reste une projection du Hall. Aucune planète ni flotte
            // n'est créée : la revendication ne donne aucun droit ni bonus de jeu.
            $hall->solarSystem = $system;
            $hall->placedAt = $this->clock->now();
            ++$hall->revision;

            return $hall;
        });
    }

    private function lockGuild(PlayerUniverse $actor, int $guildId): Guild
    {
        if ($this->funding->target() === null) {
            throw new \DomainException('La construction du Hall n’est pas ouverte.');
        }

        // Partager les verrous participation → univers avec les départs de guilde,
        // le changement de commandant et les actions économiques sur une colonie.
        $this->guard->lockMembership($actor, currentReads: true);
        if ($this->vacations->isActive($actor) || !$this->funding->availableIn($actor->universe)) {
            throw new \DomainException('Le Hall est indisponible dans ce contexte.');
        }
        $conn = $this->em->getConnection();
        try {
            // Comme pour les quotidiennes, ne pas attendre le compte si une sélection
            // d'univers le tient déjà dans son propre ordre de verrouillage.
            $participation = $conn->fetchOne('SELECT current_player_universe_id FROM player
                WHERE id = ? AND locked_at IS NULL AND email_verified_at <= ? FOR UPDATE NOWAIT',
                [$actor->player?->id, $this->clock->now()->format('Y-m-d H:i:s')]);
        } catch (LockWaitTimeoutException) {
            throw new \DomainException('Le compte est occupé. Rechargez la page avant de réessayer.');
        }
        if ((int) $participation !== $actor->id) {
            throw new \DomainException('Cette participation n’est plus active. Rechargez la page.');
        }
        $memberGuild = $conn->fetchOne('SELECT guild_id FROM guild_member WHERE player_universe_id = ? FOR UPDATE', [$actor->id]);
        if ($guildId <= 0 || $guildId !== (int) $memberGuild) {
            throw new \DomainException('Guilde inaccessible.');
        }
        $guild = $this->em->find(Guild::class, $guildId);
        if (!$guild instanceof Guild) {
            throw new \DomainException('Guilde inaccessible.');
        }
        $this->manager->lockCurrent($guild);
        if ($guild->archivedAt !== null || $guild->universe->id !== $actor->universe->id) {
            throw new \DomainException('Guilde inaccessible.');
        }

        return $guild;
    }

    private function assertRevision(?GuildHall $hall, int $revision): void
    {
        if ($revision !== ($hall?->revision ?? 0)) {
            throw new \DomainException('Le Hall a changé. Rechargez la page avant de réessayer.');
        }
    }
}
