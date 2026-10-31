<?php
namespace App\Message;
final readonly class QueueItemFinished
{
    public function __construct(public int $queueItemId,public string $dispatchToken) {}
}
