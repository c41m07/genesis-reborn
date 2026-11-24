<?php

namespace App\Service\Colony;

use App\Entity\{Planet, PlayerUniverse};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Requirement\RequirementChecker;

final class CatalogueInsights
{
    public function __construct(
        private GameCatalog $catalog,
        private ResourceService $resources,
        private RequirementChecker $requirements,
        private TechnologyEffectCalculator $technologyEffects,
        private \App\Service\Requirement\CatalogAccess $access,
    )
    {
    }

    public function benefits(
        array $definition,
        string $group,
        ?int $target,
        Planet $planet,
        array $levels,
        array $technologies,
        ?CatalogueProductionContext $context = null,
    ): array
    {
        $tier = (string) ($definition['_tier'] ?? 'basic');
        $factor = GameCatalog::tierFactor($tier);
        $slug = $definition['slug'];
        if ($group === 'buildings') {
            // Sans projection, les deux lectures utilisent les rangs acquis, jamais la file.
            $rankedLevels = $levels[$slug] ?? [];
            if (is_numeric($rankedLevels)) {
                $rankedLevels = ['basic' => (int) $rankedLevels];
            }
            if ($target !== null) {
                // Pour un gain, comparer uniquement le dernier niveau proposé.
                $rankedLevels[$tier] = max(0, $target - 1);
            }
            $levels[$slug] = $rankedLevels;
            $next = $levels;
            if ($target !== null) {
                $next[$slug][$tier] = $target;
            }
            if (isset($definition['production_resource'])) {
                $benefitResource = $definition['production_resource'];
                $production = $this->resources->rate($planet, $benefitResource, 0, $levels, $context) * 3600;
                $result = [[
                    'label' => 'Production de ' . mb_strtolower($this->catalog->rules()['resources'][$benefitResource]),
                    'before' => $production,
                    'after' => $target === null ? $production : $this->resources->rate($planet, $benefitResource, 0, $next, $context) * 3600,
                    'unit' => '/ h',
                ]];
                $beforeUse = $this->resources->factoryConsumption($planet, $slug, $levels, $context);
                $afterUse = $target === null ? $beforeUse : $this->resources->factoryConsumption($planet, $slug, $next, $context);
                foreach (['electricity' => 'Consommation électrique', 'hydrogen' => 'Consommation d’hydrogène'] as $resource => $label) {
                    if (max($beforeUse[$resource], $afterUse[$resource]) > 0) {
                        $result[] = [
                            'label' => $label,
                            'before' => $beforeUse[$resource] * 3600,
                            'after' => $afterUse[$resource] * 3600,
                            'unit' => '/ h',
                            'consumption' => true,
                        ];
                    }
                }
                return $result;
            }
            if (isset($definition['storage_resource'])) {
                $benefitResource = $definition['storage_resource'];
                $capacity = $this->resources->capacity($planet, $benefitResource, $levels);
                return [[
                    'label' => 'Stockage de ' . mb_strtolower($this->catalog->rules()['resources'][$benefitResource]),
                    'before' => $capacity,
                    'after' => $target === null ? $capacity : $this->resources->capacity($planet, $benefitResource, $next),
                    'unit' => '',
                ]];
            }
            if (in_array($slug, ['lab', 'shipyard'], true)) {
                $maxLevel = max(1, (int) ($definition['max_level'] ?? 25));
                $afterRanks = $next[$slug];
                $cap = (float) ($this->catalog->rules()['time_reduction_max_percent'] ?? 70);
                $before = GameCatalog::infrastructureBonus($levels[$slug] ?? [], $cap, $maxLevel, $this->catalog->rules()['building_level_growth'] ?? []);
                $after = GameCatalog::infrastructureBonus($afterRanks, $cap, $maxLevel, $this->catalog->rules()['building_level_growth'] ?? []);
                return [[
                    'label' => $slug === 'lab' ? 'Réduction du temps de recherche' : 'Réduction du temps de construction des vaisseaux',
                    'before' => $before,
                    'after' => $after,
                    'unit' => '%',
                ]];
            }
            if ($slug === 'fortress') {
                $effect = $definition['effect'] ?? [];
                if ($target === null && (($effect['targets'] ?? null) !== 'ground_defenses' || ($effect['stat'] ?? null) !== 'hp')) {
                    return [];
                }
                // Le combat utilise le niveau de référence du bunker pour le bonus acquis.
                $maxLevel = max(1, (int) ($definition['max_level'] ?? 25));
                $afterRanks = $next[$slug];
                $cap = (float) ($effect['max_bonus'] ?? 25);
                return [[
                    'label' => 'Structure supplémentaire des défenses',
                    'before' => GameCatalog::infrastructureBonus($levels[$slug] ?? [], $cap, $maxLevel, $this->catalog->rules()['building_level_growth'] ?? []),
                    'after' => GameCatalog::infrastructureBonus($afterRanks, $cap, $maxLevel, $this->catalog->rules()['building_level_growth'] ?? []),
                    'unit' => '%',
                ]];
            }
        }
        if ($group === 'technologies') {
            return $this->technologyBenefits($definition, $target, $technologies, $planet);
        }
        if ($group === 'ships') {
            $definition = $this->technologyEffects->applyToShips([$definition], $technologies, $planet->solarSystem->galaxy->universe)[0];
        }
        if ($group === 'ships') {
            $shipBenefits = [
                ['label' => 'Vitesse dans le système', 'value' => $definition['speed_system'] ?? 0, 'unit' => ''],
                ['label' => 'Vitesse entre systèmes', 'value' => $definition['speed_galaxy'] ?? 0, 'unit' => ''],
                ['label' => 'Tirs principaux par round', 'text' => (float) ($definition['base_attack'] ?? 0) > 0 ? ($definition['shots_min'] ?? 0) . '–' . ($definition['shots_max'] ?? 0) : 'Aucun'],
                ['label' => 'Attaque', 'value' => $definition['base_attack'] ?? 0, 'unit' => ''],
                ['label' => 'Structure', 'value' => $definition['hp'], 'unit' => ''],
                ['label' => 'Bouclier maximal', 'value' => $definition['shield'] ?? 0, 'unit' => ''],
                ['label' => 'Précision de tir', 'value' => $definition['precision'] ?? 0, 'unit' => '%'],
                ['label' => 'Suivi de cible', 'value' => $definition['tracking'] ?? 0, 'unit' => '%'],
                ['label' => 'Capacité d’esquive', 'value' => $definition['evasion'] ?? 0, 'unit' => '%'],
                ['label' => 'Signature · faible = moins ciblé', 'value' => $definition['signature'] ?? 0, 'unit' => ''],
                ['label' => 'Capacité de soute', 'value' => $definition['cargo_capacity'] ?? 0, 'unit' => 'unités'],
                ['label' => 'Réservoir d’hydrogène', 'value' => $definition['fuel_capacity'] ?? 0, 'unit' => 'unités'],
            ];
            if (isset($definition['economic_unit'])) {
                $economic = $definition['economic_unit'];
                array_unshift($shipBenefits, ['label'=>'Production par unité active · cette colonie',
                    'value'=>$this->resources->economicUnitRate($planet, $definition, $context) * 3600,
                    'unit'=>$economic['resource']==='hydrogen'?'H / h':'E / h']);
            }
            if (isset($definition['secondary_attack'])) {
                $shipBenefits[] = ['label' => 'Tir secondaire anti-léger', 'value' => $definition['secondary_attack'], 'unit' => ''];
            }
            array_unshift($shipBenefits, ['text' => 'Vos recherches sont incluses · valeurs maximales avant combat.']);
            if ((float) ($definition['base_attack'] ?? 0) > 0) {
                $chance = 1.0;
                foreach ($definition['extra_shot_chances'] ?? [] as $extraChance) {
                    $chance *= 1.0 - (float) $extraChance;
                }
                $shipBenefits[] = ['label' => 'Chance de tir supplémentaire par tir de base', 'value' => (1.0 - $chance) * 100, 'unit' => '%'];
            }
            if (isset($definition['secondary_attack'])) {
                $shipBenefits[] = ['label' => 'Tirs secondaires par round', 'text' => ($definition['secondary_shots_min'] ?? 0) . '–' . ($definition['secondary_shots_max'] ?? 0)];
                $shipBenefits[] = ['label' => 'Correction de précision secondaire', 'value' => $definition['secondary_precision_modifier'] ?? 0, 'unit' => 'points'];
            }
            foreach ($shipBenefits as &$benefit) {
                if (isset($benefit['value'])) {
                    $benefit['decimals'] = (float) $benefit['value'] === floor((float) $benefit['value']) ? 0 : 2;
                }
            }
            unset($benefit);
            return $shipBenefits;
        }
        if ($group === 'defenses') {
            return [
                ...isset($definition['countered_class_label']) ? [['text' => 'Bonus contre : ' . $definition['countered_class_label'] . ' (' . implode(', ', $definition['countered_ship_labels'] ?? []) . ') · dégâts et chance de toucher ×1,30']] : [],
                ['label' => 'Attaque', 'value' => $definition['base_attack'] ?? 0, 'unit' => ''],
                ['label' => 'Structure', 'value' => $definition['hp'], 'unit' => ''],
                ['label' => 'Bouclier maximal', 'value' => $definition['shield'] ?? 0, 'unit' => ''],
            ];
        }
        return [];
    }

