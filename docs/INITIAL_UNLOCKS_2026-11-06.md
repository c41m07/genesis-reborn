# Déblocages initiaux permanents — 6 novembre 2026

Implémenté sur **dev** ; aucune fusion vers Alpha ni déploiement. Migration et reprise appliquées à la base locale le 6 novembre 2026 après validation explicite de l’utilisateur.

## Lecture

Les conditions débloquent chaque rang dès son niveau 1, puis l’accès est conservé. Aucun nouveau prérequis pendant ses niveaux. Chaque colonne donne toutes les conditions ensemble, sans doublons.

**B** = Basique, **I** = Intermédiaire, **S** = Supérieur. Le chiffre est le niveau du prérequis, pas un futur palier de l’élément. Les noms indiquent les familles ; leurs variantes gardent leurs libellés dans le jeu.

Recherches : accès du joueur dans cet univers, utilisable depuis les autres colonies même sans laboratoire local. Bâtiments, vaisseaux, défenses : accès du joueur sur cette colonie seulement. Aucun rang précédent du même élément n’est exigé implicitement. Coûts, durées, ressources, plafonds et limites de file restent applicables.

## Choix de progression

Démarrage accessible : métal, nano, hydrogène, centrale, réserves courantes et laboratoire sans prérequis. Le chantier reprend seulement son premier ancien jalon léger. Le réacteur demande un premier développement hydrogène/naval, allégé par rapport à son ancien palier 2. Son stockage s’ouvre au premier réacteur. Les autres anciens paliers disparaissent sans être déplacés intégralement au niveau 1.

Les conditions militaires initiales restent graduelles. Les rangs avancés gardent leurs spécialités : généralement B5 puis I10, ou I5 si le plafond réel est 5. Le bunker Intermédiaire demandé pour l’anti-capital Supérieur passe de 15 à 10. Huit accès hors plafond sont corrigés sans relever les plafonds ni modifier les bonus.

## Bâtiments — 13

