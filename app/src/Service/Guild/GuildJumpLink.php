<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildJumpPoint, Planet, PlayerUniverse};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Seul le serveur décide si ces deux colonies précises possèdent un lien utilisable. */
final readonly class GuildJumpLink
{
    public function __construct(private EntityManagerInterface $em, private ClockInterface $clock)
    {
    }

    public function available(PlayerUniverse $actor, Planet $origin, Planet $target): bool
    {
        if ($actor->id === null || $origin->id === null || $target->id === null || $origin->id === $target->id
            || $origin->owner?->id !== $actor->id || $target->owner === null
            || $origin->solarSystem->galaxy->id !== $target->solarSystem->galaxy->id) {
            return false;
        }

        $points = $this->em->createQueryBuilder()
            ->select('point', 'hall', 'affiliation', 'guild', 'planet')
            ->from(GuildJumpPoint::class, 'point')
            ->join('point.hall', 'hall')->join('point.member', 'affiliation')
            ->join('hall.guild', 'guild')->join('point.planet', 'planet')
            ->where('planet.id IN (:colonies) AND guild.universe = :universe')
            ->setParameter('colonies', [$origin->id, $target->id])
            ->setParameter('universe', $actor->universe)
            ->getQuery()->getResult();
        if (count($points) !== 2) {
            return false;
        }

        [$left, $right] = $points;
        $now = $this->clock->now();

        return $left->hall->id === $right->hall->id && $left->isUsableAt($now) && $right->isUsableAt($now);
    }
}
