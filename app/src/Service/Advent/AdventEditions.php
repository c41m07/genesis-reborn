<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\{AdminAuditLog, AdventEdition, AdventWindow, OutboxMessage, Player, Universe};
use App\Manager\GameManager;
use App\Message\AdventCaptureDue;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class AdventEditions
{
    public function __construct(
        private AdventCalendar $calendar,
        private AdventSeasonPlan $seasonPlan,
        private EntityManagerInterface $em,
        private GameManager $manager,
        private ClockInterface $clock,
    ) {}

    /** L’aperçu en session fige le périmètre et les cadeaux effectivement proposés. */
    public function prepare(string $name, int $season, int $universe, string $from, string $until): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new \DomainException('Saisissez un nom d’édition de 120 caractères maximum.');
        }
        $dates = $this->calendar->schedule($season.'-12-01', $season.'-12-31');
        $start = $this->calendar->instant($from);
        $end = $this->calendar->instant($until);
        if ($start >= $end || $end <= $this->clock->now() || $end <= $dates['startsAt']) {
            throw new \DomainException('La fermeture doit suivre le début de visibilité et la première porte, et être encore à venir.');
        }
        $world = $this->em->find(Universe::class, $universe);
        if ($world === null) {
            throw new \DomainException('Choisissez un univers existant.');
        }
        $existing = $this->em->getRepository(AdventEdition::class)->findOneBy(['season' => $season]);
        $doors = $existing?->doors ?? $this->seasonPlan->doors();
        foreach ($dates['doors'] as $number => $opensAt) {
            // Un ancien barème publié reste figé. Une édition déplacée historiquement
            // ne doit pas être présentée comme un calendrier du 1er au 24 décembre.
            if ($existing !== null && ($existing->plan !== 'consolidated'
                || new \DateTimeImmutable($doors[$number]['opens_at']) != $opensAt)) {
                throw new \DomainException('Cette saison contient déjà une édition incompatible avec les portes de décembre.');
            }
            $doors[$number]['opens_at'] = $opensAt->format(DATE_ATOM);
            foreach ($doors[$number]['options'] as $choices) {
                foreach ($choices as $offer) {
                    if ($offer['kind'] === 'ship_pack' && $offer['value_twice_metal'] > 2 * $offer['budget_metal']) {
                        throw new \DomainException('La valeur d’un lot dépasse le plafond approuvé de la porte '.$number.'.');
                    }
                }
            }
        }

        return compact('name', 'season', 'universe', 'from', 'until', 'doors') + ['universeName' => $world->name];
    }

    public function publish(Player $actor, array $preview): AdventWindow
    {
        try {
            return $this->manager->transaction(function () use ($actor, $preview): AdventWindow {
                $this->manager->lockCurrent($actor);
                if (!in_array('ROLE_ADMIN', $actor->getRoles(), true) || $actor->lockedAt !== null) {
                    throw new \DomainException('La publication de l’Avent est indisponible.');
                }
                $world = $this->em->find(Universe::class, $preview['universe']);
                if ($world === null) {
                    throw new \DomainException('L’univers de cet aperçu n’existe plus.');
                }
                // Sérialiser les publications du même univers avant de vérifier leurs périodes.
                $this->manager->lockCurrent($world);
                $current = $this->prepare($preview['name'], $preview['season'], $world->id, $preview['from'], $preview['until']);
                if ($current != $preview) {
                    throw new \DomainException('L’édition a changé depuis votre aperçu. Préparez un nouvel aperçu.');
                }
                $start = $this->calendar->instant($preview['from']);
                $end = $this->calendar->instant($preview['until']);
                $db = $this->em->getConnection();
                if ($db->fetchOne('SELECT id FROM advent_window WHERE universe_id = ? AND visible_from < ? AND visible_until > ? LIMIT 1 FOR UPDATE',
                    [$world->id, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')])) {
                    throw new \DomainException('Un calendrier est déjà programmé sur cette période dans cet univers.');
                }
                $edition = $this->em->getRepository(AdventEdition::class)->findOneBy(['season' => $preview['season']]);
                $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
                if ($edition === null) {
                    $edition = new AdventEdition();
                    $edition->season = $preview['season'];
                    $edition->name = $preview['name'];
                    $edition->plan = 'consolidated';
                    $edition->startsAt = new \DateTimeImmutable($preview['doors'][1]['opens_at']);
                    $edition->closesAt = $end;
                    $edition->doors = $preview['doors'];
                    $edition->publishedAt = $now;
                    $this->manager->save($edition);
                    $this->manager->flush();
                } else {
                    $this->manager->lockCurrent($edition);
                    // La cohorte et les droits sont communs à la saison. Chaque univers
                    // garde sa propre fermeture même si une autre fenêtre dure davantage.
                    $edition->closesAt = max($edition->closesAt, $end);
                }
                if ($edition->cohortCapturedAt === null) {
                    $message = new OutboxMessage();
                    $message->kind = AdventCaptureDue::KIND;
                    $message->targetId = $edition->id;
                    $message->dueAt = max($now, $edition->startsAt);
                    $message->token = $now > $edition->startsAt ? 'late' : 'scheduled';
                    $this->manager->save($message);
                }
                $window = new AdventWindow();
                $window->edition = $edition;
                $window->universe = $world;
                $window->name = $preview['name'];
                $window->visibleFrom = $start;
                $window->visibleUntil = $end;
                $this->manager->save($window);
                $audit = new AdminAuditLog();
                $audit->actor = $actor;
                $audit->action = 'advent_edition_published';
                $audit->targetType = 'advent_edition';
                $audit->targetId = $edition->id;
                $audit->details = ['season' => $edition->season, 'universe' => $world->id, 'visible_from' => $start->format(DATE_ATOM), 'visible_until' => $end->format(DATE_ATOM)];
                $audit->createdAt = $now;
                $this->manager->save($audit);

                return $window;
            });
        } catch (UniqueConstraintViolationException $error) {
            throw new \DomainException('Cette saison vient d’être créée. Vérifiez son barème dans un nouvel aperçu.', previous: $error);
        }
    }
}
