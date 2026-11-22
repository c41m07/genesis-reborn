<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\{Guild, Universe};
use App\Repository\GuildHallRepository;
use App\Service\Guild\GuildHallFunding;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GuildHallExtension extends AbstractExtension
{
    public function __construct(private readonly GuildHallFunding $funding, private readonly GuildHallRepository $halls)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('guild_hall_available', fn (Universe $universe): bool => $this->funding->availableIn($universe)),
            new TwigFunction('guild_has_hall', fn (Guild $guild): bool => $this->halls->forGuild($guild) !== null),
        ];
    }
}