| Élément | Basique | Intermédiaire | Supérieur | Pourquoi |
|---|---|---|---|---|
| Bunker de défense (`fortress`) | Chantier d’assemblage B 3 ; Laboratoire colonial B 2 ; Blindage multicouche B 2 | Chantier d’assemblage B 3 ; Laboratoire colonial B 5 ; Blindage multicouche B 2 ; Canons à énergie focalisée B 5 | Chantier d’assemblage B 3 ; Laboratoire colonial B 2 ; Blindage multicouche B 2 ; Canons à énergie focalisée I 10 ; Laboratoire colonial I 10 | Accès militaire initial conservé ; retrait du futur verrou blindage 5 et réparation 3 au niveau 2. |
| Laboratoire colonial (`lab`) | Aucun | Génie civil colonial B 5 | Génie civil colonial I 10 | Entrée libre dans la recherche ; suppression du verrou sur ses propres améliorations. |
| Piège à antimatière (`mine_antimatter`) | Raffinerie d’hydrogène B 3 ; Laboratoire colonial B 2 ; Architecture navale B 2 | Raffinerie d’hydrogène B 3 ; Laboratoire colonial B 5 ; Architecture navale B 5 | Raffinerie d’hydrogène B 3 ; Laboratoire colonial B 2 ; Architecture navale B 2 ; Architecture navale I 10 ; Laboratoire colonial I 10 | Premier jalon industriel après hydrogène 3 et recherche navale 2, allégé par rapport à l’ancien palier. |
| Raffinerie d’hydrogène (`mine_hydrogen`) | Aucun | Propulseurs vectoriels B 5 ; Laboratoire colonial B 5 | Propulseurs vectoriels I 10 ; Laboratoire colonial I 10 | Carburant et alimentation du réacteur sans recherche navale préalable. |
| Mine à ciel ouvert (`mine_metal`) | Aucun | Génie civil colonial B 5 ; Laboratoire colonial B 5 | Génie civil colonial I 10 ; Laboratoire colonial I 10 | Ressource de départ sans verrou scientifique. |
| Atelier de nanofabrication (`mine_nano`) | Aucun | Métallurgie des nanomatériaux B 5 ; Laboratoire colonial B 5 | Métallurgie des nanomatériaux I 10 ; Laboratoire colonial I 10 | Ressource de départ ; suppression du blocage au niveau 2. Rangs avancés liés à la synthèse. |
| Centrale nucléaire (`power_plant`) | Aucun | Génie civil colonial B 5 ; Laboratoire colonial B 5 | Génie civil colonial I 10 ; Laboratoire colonial I 10 | Énergie disponible dès le départ, puis approfondissement du génie civil. |
| Chantier d’assemblage (`shipyard`) | Mine à ciel ouvert B 2 ; Architecture navale B 1 | Mine à ciel ouvert B 2 ; Architecture navale B 5 | Mine à ciel ouvert B 2 ; Architecture navale B 1 ; Architecture navale I 10 | Seulement le premier ancien jalon léger : métal 2 et architecture navale 1, sans cumuler les paliers 6 et 10. |
| Cage magnétique (`storage_antimatter`) | Piège à antimatière B 1 | Piège à antimatière B 1 ; Métallurgie des nanomatériaux B 5 ; Laboratoire colonial B 5 | Piège à antimatière B 1 ; Métallurgie des nanomatériaux I 10 ; Laboratoire colonial I 10 | Stockage ouvert au premier réacteur, sans réacteur 2 ni synthèse 2. |
| Batterie de colonie (`storage_electricity`) | Aucun | Architecture navale B 5 ; Laboratoire colonial B 5 | Architecture navale I 10 ; Laboratoire colonial I 10 | Stockage accessible dès le départ pour éviter un blocage de capacité ; rangs avancés liés à la spécialité existante. |
| Réservoir cryogénique (`storage_hydrogen`) | Aucun | Propulseurs vectoriels B 5 ; Laboratoire colonial B 5 | Propulseurs vectoriels I 10 ; Laboratoire colonial I 10 | Stockage accessible dès le départ pour éviter un blocage de capacité ; rangs avancés liés à la spécialité existante. |
| Dépôt de minerais (`storage_metal`) | Aucun | Métallurgie des nanomatériaux B 5 ; Laboratoire colonial B 5 | Métallurgie des nanomatériaux I 10 ; Laboratoire colonial I 10 | Stockage accessible dès le départ pour éviter un blocage de capacité ; rangs avancés liés à la spécialité existante. |
| Coffre de nanomatériaux (`storage_nano`) | Aucun | Génie civil colonial B 5 ; Laboratoire colonial B 5 | Génie civil colonial I 10 ; Laboratoire colonial I 10 | Stockage accessible dès le départ pour éviter un blocage de capacité ; rangs avancés liés à la spécialité existante. |

## Recherches — 19

