<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet, Planet, PlayerUniverse, TransportReport};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Boost\BoostEventService;
use App\Service\Colony\ResourceService;
use App\Service\Notification\NotificationService;
use App\Service\Player\{PreferencesService, RelationResolver, VacationCalendar};
use Psr\Clock\ClockInterface;

final class TransportService
{
    public function __construct(private GameRepository $repo, private GameManager $manager, private ResourceService $resources, private PreferencesService $preferences, private RelationResolver $relations, private NotificationService $notifications, private BoostEventService $boostEvents, private ClockInterface $clock, private VacationCalendar $vacations) {}

    public function canReceive(PlayerUniverse $sender, ?Planet $target): bool
    {
        $recipient = $target?->owner;
        if (!$recipient || !$sender->isPlayerControlled() || !$recipient->isPlayerControlled() || $recipient->universe->id !== $sender->universe->id) return false;
        if ($this->vacations->isActive($sender) || $this->vacations->isActive($recipient)) return false;
        if ($recipient->id === $sender->id) return true;
        return match ($this->preferences->for($recipient->player)['transportReception']) {
            'all' => true,
            'allies' => $this->relations->allied($sender, $recipient),
            default => false,
        };
    }

    public function recordDeparture(Fleet $fleet, array $freight): void
    {
        $report = new TransportReport();
        $report->fleet = $fleet;
        $report->sender = $fleet->playerUniverse;
        $report->recipient = $fleet->destination->owner;
        $report->createdAt = $this->clock->now();
        $report->snapshot = [
            'sender' => $report->sender->getDisplayName(),
            'recipient' => $report->recipient->getDisplayName(),
            'origin' => $fleet->homePlanet->name.' ['.$fleet->homePlanet->coordinates().']',
            'destination' => $fleet->destination->name.' ['.$fleet->destination->coordinates().']',
            'sent' => $freight, 'delivered' => [], 'returned' => [],
        ];
        $this->manager->save($report);
    }

    public function deliver(Fleet $fleet): void
    {
        $target = $fleet->destination;
        if ($target) $this->manager->lock($target);
        $report = $this->repo->one(TransportReport::class, ['fleet' => $fleet]);
        // Les transports déjà en vol avant cette évolution visaient uniquement le propriétaire.
        $recipient = $report?->recipient ?? $fleet->playerUniverse;
        if ($target?->owner?->player!==null) $this->manager->lock($target->owner->player);
        $allowed = $target?->owner?->id === $recipient->id && $this->canReceive($fleet->playerUniverse, $target);
        $freight = $fleet->cargo;
        $freight['hydrogen'] = max(0, ($freight['hydrogen'] ?? 0) - $fleet->reservedFuel);
        $result = $allowed ? $this->resources->deposit($target, $freight) : ['delivered' => [], 'overflow' => $freight];
        foreach ($result['delivered'] as $slug => $amount) $fleet->cargo[$slug] -= $amount;
        if ($recipient->id === $fleet->playerUniverse->id && array_sum($result['delivered']) > 0) {
            $this->boostEvents->recordTransportDelivery($fleet->playerUniverse, $fleet->id, array_sum($result['delivered']));
        }
        if ($report) {
            $report->status = $allowed ? 'delivered' : 'refused';
            $report->resolvedAt = $this->clock->now();
            $report->snapshot['delivered'] = $result['delivered'];
            $report->snapshot['returned'] = $result['overflow'];
        }
        $message = $allowed ? 'Transport livré sur '.$target->name.'. Le surplus éventuel revient avec la flotte.' : 'Transport non livré : propriétaire ou autorisation modifiés. La cargaison revient.';
        $this->notifications->enqueue($fleet->playerUniverse, $message);
        if ($report && $recipient->id !== $fleet->playerUniverse->id) {
            $this->notifications->enqueue($recipient, 'Transport de '.$report->snapshot['sender'].' : '.($allowed ? 'livraison effectuée.' : 'livraison refusée.').' Consultez vos rapports économiques.');
        }
    }

    public function recordReturn(Fleet $fleet, array $result): void
    {
        $report = $this->repo->one(TransportReport::class, ['fleet' => $fleet]);
        if (!$report) return;
        $report->snapshot['returned_deposited'] = $result['delivered'];
        $report->snapshot['return_overflow'] = $result['overflow'];
        $report->snapshot['returned_at'] = $this->clock->now()->format(DATE_ATOM);
    }
    public function recordConversionRecall(Fleet $fleet, string $reason = 'empire_conversion'): void
    {
        if($fleet->mission!=='transport') return;
        $report=$this->repo->one(TransportReport::class,['fleet'=>$fleet]);
        if($report===null || $report->status!=='traveling') return;
        $report->status='refused';$report->resolvedAt=$this->clock->now();
        $freight=$fleet->cargo;$freight['hydrogen']=max(0,($freight['hydrogen']??0)-$fleet->reservedFuel);
        $report->snapshot['delivered']=[];$report->snapshot['returned']=$freight;
        $report->snapshot['refusal_reason']=$reason;
    }
}
