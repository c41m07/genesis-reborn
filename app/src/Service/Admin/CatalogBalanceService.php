<?php
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog,Player,PlayerUniverse,PlanetBuilding,PlayerTechnology,QueueItem,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use Psr\Clock\ClockInterface;
use App\Service\Queue\OperationCalculator;

final class CatalogBalanceService
{
    private const GROUPS=['buildings','technologies','ships','defenses'];

    public function __construct(private GameCatalog $catalog,private GameRepository $repo,private GameManager $manager,private ResourceService $resources,private ClockInterface $clock,private OperationCalculator $calculator){}

    public function entries(Universe $universe, ?string $family = null, ?string $slugFilter = null, bool $withFields = true):array
    {
        $config=$this->catalog->forUniverse($universe);$result=[];
        foreach($family === null ? self::GROUPS : [$family] as $group) foreach($config['catalog'][$group] as $slug=>$definition){
            if ($slugFilter !== null && $slug !== $slugFilter) continue;
            $fields=[];
            foreach($withFields ? $this->fieldSchema($group,$definition) : [] as $path=>$schema){$value=$this->read($definition,$path);$fields[$path]=$schema+['path'=>$path,'value'=>$value,'reference'=>$this->read($this->catalog->get($group,$slug),$path),'overridden'=>$this->has($universe->gameSettings['catalog_overrides'][$group][$slug]??[],$path)];}
            $result[$group][]=['slug'=>$slug,'label'=>$definition['tier_labels']['basic']??$definition['label'],'tier_labels'=>$definition['tier_labels']??[],'fields'=>$fields,'inactive_fields'=>$withFields?$this->inactiveFields($group,$definition):[],'revision'=>$withFields?$this->revision($universe,$group,$slug):null];
        }
        return $result;
    }

    public function save(Player $admin,int $universeId,string $group,string $slug,array $input,string $expectedRevision,string $reason,?int $restoreSource = null):void
    {
        if(!in_array($group,self::GROUPS,true))throw new \DomainException('Groupe de catalogue invalide.');
        $this->manager->transaction(function()use($admin,$universeId,$group,$slug,$input,$expectedRevision,$reason,$restoreSource):void{
            $universe=$this->repo->find(Universe::class,$universeId);
            if(!$universe instanceof Universe||$universe->status==='closed')throw new \DomainException('Univers introuvable ou fermé.');
            $this->manager->lock($universe);
            if ($universe->status === 'closed') throw new \DomainException('Univers fermé.');
            $this->assertRevision($universe,$group,$slug,$expectedRevision);
            $reason=$this->validateReason($reason);
            $before=$this->catalog->getForUniverse($universe,$group,$slug);
            // La source historique est relue après le verrou : aucun champ reçu du navigateur
            // ne peut remplacer les valeurs approuvées dans l’aperçu.
            if ($restoreSource !== null) {
                $input = $this->historicalValues($universe, $group, $slug, $restoreSource);
            }
            $values = $this->validatedValues($group, $slug, $input);
            if(isset($values['max_level']))$this->assertMaxLevelCompatible($universe,$group,$slug,$values['max_level']);
            $planets=$this->repo->planets($universe);foreach($planets as $planet){$this->manager->lock($planet);$this->resources->materialize($planet,$this->clock->now());}
            $settings=$universe->gameSettings;$settings['catalog_overrides']??=[];$settings['catalog_overrides'][$group]??=[];$override=$settings['catalog_overrides'][$group][$slug]??[];foreach($values as $path=>$value)$this->write($override,$path,$value);$settings['catalog_overrides'][$group][$slug]=$override;$universe->gameSettings=$settings;
            $this->advanceRevision($universe,$group,$slug);
            $this->manager->flush();
            foreach($planets as $planet)$this->resources->refreshProduction($planet);
            $this->audit($admin,$universeId,$group,$slug,$values,$before,$this->catalog->getForUniverse($universe,$group,$slug),$reason,$restoreSource);$this->manager->flush();
        });
    }