| Élément | Basique | Intermédiaire | Supérieur | Pourquoi |
|---|---|---|---|---|
| Drones réparateurs (`automated_defense_repair`) | Laboratoire colonial B 1 | Laboratoire colonial B 5 ; Architecture navale B 5 | Laboratoire colonial B 1 ; Architecture navale I 10 ; Laboratoire colonial I 10 | Reconstruction des défenses accessible avec le laboratoire ; rangs avancés liés à l’architecture navale, sans verrou sur les niveaux du bunker. |
| Protocoles de colonisation (`colonial_expansion`) | Laboratoire colonial B 1 | Laboratoire colonial B 5 ; Propulseurs vectoriels B 5 | Laboratoire colonial B 1 ; Propulseurs vectoriels I 10 ; Laboratoire colonial I 10 | Premiers emplacements accessibles tôt ; rangs suivants liés à la propulsion. Quotas et missions conservés. |
| Réservoirs cryogéniques (`cryogenic_reserves`) | Laboratoire colonial B 1 | Laboratoire colonial B 5 ; Propulseurs vectoriels B 5 | Laboratoire colonial B 1 ; Propulseurs vectoriels I 10 ; Laboratoire colonial I 10 | Réservoir accessible tôt après le laboratoire ; approfondissement par la propulsion. |
| Génie civil colonial (`engineering_foundations`) | Laboratoire colonial B 1 | Laboratoire colonial B 5 ; Architecture navale B 5 | Laboratoire colonial B 1 ; Architecture navale I 10 ; Laboratoire colonial I 10 | Première recherche civile après le laboratoire ; ouvre les branches spécialisées. |
| Manœuvres d’évitement (`evasive_navigation`) | Propulseurs vectoriels B 3 ; Télémétrie de poursuite B 2 ; Laboratoire colonial B 1 | Propulseurs vectoriels B 5 ; Télémétrie de poursuite B 2 ; Laboratoire colonial B 5 | Propulseurs vectoriels B 3 ; Télémétrie de poursuite B 2 ; Laboratoire colonial B 1 ; Propulseurs vectoriels I 10 ; Laboratoire colonial I 10 | Mobilité défensive liée à la propulsion et au suivi. |
| Discipline du secret (`intelligence_countermeasures`) | Laboratoire colonial B 1 | Laboratoire colonial B 5 ; Analyse tactique B 5 | Laboratoire colonial B 1 ; Analyse tactique I 5 ; Laboratoire colonial I 10 | Contre-espionnage accessible tôt ; rang supérieur fondé sur un renseignement atteignable. |
| Soute compartimentée (`modular_cargo_bays`) | Laboratoire colonial B 1 | Laboratoire colonial B 5 ; Protocoles de colonisation B 5 | Laboratoire colonial B 1 ; Protocoles de colonisation I 10 ; Laboratoire colonial I 10 | Logistique accessible tôt ; rangs avancés liés à l’expansion et à ses plafonds réels. |
| Métallurgie des nanomatériaux (`nanomaterial_synthesis`) | Laboratoire colonial B 3 ; Génie civil colonial B 3 | Laboratoire colonial B 5 ; Génie civil colonial B 5 | Laboratoire colonial B 3 ; Génie civil colonial B 3 ; Génie civil colonial I 10 ; Laboratoire colonial I 10 | Branche industrielle après génie civil 3, distincte de la branche navale. |
| Architecture navale (`naval_engineering`) | Laboratoire colonial B 2 ; Génie civil colonial B 2 | Laboratoire colonial B 5 ; Génie civil colonial B 5 | Laboratoire colonial B 2 ; Génie civil colonial B 2 ; Génie civil colonial I 10 ; Laboratoire colonial I 10 | Spécialisation après les bases civiles, préparant les premiers vaisseaux et leur économie. |
| Séquenceur de salves (`rapid_fire_cycle`) | Canons à énergie focalisée B 7 ; Télémétrie de poursuite B 4 ; Laboratoire colonial B 1 | Canons à énergie focalisée B 7 ; Télémétrie de poursuite B 5 ; Laboratoire colonial B 5 | Canons à énergie focalisée B 7 ; Télémétrie de poursuite B 4 ; Laboratoire colonial B 1 ; Télémétrie de poursuite I 10 ; Laboratoire colonial I 10 | Spécialisation militaire avancée ; conditions vérifiées seulement à l’ouverture. |
| Générateurs de bouclier accordés (`shield_harmonics`) | Blindage multicouche B 3 ; Canons à énergie focalisée B 5 ; Laboratoire colonial B 1 | Blindage multicouche B 3 ; Canons à énergie focalisée B 5 ; Laboratoire colonial B 5 ; Architecture navale B 5 | Blindage multicouche B 3 ; Canons à énergie focalisée B 5 ; Laboratoire colonial B 1 ; Architecture navale I 10 ; Laboratoire colonial I 10 | Protection avancée après blindage et armement. |
| Écran de brouillage (`signature_masking`) | Laboratoire colonial B 1 | Laboratoire colonial B 5 ; Analyse tactique B 5 | Laboratoire colonial B 1 ; Analyse tactique I 5 ; Laboratoire colonial I 10 | Brouillage accessible tôt ; analyse tactique Intermédiaire 5 remplace le seuil 10 impossible. |
| Revêtement absorbant (`signature_reduction`) | Laboratoire colonial B 1 | Laboratoire colonial B 5 ; Écran de brouillage B 5 | Laboratoire colonial B 1 ; Écran de brouillage I 5 ; Laboratoire colonial I 10 | Signature accessible avec le laboratoire ; seuil avancé de brouillage ramené à son plafond 5. |
| Blindage multicouche (`structural_armor`) | Métallurgie des nanomatériaux B 3 ; Canons à énergie focalisée B 3 ; Laboratoire colonial B 1 | Métallurgie des nanomatériaux B 3 ; Canons à énergie focalisée B 5 ; Laboratoire colonial B 5 | Métallurgie des nanomatériaux B 3 ; Canons à énergie focalisée B 3 ; Laboratoire colonial B 1 ; Canons à énergie focalisée I 10 ; Laboratoire colonial I 10 | Blindage associant matériaux et armement ; ne bloque plus les améliorations futures du bunker. |
| Analyse tactique (`tactical_intelligence`) | Laboratoire colonial B 1 | Laboratoire colonial B 5 ; Écran de brouillage B 5 | Laboratoire colonial B 1 ; Écran de brouillage I 5 ; Laboratoire colonial I 10 | Renseignement accessible tôt ; brouillage Intermédiaire 5 remplace le seuil 10 impossible. |
| Télémétrie de poursuite (`target_acquisition`) | Viseurs balistiques B 2 ; Architecture navale B 3 ; Laboratoire colonial B 1 | Viseurs balistiques B 5 ; Architecture navale B 3 ; Laboratoire colonial B 5 | Viseurs balistiques B 2 ; Architecture navale B 3 ; Laboratoire colonial B 1 ; Viseurs balistiques I 10 ; Laboratoire colonial I 10 | Suivi après précision et architecture navale, utile aux unités spécialisées. |
| Propulseurs vectoriels (`vector_propulsion`) | Laboratoire colonial B 4 ; Architecture navale B 5 ; Génie civil colonial B 5 | Laboratoire colonial B 5 ; Architecture navale B 5 ; Génie civil colonial B 5 | Laboratoire colonial B 4 ; Architecture navale B 5 ; Génie civil colonial B 5 ; Génie civil colonial I 10 ; Laboratoire colonial I 10 | Développement naval préalable aux bonus de mobilité. |
| Viseurs balistiques (`weapon_calibration`) | Canons à énergie focalisée B 2 ; Laboratoire colonial B 1 | Canons à énergie focalisée B 2 ; Laboratoire colonial B 5 ; Télémétrie de poursuite B 5 | Canons à énergie focalisée B 2 ; Laboratoire colonial B 1 ; Télémétrie de poursuite I 10 ; Laboratoire colonial I 10 | Précision après les premiers travaux d’armement. |
| Canons à énergie focalisée (`weapons_engineering`) | Laboratoire colonial B 5 ; Propulseurs vectoriels B 3 ; Architecture navale B 8 | Laboratoire colonial B 5 ; Propulseurs vectoriels B 3 ; Architecture navale B 8 ; Télémétrie de poursuite B 5 | Laboratoire colonial B 5 ; Propulseurs vectoriels B 3 ; Architecture navale B 8 ; Télémétrie de poursuite I 10 ; Laboratoire colonial I 10 | Armement après propulsion et architecture navale, pour une progression militaire graduelle. |