    private function technologyBenefits(
        array $definition,
        ?int $target,
        array $levels,
        Planet $planet,
    ): array
    {
        $effect = $definition['effect'] ?? [];
        $slug = $definition['slug'];
        $tier = (string) ($definition['_tier'] ?? 'basic');
        $ranks = $levels[$slug] ?? [];
        if (is_numeric($ranks)) {
            $ranks = ['basic' => (int) $ranks];
        }
        if ($target !== null) {
            $target = min($target, (int) ($effect['tier_max_levels'][$tier] ?? $definition['max_level'] ?? $target));
            // Compare uniquement le niveau proposé au dernier niveau déjà programmé.
            $ranks[$tier] = max(0, $target - 1);
        }
        $afterRanks = $ranks;
        if ($target !== null) {
            $afterRanks[$tier] = $target;
        }
        $maxLevel = max(1, (int) ($definition['max_level'] ?? 25));
        $type = $effect['type'] ?? null;
        if (in_array($slug, ['signature_masking', 'intelligence_countermeasures', 'tactical_intelligence'], true)) {
            // L’espionnage arrondit la somme des niveaux pondérés des trois rangs.
            $effectiveLevel = static function (array $ranks): int {
                $value = 0.0;
                foreach (GameCatalog::TIERS as $rank) {
                    $value += (int) ($ranks[$rank] ?? 0) * GameCatalog::tierFactor($rank);
                }
                return max(0, (int) round($value));
            };
            $before = $effectiveLevel($ranks);
            $after = $effectiveLevel($afterRanks);
            $settings = $this->catalog->all('espionage');
            if ($slug === 'tactical_intelligence') {
                return [[
                    'label' => 'Score de renseignement de vos sondes',
                    'before' => $before,
                    'after' => $after,
                    'unit' => 'points',
                ], [
                    'label' => 'Score de renseignement des sondes adverses',
                    'before' => -$before,
                    'after' => -$after,
                    'unit' => 'points',
                ], [
                    'label' => 'Préavis théorique des attaques entrantes',
                    'before' => \App\Service\Fleet\AttackDetection::warningSeconds(array_sum($ranks), PHP_INT_MAX) / 60,
                    'after' => \App\Service\Fleet\AttackDetection::warningSeconds(array_sum($afterRanks), PHP_INT_MAX) / 60,
                    'unit' => 'min',
                ]];
            }
            $weight = (int) ($settings[$slug === 'signature_masking' ? 'stealth_weight' : 'intelligence_countermeasures_weight'] ?? 10);
            $sign = $slug === 'signature_masking' ? -1 : 1;
            return [[
                'label' => $slug === 'signature_masking' ? 'Risque de détection de vos sondes' : 'Détection des sondes adverses',
                'before' => $sign * $weight * $before,
                'after' => $sign * $weight * $after,
                'unit' => 'points de %',
            ]];
        }
        if ($type === 'stat_bonus') {
            $cap = (float) ($effect['max_bonus'] ?? 0);
            $before = GameCatalog::diminishingBonus($ranks, $cap, $maxLevel);
            $after = GameCatalog::diminishingBonus($afterRanks, $cap, $maxLevel);
            $formula = $effect['formula'] ?? 'flat';
            $result = [];
            foreach ($effect['stats'] ?? [$effect['stat'] ?? null] as $stat) {
                if (!is_string($stat)) {
                    continue;
                }
                $label = match ($stat) {
                    'hp' => 'Structure',
                    'shield' => 'Bouclier',
                    'base_attack' => 'Attaque',
                    'precision' => 'Précision',
                    'tracking' => 'Suivi',
                    'evasion' => 'Esquive',
                    'signature' => 'Signature des vaisseaux et défenses',
                    'shots_min', 'shots_max' => 'Nombre de tirs',
                    'speed_system' => 'Vitesse dans le système',
                    'speed_galaxy' => 'Vitesse entre systèmes',
                    'fuel_capacity' => 'Réservoir',
                    'cargo_capacity' => 'Soute',
                    default => $stat,
                };
                $factor = ($formula === 'percent' ? 100 : 1) * (($effect['direction'] ?? 'increase') === 'decrease' ? -1 : 1);
                $scope = match ($effect['targets'] ?? '') {
                    'all_combat_units' => 'des vaisseaux et défenses',
                    'all_ships', 'combat_ships' => 'des vaisseaux',
                    default => '',
                };
                $result[] = [
                    'label' => $stat === 'signature' ? $label : trim($label . ' ' . $scope),
                    'before' => $before * $factor,
                    'after' => $after * $factor,
                    'unit' => $formula === 'percent' ? '%' : '',
                ];
            }
            return $result;
        }
        if (in_array($type, ['time_discount', 'cost_discount'], true)) {
            $cap = $type === 'time_discount' ? (float) ($this->catalog->rules()['time_reduction_max_percent'] ?? 70) : (float) ($this->catalog->rules()['cost_reduction_max_percent'] ?? 40);
            $before = GameCatalog::diminishingBonus($ranks, $cap, $maxLevel);
            $after = GameCatalog::diminishingBonus($afterRanks, $cap, $maxLevel);
            $label = match ([$type, $effect['targets'] ?? '']) {
                ['time_discount', 'buildings'] => 'Réduction du temps de construction des bâtiments',
                ['cost_discount', 'buildings'] => 'Réduction du coût de construction des bâtiments',
                ['time_discount', 'ships'] => 'Réduction du temps de construction des vaisseaux',
                ['cost_discount', 'ships'] => 'Réduction du coût de construction des vaisseaux',
                default => $type === 'time_discount' ? 'Réduction du temps de construction' : 'Réduction du coût de construction',
            };
            return [[
                'label' => $label,
                'before' => $before,
                'after' => $after,
                'unit' => '%',
            ]];
        }
        if ($type === 'extra_shot_chance') {
            $cap = (float) ($effect['max_bonus'] ?? 0.5);
            return [[
                'label' => 'Chance de tir principal supplémentaire',
                'before' => GameCatalog::diminishingBonus($ranks, $cap, $maxLevel) * 100,
                'after' => GameCatalog::diminishingBonus($afterRanks, $cap, $maxLevel) * 100,
                'unit' => '%',
            ]];
        }
        if ($type === 'defense_rebuild') {
            $cap = (float) ($this->catalog->rules()['defense_rebuild_max_percent'] ?? 40);
            return [[
                'label' => 'Reconstruction des défenses détruites',
                'before' => GameCatalog::diminishingBonus($ranks, $cap, $maxLevel),
                'after' => GameCatalog::diminishingBonus($afterRanks, $cap, $maxLevel),
                'unit' => '%',
            ]];
        }
        if ($type === 'planet_limit') {
            $limit = (int) $this->catalog->forUniverse($planet->solarSystem->galaxy->universe)['limits']['max_planets'];
            $before = GameCatalog::colonyLimit($ranks, $effect, $limit);
            $after = GameCatalog::colonyLimit($afterRanks, $effect, $limit);
            return [[
                'label' => 'Limite totale de planètes',
                'before' => $before,
                'after' => $after,
                'unit' => 'planètes',
            ]];
        }
        return [];
    }

