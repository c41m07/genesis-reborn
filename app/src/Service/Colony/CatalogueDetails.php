<?php
namespace App\Service\Colony;

use App\Entity\Planet;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Requirement\RequirementChecker;

/** Informations de présentation, calculées à partir des règles exécutées par le jeu. */
final class CatalogueDetails
{
    public function __construct(
        private GameCatalog $catalog,
        private ResourceService $resources,
        private TechnologyEffectCalculator $technologyEffects,
        private RequirementChecker $requirements,
    ) {}

    public function describe(array $d,string $group,int $target,Planet $planet,array $buildings,array $tech):array
    {
        $details=['intro'=>'','scope'=>'','sections'=>[],'performance'=>[],
            'acquired'=>$planet->owner!==null && isset($planet->owner->catalogUnlocks[\App\Service\Requirement\CatalogAccess::key($planet,$group,$d['slug'],$d['_tier'])]),
            'requirements'=>$this->accessRequirements($d,$group,$target,$planet,$buildings,$tech),
            'unlocks'=>$this->relatedUnlocks($d,$group,$planet)];
        if($group==='buildings')return array_replace($details,$this->building($d,$target,$planet,$buildings));
        if($group==='technologies')return array_replace($details,$this->technology($d,$target,$planet,$tech));
        return array_replace($details,$this->unit($d,$group,$planet,$buildings,$tech));
    }

    private function section(string $title,array $paragraphs=[],array $rows=[]):array
    {
        return ['title'=>$title,'paragraphs'=>$paragraphs,'rows'=>$rows];
    }

