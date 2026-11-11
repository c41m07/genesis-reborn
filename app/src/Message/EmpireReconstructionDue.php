<?php
namespace App\Message;

final readonly class EmpireReconstructionDue
{
    public function __construct(public int $reconstructionId,public string $dispatchToken) {}
}