    public function unlocks(
        string $group,
        string $slug,
        int $target,
        string $sourceTier = 'basic',
        ?\App\Entity\Universe $universe = null,
    ): array
    {
        if (!in_array($group, ['buildings', 'technologies'], true)) {
            return [];
        }
        $result = [];
        $catalog = $universe ? $this->catalog->forUniverse($universe)['catalog'] : [];
        foreach ([
            'buildings',
            'technologies',
            'ships',
            'defenses',
        ] as $kind) {
            foreach ($catalog[$kind] ?? $this->catalog->all($kind) as $candidate) {
                foreach (GameCatalog::TIERS as $tier) {
                    foreach ($this->requirements->forTier($candidate, $tier)[$group] ?? [] as $requirement) {
                        if ($requirement['slug'] !== $slug || ($requirement['tier'] ?? 'basic') !== $sourceTier || (int) $requirement['level'] > $target) {
                            continue;
                        }
                        $definition = $this->catalog->withTier($candidate, $tier);
                        $result[$kind . ':' . $candidate['slug'] . ':' . $tier] = [
                            'group' => $kind,
                            'slug' => $candidate['slug'],
                            'tier' => $tier,
                            'tierLabel' => $definition['_tier_label'],
                            'label' => $definition['label'],
                            'requiredLevel' => $requirement['level'],
                        ];
                    }
                }
            }
        }
        return array_values($result);
    }