    private function building(array $d,int $target,Planet $planet,array $levels):array
    {
        $slug=$d['slug'];$tier=$d['_tier'];$next=$levels;$next[$slug][$tier]=$target;
        $sections=[];$rules=$this->catalog->rules();
        if(isset($d['production_resource'])){
            $resource=$d['production_resource'];$label=mb_strtolower($rules['resources'][$resource]);
            $intro=match($resource){'electricity'=>'Produit de l’électricité sur cette colonie.','antimatter'=>'Produit de l’antimatière sur cette colonie.','hydrogen'=>'Produit de l’hydrogène sur cette colonie.','nano'=>'Produit des nanomatériaux sur cette colonie.',default=>'Produit du '.$label.' sur cette colonie.'};
            $rows=[['label'=>'Production nominale actuelle','value'=>$this->resources->rate($planet,$resource,0,$levels)*3600,'unit'=>'/ h'],
                ['label'=>'Production nominale après amélioration','value'=>$this->resources->rate($planet,$resource,0,$next)*3600,'unit'=>'/ h']];
            $before=$this->resources->factoryConsumption($planet,$slug,$levels);$after=$this->resources->factoryConsumption($planet,$slug,$next);
            foreach(['electricity'=>'Électricité','hydrogen'=>'Hydrogène'] as $r=>$name)if(max($before[$r],$after[$r])>0){
                $rows[]=['label'=>$name.($r==='electricity'?' consommée':' consommé').' avant amélioration','value'=>$before[$r]*3600,'unit'=>'/ h'];
                $rows[]=['label'=>$name.($r==='electricity'?' consommée':' consommé').' après amélioration','value'=>$after[$r]*3600,'unit'=>'/ h'];
            }
            $sections[]=$this->section('Production et entretien',[
                'Ces débits sont nominaux, pour la position de cette planète et la vitesse de cet univers. Les productions des trois rangs s’additionnent.',
                'Les gains de production et les consommations suivent les facteurs '.$this->number($this->catalog->productionTierFactor('basic')).' / '.$this->number($this->catalog->productionTierFactor('intermediate')).' / '.$this->number($this->catalog->productionTierFactor('superior')).' selon le rang. Les prix et les durées suivent leur propre progression.',
                $resource==='electricity'?'La centrale alimente les usines et recharge le stock électrique. Sa production ne baisse pas lorsque le stock d’électricité est vide.':
                    'Quand le stock électrique est épuisé et que la production ne couvre plus les besoins, les usines ralentissent. Leur rendement de secours minimal est de '.$this->number((float)($rules['minimum_factory_efficiency']??.1)*100).' %.',
                'La barre de ressources affiche le débit net réel, après consommation, et la productivité des usines actives. Une usine dont le stockage de sortie est plein suspend ses consommations inutiles ; les productions d’hydrogène et d’électricité continuent à alimenter les autres usines si nécessaire.'
            ],$rows);
            if($resource==='antimatter')$sections[]=$this->section('Approvisionnement en hydrogène',[
                'Chaque unité d’antimatière produite consomme '.$this->number((float)($d['hydrogen_per_output']??$rules['antimatter_hydrogen_per_unit']??8)).' unités d’hydrogène.',
                'Sans stock d’hydrogène, la production d’antimatière est limitée par l’hydrogène disponible par heure. Sans aucun apport, elle s’arrête ; le rendement de secours électrique ne compense pas ce manque.'
            ]);
        }elseif(isset($d['storage_resource'])){
            $resource=$d['storage_resource'];$ratio=max(0.0,min(1.0,(float)($rules['combat_loot_protected_storage_ratio']??.1)));
            $before=$this->resources->capacity($planet,$resource,$levels);$after=$this->resources->capacity($planet,$resource,$next);
            $intro='Augmente la capacité de stockage de '.mb_strtolower($rules['resources'][$resource]).' sur cette colonie.';
            $sections[]=$this->section('Stockage et protection du pillage',[
                'Les capacités apportées par les trois rangs s’additionnent au stockage initial de la colonie.',
                $this->number($ratio*100).' % de la capacité totale définit le seuil protégé du pillage. Seules les ressources effectivement présentes peuvent être protégées.',
                'La zone verte de la barre de stock représente ce seuil. Les livraisons qui dépassent la capacité de stockage sont perdues.'
            ],[
                ['label'=>'Capacité actuelle','value'=>$before,'unit'=>'unités'],['label'=>'Capacité après amélioration','value'=>$after,'unit'=>'unités'],
                ['label'=>'Seuil protégé actuel','value'=>$before*$ratio,'unit'=>'unités'],['label'=>'Seuil protégé après amélioration','value'=>$after*$ratio,'unit'=>'unités'],
                ['label'=>'Protection supplémentaire','value'=>($after-$before)*$ratio,'unit'=>'unités'],
            ]);
        }else{
            $intro=match($slug){'lab'=>'Accélère les nouvelles recherches lancées depuis cette colonie.','shipyard'=>'Accélère la construction de nouveaux vaisseaux sur cette colonie.','fortress'=>'Augmente la structure des défenses au sol de cette colonie.',default=>$d['description']??''};
            $sections[]=$this->section('Fonctionnement',match($slug){
                'lab'=>['Les trois rangs contribuent ensemble à la réduction du temps de recherche, avec des rendements décroissants.','La recherche est partagée entre vos colonies de cet univers, mais le laboratoire utilisé est celui de la colonie où elle est lancée.','Les durées des opérations déjà en file restent celles enregistrées à leur lancement.'],
                'shipyard'=>['Les trois rangs contribuent ensemble à la réduction du temps de construction des vaisseaux, avec des rendements décroissants.','Les défenses partagent la file du chantier, mais ne bénéficient pas de cette réduction de durée.','Les durées des opérations déjà en file restent celles enregistrées à leur lancement.'],
                'fortress'=>['Les trois rangs contribuent ensemble au bonus de structure des défenses au sol. Ce bonus se combine avec vos recherches de blindage.','Le bunker ne tire pas lui-même. Il ne renforce ni la structure des vaisseaux ni les boucliers des défenses.'],default=>[]});
        }
        return ['intro'=>$intro,'scope'=>'Cette colonie · rangs développés séparément','sections'=>$sections];
    }

