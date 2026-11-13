<?php
namespace App\Message;

final readonly class GuildProjectRewardsDue
{
    public function __construct(public int $projectId,public string $dispatchToken) {}
}
