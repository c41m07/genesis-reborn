<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\{AdventBinding, AdventEdition, Planet, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AdventParticipation
{
    public function __construct(
        private EntityManagerInterface $em,
        private GameManager $manager,
        private AdventBracket $brackets,
        private GameCatalog $catalog,
        private ClockInterface $clock,
        #[Autowire('%env(bool:ADVENT_ENABLED)%')] private bool $enabled,
    ) {}

    public function binding(Player $player, AdventEdition $edition): ?AdventBinding
    {
        return $this->em->getRepository(AdventBinding::class)->findOneBy(['player' => $player, 'adventEdition' => $edition]);
    }

    public function bind(Player $player, PlayerUniverse $membership, AdventEdition $edition): void
    {
        $this->manager->transaction(function () use ($player, $membership, $edition): void {
            $this->manager->lockCurrent($player);
            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            if (!$this->enabled || $edition->plan !== 'consolidated' || $now < $edition->startsAt || $now >= $edition->closesAt
                || $player->lockedAt !== null || $player->emailVerifiedAt === null || $player->currentPlayerUniverse?->id !== $membership->id) {
                throw new \DomainException('Votre univers actif ne peut pas être retenu pour cette édition.');
            }
            // Tous les chemins d’époque et l’effacement sérialisent sur le compte.
            // La liaison n’est jamais mise à jour lors d’un changement d’univers.
            $db = $this->em->getConnection();
            $old = $db->fetchOne(
                'SELECT universe_id FROM advent_binding WHERE player_id = ? AND advent_edition_id = ? FOR UPDATE',
                [$player->id, $edition->id],
            );
            if ($old !== false) {
                if ((int) $old !== $membership->universe->id) {
                    throw new \DomainException('Votre univers est déjà fixé pour cette édition.');
                }
                return;
            }
            $membershipRow = $db->fetchAssociative('SELECT p.joined_at FROM player_universe p JOIN universe u ON u.id = p.universe_id
                WHERE p.id = ? AND p.player_id = ? AND p.forgotten_at IS NULL AND u.status = ? AND
                (u.starts_at IS NULL OR u.starts_at <= ?) AND (u.ends_at IS NULL OR u.ends_at > ?)',
                [$membership->id, $player->id, 'active', $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')],
            );
            if ($membershipRow === false) {
                throw new \DomainException('Votre univers actif ne peut pas être retenu pour cette édition.');
            }
            $bracket = $db->fetchOne('SELECT bracket FROM advent_progression
                WHERE player_id = ? AND advent_edition_id = ? AND universe_id = ?',
                [$player->id, $edition->id, $membership->universe->id],
            );
            if ($bracket === false || !isset(AdventSeasonPlan::MULTIPLIERS[$bracket])) {
                throw new \DomainException('Le relevé de progression n’est pas disponible pour cet univers. Aucune destination n’a été fixée.');
            }
            $binding = new AdventBinding();
            $binding->adventEdition = $edition;
            $binding->player = $player;
            $binding->universeId = $membership->universe->id;
            $binding->bracket = $bracket;
            $binding->joinedAt = new \DateTimeImmutable($membershipRow['joined_at'], new \DateTimeZone('UTC'));
            $binding->boundAt = $now;
            $this->manager->save($binding);
        });
    }

    /** Seulement depuis la création effective d’une première participation. */
    public function observeJoinLocked(PlayerUniverse $membership, Planet $firstPlanet): void
    {
        if (!$this->enabled) {
            return;
        }
        // Création native : aucun bâtiment, technologie ou accès acquis en
        // dehors des valeurs initiales. Ne pas recalculer plus tard au retrait.
        $bracket = $this->brackets->classify([$firstPlanet->id => []], [], $membership->catalogUnlocks ?? [],
            $this->catalog->forUniverse($membership->universe)['catalog']);
        if ($bracket === null) {
            return;
        }
        $joined = $membership->joinedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $this->em->getConnection()->executeStatement(
            'INSERT INTO advent_progression (advent_edition_id, player_id, universe_id, bracket, observed_at, origin)
             SELECT e.id, ?, ?, ?, ?, ? FROM advent_edition e WHERE e.plan = ? AND e.starts_at <= ? AND e.closes_at > ?
             ON DUPLICATE KEY UPDATE id = advent_progression.id',
            [$membership->player->id, $membership->universe->id, $bracket, $joined, 'arrival', 'consolidated', $joined, $joined],
        );
    }
}
