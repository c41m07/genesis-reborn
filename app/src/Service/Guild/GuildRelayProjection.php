<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\Fleet;

/** Uniquement la partie actuellement observée à l’intérieur du système couvert. */
final class GuildRelayProjection
{
    public const MISSIONS = ['explore', 'colonize', 'transport', 'attack', 'conquer', 'defend', 'orbit', 'station'];

    /** @return array{direction:string,from:float,to:float,segmentStart:float,observedAt:int,until:int}|null */
    public static function contact(Fleet $fleet, int $system, \DateTimeImmutable $now): ?array
    {
        if (!in_array($fleet->mission, self::MISSIONS, true)
            || !in_array($fleet->status, ['traveling', 'returning'], true)) {
            return null;
        }
        $navigation = $fleet->navigation;
        $duration = (float) ($navigation['seconds'] ?? 0);
        $active = $navigation['active'] ?? [];
        $anchor = (int) ($active['at'] ?? 0);
        $finish = $fleet->arrivalAt?->getTimestamp();
        $instant = $now->getTimestamp();
        $base = (float) ($active['progress'] ?? -1);
        if ($duration <= 0 || ($active['status'] ?? null) !== $fleet->status || $anchor <= 0
            || $finish === null || $instant < $anchor || $instant >= $finish || $base < 0 || $base >= 1) {
            return null;
        }

        // Un repli vers une autre colonie n’a pas de segment local enregistré.
        // Ne jamais projeter l’itinéraire précédent à la place du vrai repli.
        $recall = $navigation['recall'] ?? null;
        $reversed = $fleet->status === 'returning' && ($recall !== null || !isset($navigation['station']['route_origin_id']));
        $covered = 1.0;
        if ($reversed && $recall !== null) {
            if ($fleet->returnPlanet === null || $fleet->returnPlanet->solarSystem->id !== ($navigation['origin'][0] ?? null)
                || $fleet->returnPlanet->position !== ($navigation['origin'][4] ?? null)) {
                return null;
            }
            $covered = (float) ($recall['progress'] ?? 0);
            if ($covered <= 0 || $covered > 1) {
                return null;
            }
        }
        $progress = $base + (1 - $base) * ($instant - $anchor) / ($finish - $anchor);
        $route = $reversed ? $covered * (1 - $progress) : $progress;
        $elapsed = 0.0;
        foreach ($navigation['legs'] ?? [] as $leg) {
            $start = $elapsed / $duration;
            $elapsed += (float) ($leg['seconds'] ?? 0);
            $end = $elapsed / $duration;
            if (($leg['hyper'] ?? true) || ($leg['from'][0] ?? null) !== $system || ($leg['to'][0] ?? null) !== $system
                || $end <= $start || ($reversed ? $route <= $start || $route > $end : $route < $start || $route >= $end)) {
                continue;
            }
            $first = (float) ($leg['from'][4] ?? 10);
            $last = (float) ($leg['to'][4] ?? 10);
            if ($first < 0 || $last < 0 || $first > 10 || $last > 10 || $first === $last) {
                return null;
            }
            $current = $first + ($last - $first) * (($route - $start) / ($end - $start));
            $edge = $reversed ? 1 - $start / $covered : $end;
            $until = min($finish, max($instant + 1, (int) ceil($anchor + ($finish - $anchor) * ($edge - $base) / (1 - $base))));
            $destination = $reversed ? $first : $last;
            $departure = $reversed ? $last : $first;

            return [
                'direction' => $destination === 10.0 ? 'outbound' : ($departure === 10.0 ? 'inbound' : 'local'),
                'from' => $current,
                'to' => $destination,
                'segmentStart' => $departure,
                'observedAt' => $instant,
                'until' => $until,
            ];
        }

        return null;
    }
}
