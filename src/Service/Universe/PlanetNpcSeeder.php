<?php
namespace App\Service\Universe;

use App\Entity\Planet;
use App\Entity\PlanetBuilding;
use App\Entity\PlanetDefense;
use App\Entity\PlanetNpcShip;
use App\Entity\PlanetResource;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;

final class PlanetNpcSeeder
{
    public function __construct(private GameRepository $repo, private GameManager $manager, private GameCatalog $catalog, private ResourceService $resources) {}

    public function seed(Planet $planet): void
    {
        if ($planet->owner !== null || !$planet->npcPlanet || $planet->npcGarrisonInitialized) return;
        $preset = $this->catalog->all('planet_conquest')['levels'][$planet->conquestLevel] ?? null;
        if (!is_array($preset)) throw new \DomainException('Préréglage de planète PNJ absent du catalogue.');
        $levels = [];
        foreach ($preset['buildings'] ?? [] as $slug => $level) {
            $definition = $this->catalog->getForUniverse($planet->solarSystem->galaxy->universe, 'buildings', (string) $slug);
            if (!is_int($level) || $level < 1 || $level > 100) throw new \DomainException('Niveau de bâtiment PNJ invalide.');
            $row = $this->repo->one(PlanetBuilding::class, ['planet' => $planet, 'slug' => (string) $slug]);
            if (!$row) { $row = new PlanetBuilding(); $row->planet = $planet; $row->slug = (string) $slug; }
            $row->level = max($row->level, $level); $levels[(string) $slug] = $row->level; $this->manager->save($row);
        }
        foreach ($preset['fleet'] ?? [] as $slug => $quantity) {
            $this->catalog->getForUniverse($planet->solarSystem->galaxy->universe, 'ships', (string) $slug);
            if (!is_int($quantity) || $quantity < 1) throw new \DomainException('Composition de flotte PNJ invalide.');
            $row = $this->repo->one(PlanetNpcShip::class, ['planet' => $planet, 'slug' => (string) $slug]);
            if (!$row) { $row = new PlanetNpcShip(); $row->planet = $planet; $row->slug = (string) $slug; $row->quantity = $quantity; $this->manager->save($row); }
        }
        foreach ($preset['defenses'] ?? [] as $slug => $quantity) {
            $this->catalog->getForUniverse($planet->solarSystem->galaxy->universe, 'defenses', (string) $slug);
            if (!is_int($quantity) || $quantity < 1) throw new \DomainException('Composition de défense PNJ invalide.');
            $row = $this->repo->one(PlanetDefense::class, ['planet' => $planet, 'slug' => (string) $slug]);
            if (!$row) { $row = new PlanetDefense(); $row->planet = $planet; $row->slug = (string) $slug; $row->quantity = $quantity; $this->manager->save($row); }
        }
        if ($this->repo->all(PlanetResource::class, ['planet' => $planet]) === []) {
            $this->resources->initialize($planet, false, $levels, (float) ($preset['resource_multiplier'] ?? 1));
        }
        $planet->npcGarrisonInitialized = true;
        $this->manager->save($planet);
    }
}