    public function tree(
        array $levels,
        array $technologies,
        ?PlayerUniverse $membership = null,
        ?Planet $planet = null,
    ): array
    {
        $nodes = [];
        $depths = [];
        $visiting = [];
        $catalog = $membership ? $this->catalog->forUniverse($membership->universe)['catalog'] : [];
        // Construire tous les liens avant les profondeurs : un prérequis peut être déclaré plus loin dans le catalogue.
        foreach ([
            'buildings',
            'technologies',
            'ships',
            'defenses',
        ] as $kind) {
            foreach (GameCatalog::TIERS as $tier) {
                foreach ($catalog[$kind] ?? $this->catalog->all($kind) as $slug => $base) {
                    $key = $kind . ':' . $slug . '-' . $tier;
                    $nodes[$key] = $this->technologyNode($kind, $slug, $tier, $base, $levels, $technologies, $catalog, $membership, $planet);
                }
            }
        }
        // Réutiliser les profondeurs connues et refuser les cycles plutôt que masquer un catalogue invalide.
        $depth = function (string $key) use (&$depth, &$depths, &$visiting, $nodes): int {
            if (isset($depths[$key])) {
                return $depths[$key];
            }
            if (isset($visiting[$key])) {
                throw new \LogicException('Cycle dans l’arbre technologique : ' . $key);
            }
            $visiting[$key] = true;
            $parentDepths = [];
            foreach ($nodes[$key]['parents'] as $parent) {
                if (isset($nodes[$parent])) {
                    $parentDepths[] = $depth($parent);
                }
            }
            unset($visiting[$key]);
            return $depths[$key] = $parentDepths ? max($parentDepths) + 1 : 0;
        };
        $columns = [];
        $lookup = static fn (string $candidate): ?array => $nodes[$candidate] ?? null;
        foreach ($nodes as $key => $node) {
            $node['depth'] = $depth($key);
            $node['recommendation'] = TechnologyGuideStep::resolve($key, $lookup);
            $columns[$node['depth']][] = $node;
        }
        ksort($columns);
        return $columns;
    }