    private function technology(array $d,int $target,Planet $planet,array $levels):array
    {
        $effect=$d['effect']??[];$type=$effect['type']??'';$slug=$d['slug'];$rules=$this->catalog->rules();$sections=[];
        $scope=match($effect['targets']??''){'all_combat_units'=>'Vaisseaux et défenses au sol','all_ships'=>'Tous vos vaisseaux','combat_ships'=>'Vaisseaux armés · tirs principaux','buildings'=>'Constructions de bâtiments','ships'=>'Constructions de vaisseaux',default=>'Votre empire dans cet univers'};
        $intro=match($slug){
            'engineering_foundations'=>'Réduit la durée des nouvelles constructions de bâtiments.',
            'nanomaterial_synthesis'=>'Réduit les ressources nécessaires aux nouvelles constructions de bâtiments.',
            'naval_engineering'=>'Réduit les ressources nécessaires à la construction de nouveaux vaisseaux.',
            'structural_armor'=>'Augmente la structure de vos vaisseaux et de vos défenses au sol.',
            'shield_harmonics'=>'Augmente le bouclier maximal de vos vaisseaux et de vos défenses au sol.',
            'evasive_navigation'=>'Augmente l’esquive de vos vaisseaux et de vos défenses au sol.',
            'weapon_calibration'=>'Augmente la précision des armes de vos vaisseaux.',
            'target_acquisition'=>'Augmente le suivi de cible de vos vaisseaux.',
            'weapons_engineering'=>'Augmente les dégâts des armes principales et secondaires de vos vaisseaux.',
            'signature_reduction'=>'Réduit la signature de vos vaisseaux et de vos défenses, qui sont ainsi moins susceptibles d’être choisis comme cible en combat.',
            'rapid_fire_cycle'=>'Donne à chaque tir principal de vos vaisseaux armés une chance de générer un tir supplémentaire.',
            'cryogenic_reserves'=>'Augmente la capacité des réservoirs d’hydrogène de tous vos vaisseaux.',
            'modular_cargo_bays'=>'Augmente la capacité des soutes de tous vos vaisseaux qui en possèdent une.',
            'vector_propulsion'=>'Augmente la vitesse de vos vaisseaux dans un système et entre les systèmes.',
            'automated_defense_repair'=>'Reconstruit une partie de vos défenses détruites après un combat.',
            'colonial_expansion'=>'Augmente le nombre total de colonies que vous pouvez posséder dans cet univers.',
            'signature_masking'=>'Réduit le risque de détection de vos sondes pendant une mission d’espionnage.',
            'intelligence_countermeasures'=>'Augmente le risque de détection des sondes qui espionnent vos colonies.',
            'tactical_intelligence'=>'Améliore vos rapports d’espionnage et réduit les renseignements obtenus par les sondes adverses.',
            default=>$d['description']??''};
        if(in_array($slug,['signature_masking','intelligence_countermeasures','tactical_intelligence'],true)){
            $sections=$this->espionageSections();
        }elseif($type==='stat_bonus'){
            $paragraphs=match($effect['stat']??''){
                'precision','tracking','evasion'=>['La précision et le suivi de l’attaquant sont opposés à l’esquive de la cible. Le rang des unités intervient également. Le coefficient de contre multiplie ensuite la chance calculée ; les chances finales restent comprises entre 15 % et 95 %.','Le bonus augmente la statistique concernée. Une hausse de précision ou de suivi n’ajoute pas directement autant de points aux chances finales de toucher.','Un bonus proportionnel ne modifie pas une statistique de départ égale à zéro.'],
                'base_attack'=>['Le bonus s’applique à chaque tir principal et, si le vaisseau en possède une, à son attaque secondaire anti-léger. Le nombre de tirs reste inchangé.','Les coefficients de contre modifient à la fois les dégâts et les chances de toucher. Les boucliers absorbent les dégâts avant la structure.'],
                'hp'=>['La structure est la résistance de l’unité une fois ses boucliers traversés. Une unité dont la structure tombe à zéro est détruite.','Sur les défenses au sol, ce bonus se combine avec celui du bunker de la planète.'],
                'shield'=>['Le bonus augmente la réserve maximale de bouclier. Les dégâts sont d’abord retirés au bouclier, puis à la structure.','La recharge entre deux rounds représente '.$this->number((float)($rules['shield_recharge_percent_per_round']??5)).' % du bouclier maximal ; une capacité plus élevée augmente donc aussi la quantité rechargée.'],
                'signature'=>['La signature intervient dans le choix de la cible par les armes ennemies. Une faible signature réduit le poids de l’unité dans ce choix, sans garantir qu’elle sera épargnée.','Elle ne modifie pas les chances de toucher une fois la cible choisie. La discrétion des sondes en mission d’espionnage dépend de l’Écran de brouillage.'],
                'fuel_capacity'=>['Les réservoirs dédiés transportent uniquement du carburant. Ils ne peuvent pas servir de soute à marchandises.','Le carburant aller-retour et le stationnement prévu sont réservés au départ. Si les réservoirs ne suffisent pas, le carburant supplémentaire utilise la soute disponible.','Cette recherche augmente la capacité, sans réduire la consommation du trajet.'],
                'cargo_capacity'=>['La soute transporte les ressources d’une mission de transport ou le butin d’un combat. Le fret volontaire est réservé aux transports entre vos colonies.','Le carburant qui dépasse la capacité des réservoirs dédiés occupe une partie de la soute. La place restante dépend donc de la mission.','Les vaisseaux dont la capacité de soute est nulle ne gagnent pas de soute avec ce bonus.'],
                default=>['Les deux indices de vitesse progressent : trajet dans le système et trajet entre systèmes.','Une flotte est limitée par son vaisseau le plus lent pour chaque type de trajet. Le gain de temps dépend donc de toute sa composition, de la route et de la vitesse choisie au départ.','Cette recherche ne réduit pas directement la consommation de carburant.']};
            $sections[]=$this->section('Utilisation du bonus',$paragraphs);
        }elseif($type==='extra_shot_chance'){
            $sections[]=$this->section('Tirs supplémentaires',[
                'Un test est effectué pour chaque tir principal de base d’un vaisseau armé. S’il réussit, un tir principal supplémentaire est ajouté pour ce round.',
                'Les tirs secondaires ne bénéficient pas de ce test. Un tir supplémentaire ne déclenche pas à son tour une nouvelle série de tests.',
                'Le résultat varie d’un round à l’autre : la recherche augmente une probabilité, pas un nombre garanti de tirs.'
            ]);
        }elseif($type==='defense_rebuild'){
            $sections[]=$this->section('Après le combat',[
                'Le taux s’applique aux défenses détruites, séparément pour chaque type et rang. Le nombre reconstruit est arrondi à l’entier inférieur.',
                'Exemple : un taux de 10 % appliqué à 9 défenses détruites ne reconstruit aucune unité ; sur 10 défenses détruites, il en reconstruit une.',
                'Cette recherche ne répare pas les vaisseaux et ne soigne pas les unités entre deux rounds.'
            ]);
        }elseif($type==='planet_limit'){
            $sections[]=$this->colonizationSection($d,$target,$planet,$levels);
        }elseif(in_array($type,['time_discount','cost_discount'],true)){
            $sections[]=$this->section('Application',[
                $type==='time_discount'?'La réduction est calculée au lancement de chaque nouvelle construction de bâtiment.':'La réduction est calculée au lancement de chaque nouvelle construction concernée, sur les ressources facturées. Les montants sont arrondis vers le haut.',
                'Les opérations déjà en file conservent leur coût payé et leur durée enregistrée.',
                ($effect['targets']??'')==='ships'?'Les défenses et les bâtiments ne bénéficient pas de cette réduction.':'Les recherches, vaisseaux et défenses ne bénéficient pas de cette réduction.'
            ]);
        }
        $sections[]=$this->section('Progression des rangs',[
            in_array($slug,['signature_masking','intelligence_countermeasures','tactical_intelligence'],true)?
                'Les niveaux Basique, Intermédiaire et Supérieur sont pondérés par 1, 1,25 et 1,5625. Leur somme est arrondie pour obtenir le niveau effectif d’espionnage.':
                'Les trois rangs sont développés séparément et contribuent à un bonus commun à rendements décroissants. Le gain du prochain niveau dépend donc aussi des niveaux possédés dans les autres rangs.',
            'La recherche est partagée entre toutes vos colonies de cet univers. Un changement de colonie ne remet pas ses niveaux à zéro.'
        ]);
        return ['intro'=>$intro,'scope'=>$scope,'sections'=>$sections];
    }