## Vaisseaux — 14

| Élément | Basique | Intermédiaire | Supérieur | Pourquoi |
|---|---|---|---|---|
| Cuirassé de ligne (`battleship`) | Chantier d’assemblage B 14 ; Canons à énergie focalisée B 10 ; Générateurs de bouclier accordés B 8 ; Séquenceur de salves B 5 | Chantier d’assemblage B 14 ; Canons à énergie focalisée B 10 ; Générateurs de bouclier accordés B 8 ; Séquenceur de salves B 5 | Chantier d’assemblage B 14 ; Canons à énergie focalisée B 10 ; Générateurs de bouclier accordés B 8 ; Séquenceur de salves B 5 ; Générateurs de bouclier accordés I 10 ; Chantier d’assemblage I 10 | Grande coque tardive après chantier 14 et plusieurs spécialisations militaires. |
| Bombardier tactique (`bomber`) | Chantier d’assemblage B 4 ; Canons à énergie focalisée B 3 ; Blindage multicouche B 2 | Chantier d’assemblage B 5 ; Canons à énergie focalisée B 5 ; Blindage multicouche B 2 | Chantier d’assemblage B 4 ; Canons à énergie focalisée B 3 ; Blindage multicouche B 2 ; Canons à énergie focalisée I 10 ; Chantier d’assemblage I 10 | Accès gradué à l’anti-grandes coques via chantier 4, armement et blindage. |
| Vaisseau capital (`capital`) | Chantier d’assemblage B 18 ; Canons à énergie focalisée B 12 ; Générateurs de bouclier accordés B 12 ; Séquenceur de salves B 10 ; Propulseurs vectoriels B 10 | Chantier d’assemblage B 18 ; Canons à énergie focalisée B 12 ; Générateurs de bouclier accordés B 12 ; Séquenceur de salves B 10 ; Propulseurs vectoriels B 10 | Chantier d’assemblage B 18 ; Canons à énergie focalisée B 12 ; Générateurs de bouclier accordés B 12 ; Séquenceur de salves B 10 ; Propulseurs vectoriels B 10 ; Canons à énergie focalisée I 10 ; Chantier d’assemblage I 10 | Aboutissement militaire au chantier 18 avec plusieurs branches avancées. |
| Vaisseau colonisateur (`colony_ship`) | Chantier d’assemblage B 1 | Chantier d’assemblage B 5 ; Protocoles de colonisation B 5 | Chantier d’assemblage B 1 ; Protocoles de colonisation I 10 ; Chantier d’assemblage I 10 | Accessible au chantier 1 ; la mission reste soumise au quota colonial et à ses conditions propres. |
| Corvette de patrouille (`corvette`) | Chantier d’assemblage B 4 ; Architecture navale B 4 ; Propulseurs vectoriels B 2 | Chantier d’assemblage B 5 ; Architecture navale B 4 ; Propulseurs vectoriels B 2 ; Télémétrie de poursuite B 5 | Chantier d’assemblage B 4 ; Architecture navale B 4 ; Propulseurs vectoriels B 2 ; Télémétrie de poursuite I 10 ; Chantier d’assemblage I 10 | Branche rapide après chantier 4 et premiers travaux de propulsion. |
| Croiseur de combat (`cruiser`) | Chantier d’assemblage B 10 ; Canons à énergie focalisée B 8 ; Propulseurs vectoriels B 5 ; Blindage multicouche B 5 | Chantier d’assemblage B 10 ; Canons à énergie focalisée B 8 ; Propulseurs vectoriels B 5 ; Blindage multicouche B 5 | Chantier d’assemblage B 10 ; Canons à énergie focalisée B 8 ; Propulseurs vectoriels B 5 ; Blindage multicouche B 5 ; Propulseurs vectoriels I 10 ; Chantier d’assemblage I 10 | Jalon lourd associant armement, propulsion et blindage. |
| Destroyer d’escorte (`destroyer`) | Chantier d’assemblage B 9 ; Canons à énergie focalisée B 9 ; Générateurs de bouclier accordés B 5 | Chantier d’assemblage B 9 ; Canons à énergie focalisée B 9 ; Générateurs de bouclier accordés B 5 | Chantier d’assemblage B 9 ; Canons à énergie focalisée B 9 ; Générateurs de bouclier accordés B 5 ; Canons à énergie focalisée I 10 ; Chantier d’assemblage I 10 | Spécialiste anti-lourd après chantier, armes et boucliers. |
| Chasseur de patrouille (`fighter`) | Aucun | Propulseurs vectoriels B 5 ; Chantier d’assemblage B 5 | Propulseurs vectoriels I 10 ; Chantier d’assemblage I 10 | Vaisseau de départ libre ; variantes avancées liées à la propulsion et au chantier. |
| Frégate d’escorte (`frigate`) | Chantier d’assemblage B 6 ; Canons à énergie focalisée B 5 ; Architecture navale B 7 | Chantier d’assemblage B 6 ; Canons à énergie focalisée B 5 ; Architecture navale B 7 | Chantier d’assemblage B 6 ; Canons à énergie focalisée B 5 ; Architecture navale B 7 ; Canons à énergie focalisée I 10 ; Chantier d’assemblage I 10 | Développement naval avant cette défense contre les petites unités. |
| Chasseur lourd (`heavy_fighter`) | Chantier d’assemblage B 3 ; Viseurs balistiques B 1 | Chantier d’assemblage B 5 ; Viseurs balistiques B 1 ; Canons à énergie focalisée B 5 | Chantier d’assemblage B 3 ; Viseurs balistiques B 1 ; Canons à énergie focalisée I 10 ; Chantier d’assemblage I 10 | Contre spécialisé précoce après chantier 3 et première précision. |
| Intercepteur atmosphérique (`interceptor`) | Chantier d’assemblage B 2 ; Architecture navale B 1 | Chantier d’assemblage B 5 ; Architecture navale B 1 ; Télémétrie de poursuite B 5 | Chantier d’assemblage B 2 ; Architecture navale B 1 ; Télémétrie de poursuite I 10 ; Chantier d’assemblage I 10 | Première spécialisation au chantier 2 ; rangs avancés liés au suivi. |
| Sonde de reconnaissance (`probe`) | Chantier d’assemblage B 1 | Chantier d’assemblage B 5 ; Analyse tactique B 5 | Chantier d’assemblage B 1 ; Analyse tactique I 5 ; Chantier d’assemblage I 10 | Découverte dès le chantier 1 ; analyse tactique Intermédiaire 5 remplace le seuil 10 impossible. |
| Chasseur furtif (`stealth_fighter`) | Écran de brouillage B 2 ; Propulseurs vectoriels B 1 ; Chantier d’assemblage B 4 ; Chasseur de patrouille B accessible | Écran de brouillage B 5 ; Propulseurs vectoriels B 1 ; Chantier d’assemblage B 5 ; Chasseur de patrouille B accessible | Écran de brouillage B 2 ; Propulseurs vectoriels B 1 ; Chantier d’assemblage B 4 ; Chasseur de patrouille B accessible ; Écran de brouillage I 5 ; Chantier d’assemblage I 10 | Mobilité et brouillage avant le furtif ; seuil Supérieur de brouillage corrigé à 5. |
| Cargo de ligne (`transport`) | Chantier d’assemblage B 2 ; Architecture navale B 2 | Chantier d’assemblage B 5 ; Architecture navale B 2 ; Soute compartimentée B 5 | Chantier d’assemblage B 2 ; Architecture navale B 2 ; Soute compartimentée I 10 ; Chantier d’assemblage I 10 | Logistique après chantier 2 et architecture navale 2 ; rangs avancés liés à la soute. |

