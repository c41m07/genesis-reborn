<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\{Guild, GuildRecruitment, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Player\DiplomacyService;

final class GuildRecruitmentFixture
{
    // Les scénarios existants passent par les deux consentements, comme les joueurs.
    public static function enrol(DiplomacyService $service, GameRepository $repository, PlayerUniverse $candidate, int $guildId): void
    {
        $guild = $repository->find(Guild::class, $guildId);
        $service->applyToGuild($candidate, $guildId);
        $request = $repository->one(GuildRecruitment::class, ['guild' => $guild, 'candidate' => $candidate]);
        $service->answerRecruitment($guild->leader, $request->id, 'accept');
    }
}