    private function espionageSections():array
    {
        $s=$this->catalog->all('espionage');
        return [
            $this->section('Détection des sondes',[
                'Le risque part de '.$this->number((float)($s['base_detection_chance']??50)).' %. Chaque niveau effectif de Discipline du secret de la cible ajoute '.$this->number((float)($s['intelligence_countermeasures_weight']??10)).' points ; chaque niveau effectif d’Écran de brouillage de l’émetteur retire '.$this->number((float)($s['stealth_weight']??10)).' points.',
                'Chaque sonde envoyée au-delà de la première ajoute '.$this->number((float)($s['extra_probe_weight']??1)).' point(s) de risque. Chaque sonde présente chez la cible ajoute '.$this->number((float)($s['local_probe_weight']??1)).' point(s), y compris les sondes en défense ou en orbite.',
                'Le risque final reste compris entre '.$this->number((float)($s['min_detection_chance']??5)).' % et '.$this->number((float)($s['max_detection_chance']??95)).' %. Si les sondes sont détectées, aucun renseignement n’est obtenu et le propriétaire de la cible est averti.'
            ]),
            $this->section('Contenu du rapport',[
                'Si les sondes passent inaperçues, le score vaut '.$this->number((float)($s['report_score_base']??1)).' + votre niveau effectif d’Analyse tactique − celui de la cible + le nombre de sondes supplémentaires.',
                'Chaque point révèle une catégorie supplémentaire, dans l’ordre : ressources, bâtiments, recherches, flottes, défenses. Au-delà de cinq catégories, le rapport est déjà complet ; un score nul ne révèle aucune catégorie.',
                'Envoyer davantage de sondes peut donc améliorer le rapport, mais augmente aussi le risque de détection.'
            ]),
            $this->section('Préparer la mission',[
                'L’espionnage exige une ou plusieurs sondes, sans escorte, vers une planète connue qui ne vous appartient pas.',
                'Toutes les sondes envoyées en espionnage sont consommées à l’arrivée, que la mission soit détectée ou réussie. Une mission d’exploration consomme une seule sonde pour découvrir un système.'
            ])
        ];
    }

