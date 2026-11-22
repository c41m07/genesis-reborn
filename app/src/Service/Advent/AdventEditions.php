<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\{AdminAuditLog, AdventEdition, Player};
use App\Manager\GameManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AdventEditions
{
    public function __construct(
        private AdventCalendar $calendar,
        private AdventGifts $gifts,
        private EntityManagerInterface $em,
        private GameManager $manager,
        private ClockInterface $clock,
        #[Autowire('%env(bool:ADVENT_ENABLED)%')] public bool $enabled,
    ) {}

    /** @return array{name: string, first: string, last: string, doors: array<int, array{opens_at: string, options: list<array<string, int>>}>} */
    public function prepare(string $name, string $first, string $last, array $options): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new \DomainException('Saisissez un nom d’édition de 120 caractères maximum.');
        }
        $dates = $this->calendar->schedule($first, $last);
        $gifts = $this->gifts->validate($options);
        if ($dates['startsAt'] <= $this->clock->now()) {
            throw new \DomainException('Choisissez une première porte à une date future, à minuit heure de Paris.');
        }

        $doors = [];
        foreach ($dates['doors'] as $door => $opensAt) {
            $doors[$door] = ['opens_at' => $opensAt->format(DATE_ATOM), 'options' => $gifts[$door]];
        }

        return ['name' => $name, 'first' => $first, 'last' => $last, 'doors' => $doors];
    }

    /** @param array{name: string, first: string, last: string, doors: array<int, array{opens_at: string, options: list<array<string, int>>}>} $preview */
    public function publish(Player $actor, array $preview): AdventEdition
    {
        try {
            return $this->manager->transaction(function () use ($actor, $preview): AdventEdition {
                $this->manager->lockCurrent($actor);
                if (!$this->enabled || !in_array('ROLE_ADMIN', $actor->getRoles(), true) || $actor->lockedAt !== null) {
                    throw new \DomainException('La publication de l’Avent est indisponible.');
                }
                // Publication = instantané du brouillon validé côté serveur et stocké
                // en session. Revalider en particulier le début après l’attente du verrou.
                $prepared = $this->prepare($preview['name'], $preview['first'], $preview['last'], array_map(
                    static fn (array $door): array => $door['options'],
                    $preview['doors'],
                ));
                $dates = $this->calendar->schedule($prepared['first'], $prepared['last']);
                $event = new AdventEdition();
                $event->season = (int) substr($prepared['first'], 0, 4);
                $event->name = $prepared['name'];
                $event->startsAt = $dates['startsAt'];
                $event->closesAt = $dates['closesAt'];
                $event->doors = $prepared['doors'];
                $event->publishedAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
                $this->manager->save($event);
                $this->manager->flush();

                $audit = new AdminAuditLog();
                $audit->actor = $actor;
                $audit->action = 'advent_edition_published';
                $audit->targetType = 'advent_edition';
                $audit->targetId = $event->id;
                $audit->details = ['season' => $event->season, 'starts_at' => $event->startsAt->format(DATE_ATOM), 'closes_at' => $event->closesAt->format(DATE_ATOM)];
                $audit->createdAt = $event->publishedAt;
                $this->manager->save($audit);

                return $event;
            });
        } catch (UniqueConstraintViolationException $error) {
            // Deux confirmations ou deux admins ne peuvent créer deux éditions
            // de la même saison, même si un navigateur avait conservé son aperçu.
            throw new \DomainException('Il existe déjà une édition publiée pour cette année de départ.', previous: $error);
        }
    }

    public function visible(): ?AdventEdition
    {
        if (!$this->enabled) {
            return null;
        }

        return $this->em->getRepository(AdventEdition::class)->createQueryBuilder('edition')
            ->andWhere('edition.closesAt > :now')
            ->setParameter('now', $this->clock->now()->setTimezone(new \DateTimeZone('UTC')))
            ->orderBy('edition.startsAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
