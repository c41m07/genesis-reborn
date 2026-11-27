<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Guild, GuildHall, GuildMember, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\{GameRepository, GuildHallRepository};
use App\Service\Colony\ActionGuard;
use App\Service\Player\{DiplomacyAccessPolicy, VacationCalendar};
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Point commun de validation et de verrouillage pour les écritures du Hall. */
final readonly class GuildHallAccess
{
    public function __construct(
        private GameManager $manager,
        private EntityManagerInterface $em,
        private ActionGuard $guard,
        private VacationCalendar $vacations,
        private GuildHallFunding $funding,
        private GuildHallRepository $halls,
        private GameRepository $repository,
        private DiplomacyAccessPolicy $diplomacy,
        private ClockInterface $clock,
    ) {
    }

    /** L’appelant conserve la transaction jusqu’à la fin de l’action protégée. */
    public function lockGuild(PlayerUniverse $actor, int $guildId): Guild
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

    /** Rattacher la demande à cette installation, pas au prochain Hall du même système. */
    public function lockPlaced(PlayerUniverse $actor, int $guildId, int $hallId): GuildHall
    {
        $guild = $this->lockGuild($actor, $guildId);
        $hall = $this->halls->lockForGuild($guild);
        if ($hall === null || $hall->id !== $hallId || $hall->placedAt === null || $hall->solarSystem === null) {
            throw new \DomainException('Le Hall n’est plus disponible à cet emplacement.');
        }

        return $hall;
    }

    /** Les dépôts des membres suivent lockPlaced, sans exiger la délégation de livrer le stock commun. */
    public function lockAtelierDelivery(PlayerUniverse $actor, int $guildId, int $hallId): GuildHall
    {
        $hall = $this->lockPlaced($actor, $guildId, $hallId);
        $member = $this->repository->one(GuildMember::class, ['playerUniverse' => $actor, 'guild' => $hall->guild]);
        if (!$member instanceof GuildMember) {
            throw new \DomainException('Guilde inaccessible.');
        }
        // Un ancien formulaire ne vaut pas autorisation : relire le grade sous le verrou univers
        // commun au service qui modifie les permissions, avant de toucher au stock.
        $this->manager->lockCurrent($member);
        if ($member->rank !== null) {
            $this->manager->lockCurrent($member->rank);
        }
        if (!$this->diplomacy->allows($member, DiplomacyAccessPolicy::ATELIER_DELIVERY)) {
            throw new \DomainException('La permission de livrer les vaisseaux de l’Atelier est requise.');
        }

        return $hall;
    }
}