    /**
     * Devis hypothétique en mémoire : aucun verrou, écriture ou matérialisation.
     * Les tableaux proposés sont des copies, jamais une mutation de l'univers géré par Doctrine.
     */
    public function preview(Universe $universe, string $group, string $slug, array $input, string $expectedRevision, string $tier, int $level, int $quantity): array
    {
        if (!in_array($group, self::GROUPS, true)) {
            throw new \DomainException('Groupe de catalogue invalide.');
        }
        $this->assertRevision($universe, $group, $slug, $expectedRevision);
        $values = $this->validatedValues($group, $slug, $input);
        if (!in_array($tier, GameCatalog::TIERS, true) || $level < 1 || $level > 100 || $quantity < 1 || $quantity > 1000) {
            throw new \DomainException('Scénario invalide : rang connu, niveau de 1 à 100 et quantité de 1 à 1 000 requis.');
        }
        $unitGroup = in_array($group, ['ships', 'defenses'], true);
        if (($unitGroup && $level !== 1) || (!$unitGroup && $quantity !== 1)) {
            throw new \DomainException('Un bâtiment ou une recherche se simule à l’unité ; les unités se simulent au niveau 1.');
        }
        $current = $this->catalog->forUniverse($universe);
        $proposed = $current;
        foreach ($values as $path => $value) {
            $this->write($proposed['catalog'][$group][$slug], $path, $value);
        }
        $before = $this->catalog->withTier($current['catalog'][$group][$slug], $tier);
        $after = $this->catalog->withTier($proposed['catalog'][$group][$slug], $tier);
        return [
            'tier' => $tier,
            'level' => $level,
            'quantity' => $quantity,
            'before' => $this->calculator->calculate($before, $group, $level, $quantity, [], $current, []),
            'after' => $this->calculator->calculate($after, $group, $level, $quantity, [], $proposed, []),
            'time_multiplier' => $current['multipliers'][match ($group) {
                'buildings' => 'building_time', 'technologies' => 'research_time', default => 'ship_build_time',
            }],
        ];
    }

    /** Validation numérique et cohérence des tirs communes au devis et à l'écriture. */
    private function validatedValues(string $group, string $slug, array $input): array
    {
        $input = $this->flatten($input);
        $schema = $this->fieldSchema($group, $this->catalog->get($group, $slug));
        if (array_diff(array_keys($input), array_keys($schema)) || array_diff(array_keys($schema), array_keys($input))) {
            throw new \DomainException('Paramètres catalogue incomplets ou inconnus.');
        }
        $values = [];
        foreach ($schema as $path => $rule) {
            $value = filter_var($input[$path] ?? null, FILTER_VALIDATE_FLOAT);
            if ($value === false || !is_finite((float) $value) || $value < $rule['min'] || $value > $rule['max']) {
                throw new \DomainException('Valeur hors limites pour '.$rule['label'].'.');
            }
            $values[$path] = $rule['integer'] ? (int) $value : (float) $value;
            if ($rule['integer'] && (float) $values[$path] !== (float) $value) {
                throw new \DomainException('Un entier est requis pour '.$rule['label'].'.');
            }
        }
        if (isset($values['shots_min'], $values['shots_max']) && $values['shots_min'] > $values['shots_max']) {
            throw new \DomainException('Le minimum de tirs ne peut pas dépasser le maximum.');
        }
        return $values;
    }

    public function reset(Player $admin,int $universeId,string $group,string $slug,string $expectedRevision,string $reason):void
    {
        if(!in_array($group,self::GROUPS,true))throw new \DomainException('Groupe de catalogue invalide.');
        $this->manager->transaction(function()use($admin,$universeId,$group,$slug,$expectedRevision,$reason):void{
            $universe=$this->repo->find(Universe::class,$universeId);if(!$universe instanceof Universe||$universe->status==='closed')throw new \DomainException('Univers introuvable ou fermé.');$this->manager->lock($universe);
            if ($universe->status === 'closed') throw new \DomainException('Univers fermé.');
            $this->assertRevision($universe,$group,$slug,$expectedRevision);
            $reason=$this->validateReason($reason);
            $before=$this->catalog->getForUniverse($universe,$group,$slug);
            $this->catalog->get($group,$slug);$planets=$this->repo->planets($universe);foreach($planets as $planet){$this->manager->lock($planet);$this->resources->materialize($planet,$this->clock->now());}
            unset($universe->gameSettings['catalog_overrides'][$group][$slug]);if(($universe->gameSettings['catalog_overrides'][$group]??[])===[])unset($universe->gameSettings['catalog_overrides'][$group]);if(($universe->gameSettings['catalog_overrides']??[])===[])unset($universe->gameSettings['catalog_overrides']);foreach($planets as $planet)$this->resources->refreshProduction($planet);
            $this->advanceRevision($universe,$group,$slug);
            $this->audit($admin,$universeId,$group,$slug,['reset_to_reference'=>true],$before,$this->catalog->getForUniverse($universe,$group,$slug),$reason);$this->manager->flush();
        });
    }


