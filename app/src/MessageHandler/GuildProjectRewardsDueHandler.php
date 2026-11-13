<?php
namespace App\MessageHandler;

use App\Message\GuildProjectRewardsDue;
use App\Service\Player\GuildProjectRewardService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GuildProjectRewardsDueHandler
{
    public function __construct(private GuildProjectRewardService $rewards) {}
    public function __invoke(GuildProjectRewardsDue $message): void
    {
        $this->rewards->distribute($message->projectId,$message->dispatchToken);
    }
}
