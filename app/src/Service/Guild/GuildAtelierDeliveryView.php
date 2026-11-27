<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildAtelierStock, GuildHall, GuildMember, Planet, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GuildAtelierDeliveryView
{
    public function __construct(
        private GameRepository $repository,
        private EntityManagerInterface $em,
        private GameCatalog $catalog,
    ) {
    }

    /** Appelé uniquement pour l’Atelier de la guilde actuelle et son grade de livraison. */
    public function describe(PlayerUniverse $actor, GuildHall $hall): array
    {
        $destinations = $this->em->createQueryBuilder()
            ->select('colony.id AS id, colony.name AS name, player.username AS recipient')
            ->from(Planet::class, 'colony')
            ->innerJoin('colony.owner', 'recipient')
            ->innerJoin('recipient.player', 'player')
            ->innerJoin(GuildMember::class, 'guildLink', 'WITH', 'guildLink.playerUniverse = recipient')
            ->where('guildLink.guild = :guild AND recipient.universe = :universe AND recipient.id <> :actor AND colony.npcPlanet = false')
            ->setParameter('guild', $hall->guild)
            ->setParameter('universe', $actor->universe)
            ->setParameter('actor', $actor->id)
            ->orderBy('player.username', 'ASC')
            ->addOrderBy('colony.name', 'ASC')
            ->addOrderBy('colony.id', 'ASC')
            ->getQuery()->getArrayResult();
        $stock = $this->repository->one(GuildAtelierStock::class, ['hall' => $hall]);
        $catalog = $this->catalog->forUniverse($actor->universe)['catalog']['ships'];
        $ships = [];
        foreach ($stock?->ships ?? [] as $slug => $ranks) {
            $name = $catalog[$slug]['name'] ?? $slug;
            if ($ranks !== []) {
                $ships[] = ['slug' => $slug, 'name' => $name, 'ranks' => $ranks];
            }
        }

        return ['destinations' => $destinations, 'ships' => $ships];
    }
}
