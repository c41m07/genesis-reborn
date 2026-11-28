<?php

declare(strict_types=1);

namespace App\Service\Guild;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Le barème des opérations dépend du serveur et n’est jamais fourni par le joueur. */
final readonly class GuildHallScheduleProvider
{
    public function __construct(#[Autowire('%env(string:GUILD_HALL_OPERATIONS_JSON)%')] private string $configured)
    {
    }

    public function schedule(): ?GuildHallBuildingSchedule
    {
        if ($this->configured === '') {
            return null;
        }
        $data = json_decode($this->configured, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_array($data['levels'] ?? null) || !is_array($data['capacities'] ?? null)) {
            throw new \DomainException('Barème des chantiers du Hall invalide.');
        }

        $instability = $data['jump_instability_seconds'] ?? null;
        if ($instability !== null && !is_int($instability)) {
            throw new \DomainException('La durée d’instabilité du Jump Point doit être exprimée en secondes entières.');
        }

        return new GuildHallBuildingSchedule($data['levels'], $data['capacities'], $instability);
    }
}
