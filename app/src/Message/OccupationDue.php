<?php
namespace App\Message;

final readonly class OccupationDue
{
    public function __construct(public int $occupationId,public string $dispatchToken) {}
}