    /** Ne prépare que l’objectif suivi et ses prérequis utiles, jamais une vue complète du Guide. */
    public function goalSummary(
        PlayerUniverse $membership,
        Planet $planet,
        string $group,
        string $slug,
        string $tier,
        array $levels,
        array $technologies,
    ): ?array {
        $catalog = $this->catalog->forUniverse($membership->universe)['catalog'];
        $nodes = [];
        $lookup = function (string $key) use (&$nodes, $catalog, $levels, $technologies, $membership, $planet): ?array {
            if (array_key_exists($key, $nodes)) {
                return $nodes[$key];
            }
            [$group, $slug, $tier] = preg_split('/[:-]/', $key);
            $definition = $catalog[$group][$slug] ?? null;
            return $nodes[$key] = $definition === null ? null : $this->technologyNode(
                $group, $slug, $tier, $definition, $levels, $technologies, $catalog, $membership, $planet,
            );
        };
        $key = $group . ':' . $slug . '-' . $tier;
        $node = $lookup($key);
        if ($node === null) {
            return null;
        }
        return [
            'label' => $node['definition']['label'],
            'tier' => $node['definition']['_tier_label'],
            'recommendation' => TechnologyGuideStep::resolve($key, $lookup),
        ];
    }

    private function technologyNode(
        string $kind,
        string $slug,
        string $tier,
        array $base,
        array $levels,
        array $technologies,
        array $catalog,
        ?PlayerUniverse $membership,
        ?Planet $planet,
    ): array {
        $key = $kind . ':' . $slug . '-' . $tier;
        $definition = $this->catalog->withTier($base, $tier);
        $requirements = $this->requirements->forTier($definition, $tier, 1);
        $prerequisites = [];
        $parents = [];
        foreach (['buildings' => $levels, 'technologies' => $technologies] as $group => $owned) {
            foreach ($requirements[$group] ?? [] as $requirement) {
                $requiredTier = $requirement['tier'] ?? 'basic';
                $have = (int) ($owned[$requirement['slug']][$requiredTier] ?? ($requiredTier === 'basic' && is_numeric($owned[$requirement['slug']] ?? null) ? $owned[$requirement['slug']] : 0));
                $requiredDefinition = $this->catalog->withTier($catalog[$group][$requirement['slug']] ?? $this->catalog->get($group, $requirement['slug']), $requiredTier);
                $prerequisites[] = [
                    'group' => $group,
                    'slug' => $requirement['slug'],
                    'tier' => $requiredTier,
                    'label' => $requiredDefinition['label'],
                    'level' => $requirement['level'],
                    'current' => $have,
                    'met' => $have >= (int) $requirement['level'],
                ];
                $parents[] = $group . ':' . $requirement['slug'] . '-' . $requiredTier;
            }
        }
        foreach ($requirements['ships'] ?? [] as $entry) {
            $shipSlug = is_array($entry) ? $entry['slug'] : $entry;
            $shipTier = is_array($entry) ? $entry['tier'] ?? 'basic' : 'basic';
            $parents[] = 'ships:' . $shipSlug . '-' . $shipTier;
            $ship = $this->catalog->withTier($catalog['ships'][$shipSlug] ?? $this->catalog->get('ships', $shipSlug), $shipTier);
            $met = !$this->requirements->check($this->requirements->forTier($ship, $shipTier), $levels, $technologies, [], $catalog ?: null);
            $prerequisites[] = [
                'group' => 'ships',
                'slug' => $shipSlug,
                'tier' => $shipTier,
                'label' => $ship['label'],
                'level' => null,
                'current' => null,
                'met' => $met,
            ];
        }
        $owned = $kind === 'buildings' ? $levels : ($kind === 'technologies' ? $technologies : []);
        $value = $owned[$slug] ?? 0;
        $acquired = $membership && $planet && $this->access->acquired($membership, $planet, $kind, $slug, $tier);
        $available = (!$membership || $this->access->isCurrent($membership))
            && ($acquired || !$this->requirements->check($requirements, $levels, $technologies, [], $catalog ?: null));
        $level = is_array($value) ? (int) ($value[$tier] ?? 0) : ($tier === 'basic' ? (int) $value : 0);
        $maximum = (int) ($definition['effect']['tier_max_levels'][$tier] ?? $definition['max_level'] ?? PHP_INT_MAX);
        $localRequirement = null;
        if ($kind === 'technologies' && $level < $maximum && !$this->access->hasLocalResearchLaboratory($levels, $tier)) {
            // Un accès de recherche appartient à l’empire, mais chaque lancement exige un labo sur place.
            $laboratory = $this->catalog->withTier($catalog['buildings']['lab'] ?? $this->catalog->get('buildings', 'lab'), $tier);
            $localRequirement = [
                'group' => 'buildings', 'slug' => 'lab', 'tier' => $tier, 'label' => $laboratory['label'],
                'level' => 1, 'current' => 0, 'met' => false,
            ];
        }

        return [
            'group' => $kind,
            'key' => $key,
            'definition' => $definition,
            'tier' => $tier,
            'slug' => $slug,
            'level' => $level,
            'prerequisites' => $prerequisites,
            'parents' => array_values(array_unique($parents)),
            'acquired' => (bool) $acquired,
            'available' => $available,
            'localRequirement' => $localRequirement,
            'maximum' => $maximum,
            'canAdvance' => $available && $localRequirement === null && $level < $maximum,
        ];
    }
}