    private function colonizationSection(array $d,int $target,Planet $planet,array $levels):array
    {
        $ranks=$levels[$d['slug']]??[];if(is_numeric($ranks))$ranks=['basic'=>(int)$ranks];
        $effect=$d['effect']??[];$tierCaps=$effect['tier_max_levels']??[];$tierCap=(int)($tierCaps[$d['_tier']]??$d['max_level']??25);$caps='Basique 5, Intermédiaire 10, Supérieur 15';
        $universe=$this->catalog->forUniverse($planet->solarSystem->galaxy->universe);$cap=(int)$universe['limits']['max_planets'];
        $limit=fn(array $ranks):int=>GameCatalog::colonyLimit($ranks,$effect,$cap);
        $after=$ranks;$after[$d['_tier']]=min($target,$tierCap);$current=$limit($ranks);$next=$limit($after);
        return $this->section('Emplacements de colonies',[
            'Chaque niveau ajoute directement +1 emplacement de colonie.',
            'Limites : '.$caps.'. Maximum : 31 planètes au total, planète de départ incluse.',
            $current>=$cap?'Le plafond de cet univers est atteint.':($next>$current?'Le prochain niveau apporte +1 emplacement.':'Le plafond global est atteint.'),
            'La planète de départ, les colonies fondées et les planètes conquises occupent toutes un emplacement.',
            'Fonder une colonie exige une planète vierge connue et consomme un vaisseau colonie. Une conquête exige un vaisseau colonie survivant et une cible PNJ conquérable.'
        ],[['label'=>'Limite totale actuelle','value'=>$current,'unit'=>'colonies'],['label'=>'Limite après ce niveau','value'=>$next,'unit'=>'colonies'],['label'=>'Plafond de cet univers','value'=>$cap,'unit'=>'colonies']]);
    }