    /**
     * Restauration partielle volontaire : seuls les champs éditables de la fiche sont repris.
     * Le contexte non restauré est conservé et doit rester strictement compatible.
     */
    private function restorationContext(Universe $universe, string $group, string $slug): array
    {
        $config = $this->catalog->forUniverse($universe);
        unset($config['catalog']);
        $definition = $this->catalog->getForUniverse($universe, $group, $slug);
        foreach (array_keys($this->fieldSchema($group, $this->catalog->get($group, $slug))) as $path) {
            $this->write($definition, $path, null);
        }
        return [
            'reference' => $this->catalog->get($group, $slug),
            'rules' => $this->catalog->rules(),
            'tiers' => $this->catalog->tiers(),
            'universe' => $config,
            'fixed_definition' => $definition,
        ];
    }

    public function historicalValues(Universe $universe, string $group, string $slug, int $source): array
    {
        $entry = $this->repo->find(AdminAuditLog::class, $source);
        if (!$entry instanceof AdminAuditLog || $entry->action !== 'universe_catalog_balance'
            || $entry->targetType !== 'universe' || $entry->targetId !== $universe->id
            || ($entry->details['group'] ?? null) !== $group || ($entry->details['slug'] ?? null) !== $slug) {
            throw new \DomainException('Version introuvable pour cette fiche et cet univers.');
        }
        $version = $entry->details['version'] ?? [];
        $schema = $this->fieldSchema($group, $this->catalog->get($group, $slug));
        if (($version['format'] ?? null) !== 1 || ($version['fields'] ?? null) !== array_keys($schema)
            || !is_array($entry->details['before'] ?? null) || !is_array($entry->details['after'] ?? null)
            || !is_array($version['context'] ?? null)) {
            throw new \DomainException('Trace ancienne ou incomplète : restauration indisponible.');
        }
        // JSON sans conservation des fractions nulles neutralise 1/1.0 après stockage Doctrine.
        if (json_encode($version['context'], JSON_THROW_ON_ERROR) !== json_encode($this->restorationContext($universe, $group, $slug), JSON_THROW_ON_ERROR)) {
            throw new \DomainException('Le contexte de règles ou de référence a changé : restauration incompatible.');
        }
        $values = [];
        foreach ($schema as $path => $rule) {
            if (!$this->has($entry->details['before'], $path) || !$this->has($entry->details['after'], $path)) {
                throw new \DomainException('Instantané incomplet : restauration indisponible.');
            }
            $values[$path] = $this->read($entry->details['after'], $path);
        }
        return $this->validatedValues($group, $slug, $values);
    }

    public function previewRestoration(Universe $universe, string $group, string $slug, int $source): array
    {
        if ($universe->status === 'closed') {
            throw new \DomainException('Univers fermé.');
        }
        $values = $this->historicalValues($universe, $group, $slug, $source);
        if (isset($values['max_level'])) {
            $this->assertMaxLevelCompatible($universe, $group, $slug, $values['max_level']);
        }
        $revision = $this->revision($universe, $group, $slug);
        $fields = $this->entries($universe, $group, $slug)[$group][0]['fields'];
        $comparison = [];
        foreach ($fields as $path => $field) {
            $comparison[] = ['label' => $field['label'], 'before' => $field['value'], 'after' => $values[$path]];
        }
        return [
            'revision' => $revision, 'comparison' => $comparison,
            'quote' => $this->preview($universe, $group, $slug, $values, $revision, 'basic', 1, 1),
        ];
    }

