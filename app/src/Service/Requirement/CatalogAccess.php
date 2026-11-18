<?php
namespace App\Service\Requirement;

use App\Entity\{PlayerUniverse,Planet,PlanetBuilding,PlayerTechnology,Universe};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

/** Le verrou du joueur doit être détenu avant de mémoriser des accès. */
final class CatalogAccess
{
    public const VERSION = PlayerUniverse::CATALOG_REQUIREMENTS_VERSION;

    public function __construct(private GameRepository $repo,private GameCatalog $catalog,private RequirementChecker $requirements)
    {
    }

    public static function key(Planet $planet,string $group,string $slug,string $tier):string
    {
        return 'v'.self::VERSION.':'.($group==='technologies'?'universe':'planet:'.$planet->id).':'.$group.':'.$slug.':'.$tier;
    }

    public function isCurrent(PlayerUniverse $membership):bool
    {
        return ($membership->catalogUnlocks['_requirements_version']??null)===self::VERSION;
    }

    public function acquired(PlayerUniverse $membership,Planet $planet,string $group,string $slug,string $tier):bool
    {
        return $this->isCurrent($membership)&&isset($membership->catalogUnlocks[self::key($planet,$group,$slug,$tier)]);
    }

    public function failures(PlayerUniverse $membership,Planet $planet,string $group,array $definition,string $tier,array $buildings,array $tech):array
    {
        if (!$this->isCurrent($membership)) {
            return ['Actions temporairement indisponibles'];
        }
        if ($this->acquired($membership,$planet,$group,$definition['slug'],$tier)) {
            return [];
        }
        return $this->requirements->check($this->requirements->forTier($definition,$tier),$buildings,$tech,[],
            $this->catalog->forUniverse($membership->universe)['catalog']);
    }

    /** @param array<string, array<string, int>> $buildings Niveaux de la colonie de lancement. */
    public function hasLocalResearchLaboratory(array $buildings): bool
    {
        // Le déblocage dans l’univers est permanent. L’installation de départ est vérifiée à chaque ajout.
        return ($buildings['lab']['basic'] ?? 0) >= 1;
    }

    public function captureLocked(PlayerUniverse $membership):void
    {
        // La bascule d'une participation existante relève de la commande explicite,
        // jamais d'une lecture, d'un worker ou de l'achèvement d'une ancienne commande.
        if (!$this->isCurrent($membership)) {
            return;
        }
        $definitions=$this->catalog->forUniverse($membership->universe)['catalog'];
        $tech=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$membership]);
        foreach($this->repo->planets($membership->universe,$membership) as $planet){
            $buildings=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
            foreach ($definitions as $group => $entries) {
                foreach ($entries as $slug => $definition) {
                    foreach (GameCatalog::TIERS as $tier) {
                        $key = self::key($planet, $group, $slug, $tier);
                        if (isset($membership->catalogUnlocks[$key])) {
                            continue;
                        }
                        // Seules les conditions de la nouvelle grille ouvrent un accès.
                        // Le niveau de l'élément, ses unités et ses anciennes commandes n'en sont pas la preuve.
                        $conditions = $this->requirements->forTier($definition, $tier);
                        if ($this->requirements->check($conditions, $buildings, $tech, [], $definitions) === []) {
                            $membership->catalogUnlocks[$key] = true;
                        }
                    }
                }
            }
        }
    }

    /** Sous verrou de participation ; les anciennes clés restent conservées pour diagnostic. */
    public function requalifyLocked(PlayerUniverse $membership):void
    {
        if ($this->isCurrent($membership)) {
            return;
        }
        if ($this->overrideConflicts($membership->universe) !== []) {
            throw new \DomainException('Surcharges de prérequis à examiner avant la bascule.');
        }
        $membership->catalogUnlocks??=[];
        $membership->catalogUnlocks['_requirements_version']=self::VERSION;
        $this->captureLocked($membership);
    }

    /** @return list<string> Profils dont la personnalisation change la grille décidée. */
    public function overrideConflicts(Universe $universe):array
    {
        $conflicts = [];
        $definitions = $this->catalog->forUniverse($universe)['catalog'];
        foreach ($universe->gameSettings['catalog_overrides'] ?? [] as $group => $entries) {
            foreach (array_keys($entries) as $slug) {
                if (!isset($definitions[$group][$slug])) {
                    continue;
                }
                foreach (GameCatalog::TIERS as $tier) {
                    $expected = $this->requirements->forTier($this->catalog->get($group, $slug), $tier);
                    $actual = $this->requirements->forTier($definitions[$group][$slug], $tier);
                    if ($actual != $expected) {
                        $conflicts[] = $group.'/'.$slug.'/'.$tier;
                    }
                }
            }
        }
        return $conflicts;
    }
}