## Défenses — 4

| Élément | Basique | Intermédiaire | Supérieur | Pourquoi |
|---|---|---|---|---|
| Canon laser anti-capital (`ground_turret_anti_capital`) | Bunker de défense B 5 ; Séquenceur de salves B 5 ; Canons à énergie focalisée B 8 | Bunker de défense B 10 ; Séquenceur de salves B 10 ; Canons à énergie focalisée B 8 | Bunker de défense B 5 ; Séquenceur de salves B 5 ; Canons à énergie focalisée B 8 ; Bunker de défense I 10 ; Séquenceur de salves I 15 | Dernière spécialisation défensive. Bunker Intermédiaire 10 remplace le seuil 15 impossible. |
| Canon laser anti-escorteur (`ground_turret_anti_escort`) | Bunker de défense B 2 ; Télémétrie de poursuite B 2 | Bunker de défense B 6 ; Télémétrie de poursuite B 5 | Bunker de défense B 2 ; Télémétrie de poursuite B 2 ; Bunker de défense I 10 ; Télémétrie de poursuite I 10 | Spécialisation après bunker 2 et suivi 2, puis approfondissement du suivi. |
| Canon laser anti-lourd (`ground_turret_anti_heavy`) | Bunker de défense B 3 ; Blindage multicouche B 3 | Bunker de défense B 7 ; Blindage multicouche B 3 ; Canons à énergie focalisée B 7 | Bunker de défense B 3 ; Blindage multicouche B 3 ; Bunker de défense I 10 ; Canons à énergie focalisée I 10 | Anti-lourd après bunker 3 et blindage 3, puis approfondissement de l’armement. |
| Canon laser anti-léger (`ground_turret_anti_light`) | Bunker de défense B 1 | Bunker de défense B 5 ; Viseurs balistiques B 5 | Bunker de défense B 1 ; Bunker de défense I 10 ; Viseurs balistiques I 10 | Première défense au bunker 1 ; précision aux rangs avancés. |