    private function unit(array $d,string $group,Planet $planet,array $buildings,array $tech):array
    {
        $universe=$planet->solarSystem->galaxy->universe;$defense=$group==='defenses';$rules=$this->catalog->rules();
        $effective=$defense?$this->technologyEffects->applyToDefenses([$d],$tech,$universe)[0]:$this->technologyEffects->applyToShips([$d],$tech,$universe)[0];
        if($defense){
            $fortress=$this->catalog->getForUniverse($universe,'buildings','fortress');$effect=$fortress['effect']??[];
            if(($effect['targets']??'')==='ground_defenses'&&($effect['stat']??'')==='hp')$effective['hp']*=1+GameCatalog::diminishingBonus($buildings['fortress']??[],(float)($effect['max_bonus']??25),(int)($this->catalog->get('buildings','fortress')['max_level']??25))/100;
        }
        $performance=[];
        foreach(['base_attack'=>'Attaque principale par tir','hp'=>'Structure maximale','shield'=>'Bouclier maximal','precision'=>'Précision','tracking'=>'Suivi de cible','evasion'=>'Esquive','signature'=>'Signature'] as $key=>$label){
            $performance[]=['label'=>$label,'base'=>(float)($d[$key]??0),'effective'=>(float)($effective[$key]??0),'unit'=>in_array($key,['precision','tracking','evasion'],true)?'%':''];
        }
        if(isset($d['secondary_attack']))$performance[]=['label'=>'Attaque secondaire par tir','base'=>(float)$d['secondary_attack'],'effective'=>(float)$effective['secondary_attack'],'unit'=>''];
        $armed=(float)($d['base_attack']??0)>0;$sections=[];
        $combatRows=[['label'=>'Tirs principaux de base par round','text'=>$armed?($d['shots_min'].' à '.$d['shots_max']):'Aucun']];
        $chance=(float)($effective['extra_shot_chances'][0]??0);
        if($armed)$combatRows[]=['label'=>'Chance de tir principal supplémentaire','value'=>$chance*100,'unit'=>'% par tir de base'];
        if(isset($d['secondary_attack'])){
            $combatRows[]=['label'=>'Tirs secondaires par round','text'=>($d['secondary_shots_min']??0).' à '.($d['secondary_shots_max']??0)];
            $combatRows[]=['label'=>'Correction de précision des tirs secondaires','value'=>(float)($d['secondary_precision_modifier']??0),'unit'=>'points'];
        }
        $paragraphs=['Les valeurs « Avec vos bonus » incluent vos recherches'.($defense?' et le bunker de cette planète':'').'. Elles décrivent les capacités maximales, avant les pertes du combat.',
            'Une faible signature réduit la probabilité d’être choisi comme cible. Précision et suivi sont opposés à l’esquive adverse. Le coefficient de contre multiplie ensuite la chance de toucher, qui reste comprise entre 15 % et 95 %.'];
        if(isset($d['secondary_attack']))$paragraphs[]='L’arme secondaire vise uniquement les vaisseaux légers. Son bonus d’attaque suit la recherche d’armement, mais elle ne bénéficie pas des tirs supplémentaires du Séquenceur de salves.';
        if(!$armed)$paragraphs[]='Cette unité ne tire pas. Ses statistiques de précision et de suivi ne lui donnent pas d’arme.';
        $sections[]=$this->section('Comportement en combat',$paragraphs,$combatRows);
        $matchups=[];$groupDescriptions=[];$counters=$this->catalog->all('combat_modifiers');
        foreach($counters['relations'][$d['slug']]??[] as $slug=>$factor){
            if((float)$factor===1.0||(!isset($this->catalog->all('ships')[$slug])&&!isset($counters['target_groups'][$slug])&&$slug!=='ground_defense'))continue;
            $matchups[]=['label'=>$this->catalog->combatLabel($slug),'text'=>'×'.$this->number((float)$factor).' dégâts et chance de toucher'];
            if(isset($counters['target_groups'][$slug]['description']))$groupDescriptions[]=$counters['target_groups'][$slug]['description'];
        }
        if($matchups)$sections[]=$this->section('Contres et faiblesses',['Ces coefficients multiplient les dégâts d’un tir réussi et la chance de toucher déjà calculée, pour les armes principales comme secondaires. Les chances finales restent comprises entre 15 % et 95 %.','Ces noms désignent les familles de vaisseaux : la relation s’applique aux trois rangs, quel que soit le nom de leur variante.','Exemple : un contre ×1,30 fait passer une chance de 50 % à 65 %, avec aussi 30 % de dégâts supplémentaires par impact. Il ne garantit pas que l’arme choisira cette cible.',...$groupDescriptions],$matchups);
        $shieldText='Entre deux rounds, les unités survivantes rechargent '.$this->number((float)($rules['shield_recharge_percent_per_round']??5)).' % de leur bouclier maximal, sans le dépasser.';
        $shieldText.=($rules['restore_survivor_shields_after_combat']??true)?' Les boucliers des survivants sont restaurés après le combat.':'';
        $sections[]=$this->section('Boucliers',[$shieldText]);
        if($defense){
            $repair=$this->catalog->get('technologies','automated_defense_repair');$rebuild=GameCatalog::diminishingBonus($tech['automated_defense_repair']??[],(float)($rules['defense_rebuild_max_percent']??40),(int)($repair['max_level']??25));
            $sections[]=$this->section('Déploiement et reconstruction',[
                'Installation fixe sur cette planète : elle ne peut ni voyager ni rejoindre une flotte. Chaque rang possède sa propre réserve d’unités.',
                'Après le combat, la recherche Drones réparateurs reconstruit une partie des défenses détruites. Le nombre est arrondi à l’entier inférieur pour chaque type et rang.'
            ],[['label'=>'Taux actuel de reconstruction','value'=>$rebuild,'unit'=>'% des pertes']]);
        }else{
            $mobility=[];
            foreach(['speed_system'=>'Vitesse dans le système','speed_galaxy'=>'Vitesse entre systèmes','cargo_capacity'=>'Soute disponible avant chargement','fuel_capacity'=>'Réservoir dédié à l’hydrogène'] as $key=>$label)$mobility[]=['label'=>$label,'value'=>(float)($effective[$key]??0),'unit'=>in_array($key,['cargo_capacity','fuel_capacity'],true)?'unités':'indice'];
            $mobility[]=['label'=>'Consommation en trajet normal','value'=>(float)($d['fuel_travel_system']??0),'unit'=>'H / unité de distance'];
            $mobility[]=['label'=>'Consommation en trajet entre systèmes','value'=>(float)($d['fuel_travel_galaxy']??0),'unit'=>'H / unité de distance'];
            $mobility[]=['label'=>'Consommation en stationnement','value'=>(float)($d['fuel_orbit']??0),'unit'=>'H / h'];
            $sections[]=$this->section('Déplacement et chargement',[
                'Les vitesses et capacités ci-dessous incluent vos recherches. La flotte utilise la vitesse de son vaisseau le plus lent pour chaque segment du trajet.',
                'Les réservoirs sont réservés au carburant. Le carburant aller-retour et le stationnement prévu sont réservés dès le départ ; le surplus utilise la soute et réduit la place disponible pour le fret.',
                'Le coût réel dépend de la route, de la vitesse choisie et de la composition de la flotte. Consultez l’estimation de mission avant le départ.'
            ],$mobility);
            if($d['slug']==='probe')$sections=[...$sections,...$this->espionageSections()];
            elseif($d['slug']==='colony_ship')$sections[]=$this->section('Fondation et conquête',[
                'Fonde une colonie sur une planète vierge connue, si votre limite de colonies le permet. Un vaisseau colonie est consommé à la fondation.',
                'Les ressources de départ de la nouvelle colonie sont multipliées par le rang du vaisseau consommé : ×1 en Basique, ×1,25 en Intermédiaire et ×1,5625 en Supérieur. Si plusieurs rangs sont présents, le rang le plus bas disponible est consommé.',
                'Pour conquérir une planète PNJ, au moins un vaisseau colonie doit survivre au combat. Les planètes détenues par d’autres joueurs ne peuvent pas être conquises.'
            ]);
            elseif((float)($d['cargo_capacity']??0)>0)$sections[]=$this->section('Transport',[
                'Transporte volontairement des ressources entre vos propres colonies connues. Les ressources livrées au-delà de la capacité de stockage de la destination sont perdues.',
                'Une soute plus grande augmente aussi la capacité disponible pour le butin si le vaisseau survit au combat. La capacité de fret réelle est calculée après réservation du carburant.'
            ]);
        }
        $intro=$defense?'Défense fixe spécialisée contre '.implode(', ',$d['countered_ship_labels']??[]).'.':match($d['slug']){
            'probe'=>'Sonde non armée pour découvrir des systèmes et espionner des planètes connues.',
            'colony_ship'=>'Vaisseau de soutien permettant de fonder une colonie ou d’accompagner une conquête.',
            'transport'=>'Vaisseau de logistique doté d’une soute pour le fret, le carburant supplémentaire et le butin.',
            'stealth_fighter'=>'Vaisseau de combat à faible signature, moins susceptible d’être choisi comme cible.',
            default=>explode(' Une ',$d['description']??'Vaisseau de combat.')[0]};
        return ['intro'=>$intro,'scope'=>$defense?'Défense de cette colonie · réserve propre à ce rang':'Vaisseau de votre empire · réserve propre à ce rang','sections'=>$sections,'performance'=>$performance];
    }

