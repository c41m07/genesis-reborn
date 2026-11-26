<?php

declare(strict_types=1);

namespace App\Service\Boost;

use App\Entity\PlayerBoost;
use App\Entity\PlayerUniverse;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;

/** Seule référence des effets temporaires ; les anciens boosts instantanés ne changent pas. */
final readonly class TimedBoostEffects
{
    public const KIND = 'timed_boost';
    public const SHELF_SECONDS = 7 * 86400;
    public const ACTIVE_SECONDS = 5 * 3600;
    public const CIVIL_FUEL = 'civil_fuel';
    public const CIVIL_FREIGHT = 'civil_freight';
    public const LOOT_SHIELD = 'loot_shield';
    public const PVP_DEFENSE = 'pvp_defense';
    public const PVP_ATTACK = 'pvp_attack';
    public const QUEUE_SPEED = 'queue_speed';
    public const PRODUCTION = 'production';

    public function __construct(private GameRepository $repo, private ClockInterface $clock) {}

    /** @return list<array{effect:string,label:string,description:string,factor:float}> */
    public static function schedule(): array
    {
        return [
            ['effect' => self::CIVIL_FUEL, 'label' => 'Moteurs économes', 'description' => '−20 % de carburant de trajet pour les missions civiles concernées.', 'factor' => 0.80],
            ['effect' => self::CIVIL_FREIGHT, 'label' => 'Soutes modulaires', 'description' => '+20 % de capacité de fret pour les transports et stationnements.', 'factor' => 1.20],
            ['effect' => self::LOOT_SHIELD, 'label' => 'Coffres renforcés', 'description' => 'Double la part des entrepôts protégée du pillage : de 10 % à 20 % avec les règles habituelles.', 'factor' => 2.00],
            ['effect' => self::PVP_DEFENSE, 'label' => 'Défense renforcée', 'description' => '+10 % de dégâts en défense contre un autre joueur.', 'factor' => 1.10],
            ['effect' => self::PVP_ATTACK, 'label' => 'Offensive renforcée', 'description' => '+10 % de dégâts en attaque contre un autre joueur.', 'factor' => 1.10],
            ['effect' => self::QUEUE_SPEED, 'label' => 'Rythme accéléré', 'description' => '+25 % de travail des files tant que le boost est actif.', 'factor' => 1.25],
            ['effect' => self::PRODUCTION, 'label' => 'Production renforcée', 'description' => '+15 % de production brute dans vos colonies.', 'factor' => 1.15],
        ];
    }

    public static function definition(string $effect): array
    {
        foreach (self::schedule() as $definition) {
            if ($definition['effect'] === $effect) {
                return $definition;
            }
        }

        throw new \LogicException('Effet de boost quotidien inconnu.');
    }

    /** Les durées sont des secondes écoulées, indépendantes du jour civil de retrait. */
    public static function expiresAt(PlayerBoost $boost): \DateTimeImmutable
    {
        return $boost->earnedAt->setTimestamp($boost->earnedAt->getTimestamp() + self::SHELF_SECONDS);
    }

    public static function endsAt(PlayerBoost $boost): \DateTimeImmutable
    {
        $start = $boost->usedAt ?? throw new \LogicException('Boost non activé.');
        return $start->setTimestamp($start->getTimestamp() + self::ACTIVE_SECONDS);
    }

    /** @return list<PlayerBoost> Fenêtres demi-ouvertes pertinentes, y compris celles déjà échues. */
    public function windows(?PlayerUniverse $membership, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $effect = null): array
    {
        if ($membership?->id === null) {
            return [];
        }

        return $this->repo->timedBoostWindows(
            $membership, $from->setTimestamp($from->getTimestamp() - self::ACTIVE_SECONDS), $to, $effect,
        );
    }

    public function factor(?PlayerUniverse $membership, string $effect, ?\DateTimeImmutable $at = null): float
    {
        $at ??= $this->clock->now();
        return self::factorInWindows($this->windows($membership, $at, $at, $effect), $effect, $at);
    }

    /** @param list<PlayerBoost> $windows */
    public static function factorInWindows(array $windows, string $effect, \DateTimeImmutable $at): float
    {
        foreach ($windows as $window) {
            if ($window->operationType === $effect && $window->usedAt <= $at && $at < self::endsAt($window)) {
                return self::definition($effect)['factor'];
            }
        }

        return 1.0;
    }

    public function protectedRatio(?PlayerUniverse $membership, float $base, ?\DateTimeImmutable $at = null): float
    {
        return max(0.0, min(1.0, $base * $this->factor($membership, self::LOOT_SHIELD, $at)));
    }
}
