<?php
namespace App\MessageHandler;
use App\Message\FleetArrived;
use App\Service\Fleet\FleetService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
#[AsMessageHandler]
final class FleetArrivedHandler
{
    public function __construct(private FleetService $service) {}
    public function __invoke(FleetArrived $message): void { $this->service->arrive($message->fleetId,$message->dispatchToken); }
}