## Parcours

- Laboratoire B1 libre → laboratoire B2 + génie civil B2 → architecture navale B1 ; avec métal B2, chantier B1. Sondes et vaisseaux colonie B disponibles.
- Hydrogène B3 + laboratoire B2 + architecture navale B2 → réacteur B1 → stockage d’antimatière B1. Le chemin utilise des recettes B métal/nano, sans antimatière nécessaire avant son ouverture.
- Chantier B3 + laboratoire B2 + blindage B2 → bunker B1 → tourelle anti-léger B. Les autres défenses approfondissent leurs branches.
- Le test de parcours part de zéro, ouvre les éléments accessibles puis augmente uniquement leurs niveaux autorisés. Les 150 profils sont atteints, sans cycle bloquant ni seuil hors plafond. Il ne mesure pas un délai économique complet.

## Compatibilité

La colonne JSON nullable `player_universe.catalog_unlocks` mémorise les accès. `null` identifie une participation historique à reprendre ; les nouvelles participations commencent avec un registre vide. Les clés distinguent l’univers ou l’identifiant de colonie, au sein de la participation du joueur.

La reprise utilise les anciennes conditions d’entrée figées dans `config/legacy/catalog-unlocks-2026-11-06.json`, avec les surcharges de l’univers. Elle conserve les rangs possédés, unités fixes et mobiles (y compris en déplacement), commandes acceptées et accès anciens non construits dont les anciennes conditions sont remplies. Les anciens paliers ultérieurs ne deviennent pas des conditions initiales.