    private function fieldSchema(string $group,array $definition):array
    {
        $map=match($group){
            'buildings'=>['max_level'=>['Niveau maximum',1,100,true],'base_build_time'=>['Temps de base (s)',1,100000000,false],'build_time_scaling'=>['Croissance de durée',0.01,10,false],'costs_scaling'=>['Croissance des coûts',0.01,10,false],'production_per_level'=>['Production par niveau',0,100000000,false],'build_time_reduction_percent_per_level'=>['Réduction par niveau (%)',0,90,false]],
            'technologies'=>['max_level'=>['Niveau maximum',1,100,true],'research_time_base'=>['Temps de recherche de base (s)',1,100000000,false],'research_time_scaling'=>['Croissance de durée',0.01,10,false],'costs_scaling'=>['Croissance des coûts',0.01,10,false],'effect.value_per_level'=>['Effet par niveau',0,10000,false],'effect.max_bonus'=>['Plafond du bonus tous rangs confondus',0,10000,false]],
            default=>['hp'=>['Structure (PV)',1,100000000,false],'precision'=>['Précision',0,1000,false],'tracking'=>['Poursuite',0,1000,false],'evasion'=>['Évasion',0,1000,false],'signature'=>['Signature',0,1000,false],'base_attack'=>['Dégâts de base',0,100000000,false],'shots_min'=>['Tirs minimum',1,100,true],'shots_max'=>['Tirs maximum',1,100,true],'build_time_base'=>['Temps de construction (s)',1,100000000,false],'speed_system'=>['Vitesse système',0,100000000,false],'speed_galaxy'=>['Vitesse galaxie',0,100000000,false],'fuel_travel_system'=>['Carburant système',0,100000000,false],'fuel_travel_galaxy'=>['Carburant galaxie',0,100000000,false],'fuel_orbit'=>['Carburant orbite',0,100000000,false],'cargo_capacity'=>['Capacité de fret',0,100000000,false],'fuel_capacity'=>['Capacité carburant',0,100000000,false]],
        };
        $inactive = $this->inactiveFields($group, $definition);
        $sharedBonus = in_array($definition['effect']['type'] ?? null, ['stat_bonus', 'extra_shot_chance'], true);
        $result = [];
        foreach ($map as $path => [$label, $min, $max, $integer]) {
            if (!$this->has($definition, $path) || isset($inactive[$path])) continue;
            if ($path === 'effect.value_per_level' && $sharedBonus) continue;
            if ($path === 'effect.max_bonus' && !$sharedBonus) continue;
            if ($path === 'effect.max_bonus') $label = 'Plafond commun aux trois rangs (0,15 = 15 %)';
            $result[$path] = ['label' => $label, 'min' => $min, 'max' => $max, 'integer' => $integer];
        }
        if(isset($definition['cost_profiles'])&&is_array($definition['cost_profiles']))foreach($definition['cost_profiles'] as $tier=>$costs)foreach($costs as $resource=>$amount)$result['cost_profiles.'.$tier.'.'.$resource]=['label'=>'Coût '.$tier.' · '.ucfirst((string)$resource),'min'=>0,'max'=>1000000000,'integer'=>true];
        return $result;
    }

    /** Les courbes communes priment sur les anciens coefficients de fiche. */
    private function inactiveFields(string $group, array $definition): array
    {
        $curves = $this->catalog->rules()['progression_curves'] ?? [];
        $fields = [];
        $durationCurve = match ($group) {
            'buildings' => 'building_duration',
            'technologies' => 'research_duration',
            default => null,
        };
        $durationField = $group === 'buildings' ? 'build_time_scaling' : 'research_time_scaling';
        if ($durationCurve !== null && !empty($curves[$durationCurve]) && isset($definition[$durationField])) {
            $fields[$durationField] = 'La croissance de durée utilise la courbe commune '.$durationCurve.', pas le coefficient historique de cette fiche.';
        }
        $costCurve = match ($group) {
            'buildings' => isset($definition['production_resource']) && !empty($curves['production_building_cost'])
                ? 'production_building_cost' : 'building_cost',
            'technologies' => 'research_cost',
            default => null,
        };
        if ($costCurve !== null && !empty($curves[$costCurve]) && isset($definition['costs_scaling'])) {
            $fields['costs_scaling'] = 'La croissance des coûts utilise la courbe commune '.$costCurve.', pas le coefficient historique de cette fiche.';
        }
        return $fields;
    }