    private function accessRequirements(array $d,string $group,int $target,Planet $planet,array $buildings,array $tech):array
    {
        $requirements=$this->requirements->forTier($d,$d['_tier'],$target);
        $result=[];
        foreach(['buildings'=>$buildings,'technologies'=>$tech] as $kind=>$owned)foreach($requirements[$kind]??[] as $r){
            $tier=$r['tier']??'basic';$definition=$this->catalog->getTierForUniverse($planet->solarSystem->galaxy->universe,$kind,$r['slug'],$tier);
            $current=$owned[$r['slug']]??[];$current=is_numeric($current)?($tier==='basic'?(int)$current:0):(int)($current[$tier]??0);
            $key=$kind.':'.$r['slug'].':'.$tier;
            if(isset($result[$key])&&$result[$key]['required']>=(int)$r['level'])continue;
            $result[$key]=['label'=>$definition['label'],'tier'=>$definition['_tier_label'],'required'=>(int)$r['level'],'current'=>$current,'met'=>$current>=(int)$r['level'],'maximum'=>$definition['max_level']??null,'unreachable'=>isset($definition['max_level'])&&(int)$r['level']>(int)$definition['max_level']];
        }
        foreach($requirements['ships']??[] as $r){
            $slug=is_array($r)?$r['slug']:$r;$tier=is_array($r)?($r['tier']??'basic'):'basic';$definition=$this->catalog->getTierForUniverse($planet->solarSystem->galaxy->universe,'ships',$slug,$tier);
            $result[]=['label'=>'Accès à '.$definition['label'],'tier'=>$definition['_tier_label'],'required'=>null,'current'=>null,'met'=>$this->requirements->check(['ships'=>[$r]],$buildings,$tech,[],$this->catalog->forUniverse($planet->solarSystem->galaxy->universe)['catalog'])===[],'maximum'=>null,'unreachable'=>false];
        }
        return array_values($result);
    }