Le registre est complété sous verrou avant et après la synchronisation des files. Une condition initiale manquante ou une annulation de commande ne retire pas l’accès acquis. Aucun compte, niveau, rang, unité, coût payé, durée enregistrée ou ordre de file n’est réinitialisé. Le registre d’un joueur ne transmet pas les accès d’une colonie à un autre propriétaire.

### Activation après validation

1. Prévoir maintenance et sauvegarde habituelles.
2. Appliquer `Version20261106120000` : ajout de la colonne JSON seulement.
3. Exécuter `app:catalog:preserve-unlocks --dry-run`, puis `app:catalog:preserve-unlocks`. Reprise idempotente, univers fermés inclus.
4. Vérifier zéro participation restant à reprendre avant réouverture du jeu.

La reprise à la première synchronisation est un filet de compatibilité ; utiliser la commande avant de reprendre les actions de jeu, pour figer les accès avant d’éventuelles pertes ou changements d’état. Les états disparus avant cette mise à jour, sans trace sauvegardée, ne peuvent pas être reconstitués. Ne pas supprimer le registre ni l’instantané.

## Autres mécanismes

Les prérequis de catalogue sont limités aux quatre catégories. Les `levels` de conquête/PNJ décrivent garnisons et recherches PNJ : ils restent intacts. Conditions de mission, planète connue, sondes seules, carburant, quota colonial, conquête et permissions restent inchangés.

