<?php

declare(strict_types=1);

namespace App\Service\Advent;

final class AdventCalendar
{
    public const DOORS = 24;
    public const PARIS = 'Europe/Paris';

    /** @return array{startsAt: \DateTimeImmutable, closesAt: \DateTimeImmutable, doors: array<int, \DateTimeImmutable>} */
    public function schedule(string $firstDay, string $lastClaimDay): array
    {
        $first = $this->parisDay($firstDay);
        $last = $this->parisDay($lastClaimDay);
        // Les portes suivent le jour civil à Paris : ajouter 24 h en UTC serait
        // incorrect si une édition personnalisée traverse un changement d’heure.
        if ($last < $first->modify('+23 days') || $last > $first->modify('+62 days')) {
            throw new \DomainException('La clôture doit couvrir les 24 portes et rester dans les 63 jours de l’édition.');
        }

        $utc = new \DateTimeZone('UTC');
        $doors = [];
        for ($door = 1; $door <= self::DOORS; ++$door) {
            $doors[$door] = $first->modify('+'.($door - 1).' days')->setTimezone($utc);
        }

        // Le jour affiché est entièrement inclus ; seul le lendemain est exclu.
        return ['startsAt' => $doors[1], 'closesAt' => $last->modify('+1 day')->setTimezone($utc), 'doors' => $doors];
    }

    public function boxExpiry(int $season): \DateTimeImmutable
    {
        // La fenêtre locale peut fermer plus tôt ; la boîte acquise dispose
        // de tout le 31 décembre, selon le calendrier civil de Paris.
        return $this->parisDay($season.'-12-31')->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'));
    }

    public function instant(string $value): \DateTimeImmutable
    {
        if (preg_match('/\A20\d{2}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $value)) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new \DateTimeZone(self::PARIS));
            if ($date !== false && $date->format('Y-m-d\TH:i') === $value) {
                return $date->setTimezone(new \DateTimeZone('UTC'));
            }
        }
        throw new \DomainException('Saisissez une date et une heure valides, en heure de Paris.');
    }

    public function canOpen(\DateTimeImmutable $now, \DateTimeImmutable $opensAt, \DateTimeImmutable $closesAt): bool
    {
        return $now >= $opensAt && $now < $closesAt;
    }

    private function parisDay(string $date): \DateTimeImmutable
    {
        // Filtrer la forme et les caractères avant le parseur PHP : une
        // chaîne contenant un octet nul ne doit pas contourner le refus métier.
        if (!preg_match('/\A20\d{2}-\d{2}-\d{2}\z/', $date)) {
            throw new \DomainException('Saisissez une date civile valide au format AAAA-MM-JJ.');
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone(self::PARIS));
        if ($day === false || $day->format('Y-m-d') !== $date) {
            throw new \DomainException('Saisissez une date civile valide au format AAAA-MM-JJ.');
        }

        return $day;
    }
}