    private function relatedUnlocks(array $d,string $group,Planet $planet):array
    {
        if(!in_array($group,['buildings','technologies'],true))return [];
        $result=[];$universe=$this->catalog->forUniverse($planet->solarSystem->galaxy->universe);
        foreach($universe['catalog'] as $kind=>$definitions)foreach($definitions as $base)foreach(GameCatalog::TIERS as $tier){
            $definition=$this->catalog->withTier($base,$tier);
            foreach($this->requirements->forTier($definition,$tier)[$group]??[] as $r){
                if($r['slug']!==$d['slug']||($r['tier']??'basic')!==$d['_tier'])continue;
                $key=$kind.':'.$base['slug'].':'.$tier.':'.$r['level'];
                $result[$key]=['label'=>$definition['label'],'group'=>$kind,'slug'=>$base['slug'],'tier'=>$tier,'tierLabel'=>$definition['_tier_label'],'requiredLevel'=>(int)$r['level'],'unreachable'=>isset($d['max_level'])&&(int)$r['level']>(int)$d['max_level']];
            }
        }
        usort($result,static fn(array $a,array $b):int=>$a['requiredLevel']<=>$b['requiredLevel']);
        return array_values($result);
    }

    private function number(float $value):string
    {
        return rtrim(rtrim(number_format($value,2,',',' '),'0'),',');
    }
}