Les surcharges d’univers sur les conditions initiales restent prioritaires. Les anciens `levels.*.unlock_requirements` sont ignorés même dans une surcharge. Les contrôles d’accessibilité portent sur le catalogue livré ; les conditions personnalisées manuellement doivent rester compatibles avec les plafonds de leur univers.

## Vérifications

150 profils atteignables, exigences constantes sur neuf niveaux de contrôle. Tests des accès acquis, portées, anciennes unités/files, reprise idempotente, commandes directes, améliorations et présentation. Les devis, budgets, effets et statistiques restent conservés en dehors des prérequis et descriptions volontairement révisés.

Résultats :

- `make validate` : réussi.
- `make test` : dernier passage complet réussi, **200 tests / 205 447 assertions**. Après ajout du contrôle explicite du plafond et des assertions d’achèvement militaire, la sélection ciblée passe : **32 tests / 19 069 assertions**. La suite contient désormais 201 tests ; le dernier test ajouté est couvert par cette sélection.
- `npm run build` : réussi.
- `make lint` : réussi après migration, y compris PHP, 78 catalogues/configurations YAML/JSON, 56 templates Twig, services, mapping et synchronisation du schéma. Le seul écart avant migration était l’ajout de la colonne du registre.
- `git diff --check` : réussi.
- Tests navigateur : non lancés. Les fiches sont rendues dans les tests Twig ; la disponibilité de l’arbre est vérifiée avec des conditions manquantes et un accès acquis. Aucune vérification visuelle en navigateur n’est revendiquée.

Une exécution précédente de la suite complète a rencontré l’assertion intermittente préexistante `testNpcSpyReportInitializesGarrisonAndUsesItsCounterTechnology` : elle attend des catégories positives si l’espionnage réussit, alors que le score peut en révéler zéro. Le dernier passage complet réussit ; ce test et les règles d’espionnage ne sont pas modifiés dans ce travail.

## Fichiers concernés

Catalogues de bâtiments/recherches et quatre fiches d’unités ; instantané `config/legacy`. `RequirementChecker`, `CatalogAccess`, `QueueService`, `UniverseService`, `PlayerUniverse` pour validation et registre. `ColonyView`, `CatalogueDetails`, `CatalogueInsights`, templates `catalogue_info`/`technology_tree` pour la présentation. Migration et commande de reprise dédiées ; tests de parcours, persistance, présentation et économie ; règles et décisions.

Provenance vérifiée pour les 50 fiches contre la révision `c6ecc7118cc58d8ae09b4bc72266d459a074cef2`. SHA-256 : `3e3fd07f433ef359720b76d0dea714f5d2f7021bdceda94a5889b57cc11c8ff9`.

## Application locale — 6 novembre 2026

L’utilisateur valide explicitement la migration. Branche vérifiée : `dev`. Base locale : `genesis_v2`. Une seule migration restait à appliquer : `Version20261106120000`, exécutée avec succès (une requête additive). Aucune autre migration appliquée.

Sauvegarde préalable : `var/backups/genesis-genesis_reborn_v2-20261106T015809Z.sql.gz`, avec somme SHA-256 associée. Worker, relais et notifications mis temporairement en pause, puis tous repris.

La reprise annonce 5 participations à traiter, puis 5 reprises ; le dernier contrôle indique 0 participation restante. Les empreintes avant/après sont identiques pour les tables comptes, colonies, ressources, niveaux, technologies, flottes, unités, défenses, files et outbox, ainsi que les champs historiques des participations. Seul le registre ajouté est renseigné. Somme SHA-256 des deux relevés : `4221093a2c037b76d077c82d3e945f61c12fa1ab1ad66d6529c4ce803e78afd4`.

`make validate` et `make lint` réussissent après l’opération. Les services locaux sont tous en fonctionnement. Aucun push, aucune fusion vers Alpha, aucun déploiement.
