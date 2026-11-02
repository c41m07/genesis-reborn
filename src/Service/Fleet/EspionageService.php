<?php
namespace App\Service\Fleet;

use App\Entity\{EspionageReport, Fleet, FleetShip, Planet, PlanetBuilding, PlanetDefense, PlanetNpcShip, PlayerDiscovery, PlayerTechnology, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use App\Service\Notification\NotificationService;
use App\Service\Universe\PlanetNpcSeeder;
use Psr\Clock\ClockInterface;

final class EspionageService
{
    public function __construct(private GameRepository $repo, private GameManager $manager, private GameCatalog $catalog, private ResourceService $resources, private PlanetNpcSeeder $npcSeeder, private NotificationService $notifications, private ClockInterface $clock) {}

    /** Résout une mission d'espionnage dans la transaction d'arrivée de flotte. */
    public function resolve(Fleet $fleet, int $probeCount): EspionageReport
    {
        $target = $fleet->destination ?? throw new \LogicException('Destination d’espionnage absente.');
        if ($probeCount < 1) throw new \LogicException('Une mission d’espionnage doit transporter une sonde.');
        $this->npcSeeder->seed($target);
        $this->manager->flush();
        $senderTech = $this->techLevel($fleet->playerUniverse, 'espionage_technology');
        $senderStealth = $this->techLevel($fleet->playerUniverse, 'stealth_technology');
        $targetTech = $target->owner ? $this->techLevel($target->owner, 'espionage_technology') : $this->npcResearch($target, 'espionage_technology');
        $targetCounter = $target->owner ? $this->techLevel($target->owner, 'counter_espionage') : $this->npcResearch($target, 'counter_espionage');
        $settings = $this->catalog->all('espionage');
        $detectionChance = (int) round(max((int) ($settings['min_detection_chance'] ?? 5), min((int) ($settings['max_detection_chance'] ?? 95),
            (int) ($settings['base_detection_chance'] ?? 50)
            + (int) ($settings['counter_espionage_weight'] ?? 10) * $targetCounter
            - (int) ($settings['stealth_weight'] ?? 10) * $senderStealth
            + (int) ($settings['extra_probe_weight'] ?? 1) * max(0, $probeCount - 1)
            + (int) ($settings['local_probe_weight'] ?? 1) * $this->localProbeCount($target)
        )));
        $detected = random_int(1, 100) <= $detectionChance;
        $report = new EspionageReport();
        $report->sender = $fleet->playerUniverse;
        $report->target = $target;
        $report->createdAt = $this->clock->now();
        $report->probesSent = $probeCount;
        $report->detectionChance = $detectionChance;
        $report->senderEspionageLevel = $senderTech;
        $report->targetEspionageLevel = $targetTech;
        $report->targetCounterEspionageLevel = $targetCounter;
        $report->result = $detected ? 'detected' : 'success';
        if ($detected) {
            if ($target->owner) $this->notifyTarget($fleet, $target);
            $this->notifications->enqueue($fleet->playerUniverse, 'Vos sondes ont été détectées sur ' . $target->name . '. Aucun renseignement n’a été obtenu.');
        } else {
            $categories = $settings['intel_categories'] ?? ['resources', 'buildings', 'research', 'fleets', 'defenses'];
            $score = max(0, (int) ($settings['report_score_base'] ?? 1) + $senderTech - $targetTech + $probeCount - 1);
            $report->intelCategories = min(count($categories), $score);
            $snapshot = $this->snapshot($target);
            foreach (array_slice($categories, 0, $report->intelCategories) as $category) {
                if (array_key_exists($category, $snapshot)) $report->snapshot[$category] = $snapshot[$category];
            }
            $this->notifications->enqueue($fleet->playerUniverse, 'Rapport d’espionnage disponible : ' . $target->name . '.');
        }
        $this->manager->save($report);
        $this->manager->flush();
        return $report;
    }

    private function techLevel(PlayerUniverse $membership, string $slug): int
    {
        $rows=$this->repo->all(PlayerTechnology::class,['playerUniverse'=>$membership,'slug'=>$slug]);$value=0.0;foreach($rows as $row)$value+=$row->level*GameCatalog::tierFactor($row->tier);return max(0,(int)round($value));
    }

    private function npcResearch(Planet $planet, string $slug): int
    {
        if (!$planet->npcPlanet) return 0;
        return (int) ($this->catalog->all('planet_conquest')['levels'][$planet->conquestLevel]['research'][$slug] ?? 0);
    }

    private function localProbeCount(Planet $planet): int
    {
        if ($planet->npcPlanet && !$planet->owner) {
            return $this->repo->one(PlanetNpcShip::class, ['planet' => $planet, 'slug' => 'probe'])?->quantity ?? 0;
        }
        $total = 0;
        foreach ($this->repo->all(Fleet::class, ['homePlanet' => $planet, 'status' => 'idle']) as $fleet) $total += $this->shipCount($fleet, 'probe');
        foreach (['orbiting', 'defending'] as $status) foreach ($this->repo->all(Fleet::class, ['destination' => $planet, 'status' => $status]) as $fleet) $total += $this->shipCount($fleet, 'probe');
        return $total;
    }

    private function shipCount(Fleet $fleet, string $slug): int
    {
        return array_sum(array_map(static fn($ship)=>$ship->quantity,$this->repo->all(FleetShip::class,['fleet'=>$fleet,'slug'=>$slug])));
    }

    private function snapshot(Planet $planet): array
    {
        $buildings = [];
        foreach ($this->repo->all(PlanetBuilding::class, ['planet' => $planet]) as $building) if($building->level>0)$buildings[$building->slug][$building->tier]=['label'=>$this->catalog->get('buildings',$building->slug)['label'].' · '.$this->catalog->tiers()[$building->tier]['label'],'level'=>$building->level];
        $research = [];
        if ($planet->owner) {
            foreach ($this->repo->all(PlayerTechnology::class, ['playerUniverse' => $planet->owner]) as $tech) if($tech->level>0)$research[$tech->slug][$tech->tier]=['label'=>$this->catalog->get('technologies',$tech->slug)['label'].' · '.$this->catalog->tiers()[$tech->tier]['label'],'level'=>$tech->level];
        } elseif ($planet->npcPlanet) {
            foreach ($this->catalog->all('planet_conquest')['levels'][$planet->conquestLevel]['research'] ?? [] as $slug => $level) if ($level > 0) $research[$slug] = ['label' => $this->catalog->get('technologies', $slug)['label'] ?? $slug, 'level' => $level];
        }
        $fleets = [];
        if ($planet->npcPlanet && !$planet->owner) {
            foreach ($this->repo->all(PlanetNpcShip::class, ['planet' => $planet]) as $ship) if ($ship->quantity > 0) $fleets[$ship->slug] = ['label' => $this->catalog->get('ships', $ship->slug)['label'] ?? $ship->slug, 'quantity' => $ship->quantity];
        } else {
            $present = $this->repo->all(Fleet::class, ['homePlanet' => $planet, 'status' => 'idle']);
            foreach (['orbiting', 'defending'] as $status) $present = array_merge($present, $this->repo->all(Fleet::class, ['destination' => $planet, 'status' => $status]));
            foreach ($present as $fleet) foreach ($this->repo->all(FleetShip::class, ['fleet' => $fleet]) as $ship) if ($ship->quantity > 0) {
                $key=$ship->slug.':'.$ship->tier;$fleets[$key]['label']=$this->catalog->get('ships',$ship->slug)['label'].' · '.$this->catalog->tiers()[$ship->tier]['label'];
                $fleets[$key]['quantity']=($fleets[$key]['quantity']??0)+$ship->quantity;
            }
        }
        $defenses = [];
        foreach ($this->repo->all(PlanetDefense::class, ['planet' => $planet]) as $defense) if ($defense->quantity > 0) $defenses[$defense->slug] = ['label' => $this->catalog->get('defenses', $defense->slug)['label'] ?? $defense->slug, 'quantity' => $defense->quantity];
        return ['resources' => $this->resources->snapshot($planet), 'buildings' => $buildings, 'research' => $research, 'fleets' => $fleets, 'defenses' => $defenses];
    }

    private function notifyTarget(Fleet $fleet, Planet $target): void
    {
        $owner = $target->owner;
        if (!$owner) return;
        $known = (bool) $this->repo->one(PlayerDiscovery::class, ['playerUniverse' => $owner, 'solarSystem' => $fleet->homePlanet->solarSystem]);
        $identity = $known ? ' par ' . $fleet->playerUniverse->player->username : '';
        $this->notifications->enqueue($owner, 'Sondes d’espionnage détectées sur ' . $target->name . ' depuis ' . $fleet->homePlanet->coordinates() . $identity . '.');
    }
}
