<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHall, GuildJumpPoint, GuildMember, Planet};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Liste des installations et des colonies candidates, limitée aux membres du Hall. */
final readonly class GuildJumpView
{
    public function __construct(
        private EntityManagerInterface $em,
        private GuildJumpConstruction $construction,
        private ClockInterface $clock,
    ) {
    }

    public function describe(GuildHall $hall, bool $mayBuild): array
    {
        $now = $this->clock->now();
        $active = [];
        foreach ($this->em->getRepository(GuildJumpPoint::class)->findBy(['hall' => $hall], ['id' => 'ASC']) as $point) {
            // La même règle valide un chantier futur, sans publier les anciennes acquisitions.
            if ($point->isUsableAt(max($point->readyAt, $now))) {
                $active[$point->planet->id] = [
                    'colony' => $point->planet->name,
                    'owner' => $point->member->playerUniverse->getDisplayName(),
                    'ready_at' => $point->readyAt,
                    'ready' => $point->readyAt <= $now,
                ];
            }
        }

        $quote = $mayBuild ? $this->construction->quote() : null;
        $candidates = [];
        if ($quote !== null) {
            $colonies = $this->em->createQueryBuilder()->select('colony', 'pilot', 'account')
                ->from(Planet::class, 'colony')
                ->join('colony.owner', 'pilot')->join('pilot.player', 'account')
                ->innerJoin(GuildMember::class, 'roster', 'WITH', 'roster.playerUniverse = pilot')
                ->where('roster.guild = :guild AND pilot.universe = :universe AND colony.npcPlanet = false')
                ->setParameter('guild', $hall->guild)
                ->setParameter('universe', $hall->guild->universe)
                ->orderBy('account.username', 'ASC')->addOrderBy('colony.name', 'ASC')->addOrderBy('colony.id', 'ASC')
                ->getQuery()->getResult();
            foreach ($colonies as $colony) {
                if (!isset($active[$colony->id]) && $colony->owner?->isPlayerControlled()) {
                    $candidates[] = ['id' => $colony->id, 'colony' => $colony->name, 'owner' => $colony->owner->getDisplayName()];
                }
            }
        }

        return [
            'points' => array_values($active),
            'candidates' => $candidates,
            'quote' => $quote,
            'fingerprint' => $quote === null ? null : GuildJumpConstruction::fingerprint($quote),
        ];
    }
}
