<?php
namespace App\Message;
final readonly class FleetArrived { public function __construct(public int $fleetId,public string $dispatchToken) {} }