    private function assertMaxLevelCompatible(Universe $universe,string $group,string $slug,int $max):void
    {
        if($group==='buildings'){$planets=$this->repo->planets($universe);foreach($this->repo->all(PlanetBuilding::class,['planet'=>$planets]) as $row)if($row->slug===$slug&&$row->level>$max)throw new \DomainException('Le niveau maximum ne peut pas être inférieur aux bâtiments existants.');$type='building';}
        elseif($group==='technologies'){$members=$this->repo->all(PlayerUniverse::class,['universe'=>$universe]);foreach($this->repo->all(PlayerTechnology::class,['playerUniverse'=>$members]) as $row)if($row->slug===$slug&&$row->level>$max)throw new \DomainException('Le niveau maximum ne peut pas être inférieur aux recherches existantes.');$type='research';}
        else return;
        $members=$this->repo->all(PlayerUniverse::class,['universe'=>$universe]);foreach($this->repo->all(QueueItem::class,['playerUniverse'=>$members,'queueType'=>$type,'itemSlug'=>$slug]) as $row)if(($row->itemLevel??0)>$max)throw new \DomainException('Le niveau maximum ne peut pas être inférieur aux commandes déjà en file.');
    }
    /** Empreinte du contexte réellement présenté, et non simple date du formulaire. */
    public function revision(Universe $universe, string $group, string $slug): string
    {
        return hash('sha256', json_encode([
            'universe' => $universe->id,
            'group' => $group,
            'slug' => $slug,
            'generation' => $universe->gameSettings['catalog_revisions'][$group][$slug] ?? 0,
            'config' => $this->catalog->forUniverse($universe),
            'rules' => $this->catalog->rules(),
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function assertRevision(Universe $universe, string $group, string $slug, string $expected): void
    {
        // Le verrou recharge l'univers avant ce contrôle : deux formulaires ne peuvent
        // pas écraser silencieusement leurs modifications, même après un aller-retour aux références.
        if (!hash_equals($this->revision($universe, $group, $slug), $expected)) {
            throw new \DomainException('Le catalogue a changé depuis l’ouverture du formulaire. Rechargez les valeurs actuelles avant de modifier cette fiche.');
        }
    }

    private function advanceRevision(Universe $universe, string $group, string $slug): void
    {
        $universe->gameSettings['catalog_revisions'][$group][$slug] =
            1 + (int) ($universe->gameSettings['catalog_revisions'][$group][$slug] ?? 0);
    }

    private function validateReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
            throw new \DomainException('Indiquez un motif de 5 à 1 000 caractères.');
        }
        return $reason;
    }

    private function audit(Player $admin, int $universeId, string $group, string $slug, array $values, array $before, array $after, string $reason, ?int $restoreSource = null): void
    {
        $entry = new AdminAuditLog();
        $entry->actor = $admin;
        $entry->action = 'universe_catalog_balance';
        $entry->targetType = 'universe';
        $entry->targetId = $universeId;
        $entry->details = [
            'group' => $group,
            'slug' => $slug,
            'values' => $values,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'restore_source' => $restoreSource,
            'version' => [
                'format' => 1,
                'context' => $this->restorationContext($this->repo->find(Universe::class, $universeId), $group, $slug),
                'fields' => array_keys($this->fieldSchema($group, $this->catalog->get($group, $slug))),
            ],
        ];
        $entry->createdAt = $this->clock->now();
        $this->manager->save($entry);
    }
    private function flatten(array $data,string $prefix=''):array{$result=[];foreach($data as $key=>$value){$path=$prefix===''?(string)$key:$prefix.'.'.$key;if(is_array($value))$result+=$this->flatten($value,$path);else$result[$path]=$value;}return $result;}
    private function has(array $data,string $path):bool{$value=$data;foreach(explode('.',$path) as $part){if(!is_array($value)||!array_key_exists($part,$value))return false;$value=$value[$part];}return true;}
    private function read(array $data,string $path):mixed{$value=$data;foreach(explode('.',$path) as $part)$value=$value[$part];return $value;}
    private function write(array &$data,string $path,mixed $value):void{$parts=explode('.',$path);$last=array_pop($parts);$cursor=&$data;foreach($parts as $part){$cursor[$part]??=[];$cursor=&$cursor[$part];}$cursor[$last]=$value;}
}
