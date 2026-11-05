# Synthèse de conception

## Progression (PDF 0 p.3–9)
1. Socle, authentification session.
2. Univers et cartes JSON, inscription PlayerUniverse, sélection.
3. Ressources, bâtiments, recherches, chantier et queues.
4. Flottes, trajets, exploration, transport, colonisation, orbite.
5. Relations, guildes, combats multi-camps et notifications privées.
6. Variantes, fermeture, conquête, classements et finitions.

## Monde et comptes
Univers → galaxie → système (x,y) → planète position 1..9. Comptes globaux, état par inscription à un univers. Aucune interaction entre univers. Univers fermés conservés mais inaccessibles. Cartes JSON distinctes de la configuration YAML et import explicite idempotent. Carte statique, planètes masquées hors systèmes découverts.

## Colonie
Métal, électricité, antimatière, hydrogène et nano par planète. La simulation intègre les stocks par segments entre épuisement et capacité. Les usines adaptent leur rendement à l’électricité disponible avec un plancher de 10 % ; l’antimatière exige aussi de l’hydrogène. Coûts et durées sont autoritaires côté serveur. Les coûts des trois rangs suivent des profils par catégorie avec budgets de valeur constants. Technologies par PlayerUniverse avec laboratoire local. Même technologie interdite simultanément sur des planètes différentes.

## Queues (PDF 7 p.1–7)
Trois queues indépendantes par planète. QueueItem : type, slug, niveau cible ou quantité, durée, started_at, queued_at, dispatch_token, depends_on. Ordre id ASC. Échéance non persistée : T0 + somme des durées. Un item actif par type. Prérequis achevés à l'ajout ; exception niveaux successifs via dépendance. Suppression récursive des dépendants. Boost : chaque application retire 25 % du temps restant de l’opération active. Les applications successives se cumulent sur le nouveau temps restant; aucune file en attente n’est accélérée. Ancien message ignoré si token différent ; doublon ignoré après traitement. Persistance et déduction des ressources dans une transaction avec verrou.

## Trajets (PDF 3 p.2–9)
UA universelle. Intra-système : abs(positionB-positionA) × 10. Inter-systèmes : sortie 100−positionA×10, hyperespace hypot(dx,dy)×100, entrée 100−positionB×10. Temps par segment = distance / (vitesse minimale de flotte × ratio). Ratio entre 0.1 et 1. Aller-retour symétrique. Carburant aller + stationnement + retour réservé avant départ. Consommation proportionnelle au ratio de vitesse. Chaque vaisseau possède un petit réservoir exclusivement pour le carburant ; les soutes cargo peuvent embarquer le fret et le carburant supplémentaire. Consommation d'orbite horaire indépendante du ratio. Navigation inter-galaxies non définie et refusée jusqu'à spécification.

## Combat (PDF 1 p.6–8, PDF 2 p.2–6)
Cadence confirmée par Kévin le 01/11/2026 : un round réel par minute, jusqu’à 10 rounds; les renforts rejoignent le prochain round non résolu selon leur heure d’arrivée. Le premier round commence à +60 secondes; les suivants à +120, jusqu’au 10e à +600. Une flotte arrivée exactement au début d’un round est traitée avant ce round et peut y participer, notamment à +600 secondes avant le 10e round. Le combat se clôt immédiatement dès qu’un camp est éliminé; toute flotte arrivée après cette clôture repart sans rejoindre le combat. Le moteur persiste et résout les rounds en temps réel selon cette cadence. L’Alpha conserve un round par minute, jusqu’à dix rounds ou clôture anticipée; voir l’audit de combat et les tests récents.
Fenêtre fixe dès premier attaquant, renforts échelonnés par rounds. Alliances directes et guildes définissent les camps ; défense extérieure intégrée au camp défendeur même sans alliance. FFA si attaquants non alliés. Unité individuelle, tirs bornés, cible vivante, destruction avant tir possible. Chance de base = clamp(15,95,precision+tracking+correction de l’arme−evasion−15+écart de rang). Chance finale = clamp(15,95,chance de base × coefficient de contre). La signature pondère le choix de cible ; le même coefficient de contre ou de faiblesse multiplie une fois la chance de toucher et une fois les dégâts d’un impact, sans second cumul réciproque. Un contre ×1,30 fait passer 50 % à 65 % de chance de toucher et augmente les dégâts par impact de 30 %. Voir le rééquilibrage du 02/11/2026 ci-dessous. Bonus technologiques figés au début de résolution. Notifications avec identité masquée selon découvertes ; destinataires limités. Après victoire offensive, les attaquants survivants peuvent piller le stock au-delà de 10 % de la capacité de stockage par ressource; leurs soutes libres se partagent le butin au prorata des stocks pillables, carburant de retour protégé. Au retour, le stockage restant de la planète d’origine plafonne le dépôt et le surplus est perdu.

## Architecture (PDF 4)
Controller → service métier → manager → repository. Entités et repositories plats, services regroupés par domaine ; messages et handlers fins ; intégration Mercure indépendante du jeu. Slugs uniquement pour référencer les catalogues. Aucun catalogue dynamique en base.

## Compléments opérationnels appliqués
Les opérations en retard sont rejouées à leurs dates théoriques avant dépense/lecture de colonie, avec production et plafonds successifs. Les notifications utilisent un relais indépendant des échéances du jeu. Une sonde ou un vaisseau-colonie consommé sans flotte survivante termine la mission à l’arrivée ; un transport revient et livre une seule fois.

Guildes : recrutement ouvert provisoire, adhésion exclusive dans chaque univers, transfert du commandement à un autre membre, départ après transfert, dissolution par le dirigeant. Alliances personnelles et alliances de guildes exigent le consentement réciproque. Une hostilité de guilde invalide les contrats personnels incompatibles.

Conquête JcE : seules les planètes neutres marquées PNJ sont attaquables; une victoire avec un vaisseau colonie survivant capture la planète. Bâtiments, stocks et développement sont conservés; flotte et défenses PNJ disparaissent. Les planètes vierges restent colonisables et aucune planète détenue par un joueur n’est conquérable.

Espionnage : une mission cible une planète connue du même univers autre que celles de l’émetteur. Elle transporte uniquement des sondes, détruites à l’arrivée. La détection avertit le propriétaire et bloque le rapport; les renseignements et rapports suivent les niveaux et règles résumés dans docs/agent/PHASE_02_DECISIONS.md.

Les classements, l’administration, les combats, les missions avancées et l’espionnage sont livrés. Les choix chiffrés de base figurent dans docs/agent/BALANCING_QCM_2026-11-02.md; leur effet sur le rythme et la difficulté sera mesuré avec les joueurs Alpha.


## Fermeture d’univers temporaires
Un événement Outbox est créé avec la date de fin; le relais le programme via Messenger. Le handler verrouille l’univers, vérifie la date et le jeton courant, puis passe son statut à fermé. Les anciens événements sont ignorés et les données des planètes restent intactes.


## Équilibrage retenu — 01–02/11/2026

Les valeurs initiales des stocks, productions, coûts, durées, vaisseaux, défenses, boucliers, contre-bonus, espionnage, PNJ et protections ont été choisies par Kévin pendant le QCM. Elles constituent la configuration de l’Alpha; elles ne sont pas déclarées équilibrées sur la seule base de leur présence. Les cibles de progression seront mesurées pendant l’Alpha, puis révisées à partir des résultats. La cadence confirmée reste un round par minute pendant dix minutes maximum.


## Progression technologique par rangs — décision du 02/11/2026

Chaque bâtiment, technologie et vaisseau conserve trois progressions distinctes, actives en parallèle : **Basique**, **Intermédiaire** et **Supérieur**. Les niveaux déjà sauvegardés sont conservés en Basique. Un niveau 10 Basique et un niveau 5 Intermédiaire sont donc simultanément possibles. Les défenses stationnaires possèdent aussi les trois rangs, enregistrés et combattus séparément.

| Rang | Puissance et coûts | Durée d’opération |
|---|---:|---:|
| Basique | 1× | 1× |
| Intermédiaire | 1,25× | 1,25× |
| Supérieur | 1,5625× | 1,5625× |

Les profils de coûts par rang sont pré-calibrés par famille dans les catalogues; les coûts par niveau croissent ensuite selon la courbe du bâtiment ou de la technologie. Les opérations arrondissent chaque coût positif à l’entier supérieur; une ressource configurée à zéro reste à zéro. Cette règle évite un coût nul pour une ressource effectivement requise. Les durées sont arrondies à la seconde supérieure, avec un minimum d’une seconde.

La puissance dépend de la spécialité : la production et le stockage utilisent l’effet par niveau propre au bâtiment; les technologies appliquent leur bonus au champ concerné; vaisseaux et défenses renforcent structure, bouclier et attaque; les vaisseaux améliorent aussi vitesse, réservoir et soute. Précision, suivi et esquive gardent leurs valeurs de base; la signature n’est pas divisée. Chaque écart de rang ajoute ou retire 10 points de pourcentage à la chance de toucher, bornée entre 15 % et 95 %. Les stats positives entières sont arrondies vers le haut. Pour les tirs entiers, le rang donne une chance de tir supplémentaire par tir de base : 25 % au rang Intermédiaire, 56,25 % au rang Supérieur. Les technologies de cadence ajoutent leur propre chance, indépendamment.

Les bonus technologiques en pourcentage qui étaient à +5 % par niveau passent à **+1 % par niveau et niveau maximum 25**. Le laboratoire et le chantier spatial passent à **2 % par niveau, maximum 25**, conservant leur maximum Basique de 50 %. Les bonus de rang sont multipliés puis composés : une technologie à 25 niveaux dans chacun des trois rangs produit les facteurs 1,25 × 1,3125 × 1,390625 = **2,281494×**. Les bâtiments de production de ressources et de stockage n’ont plus de niveau maximum.

Les prérequis Basique existants restent en place. Les ouvertures des rangs supérieurs sont spécifiques à chaque élément : Intermédiaire demande la technologie liée au niveau 5 Basique; Supérieur demande cette technologie au niveau 10 Intermédiaire. Le laboratoire et le chantier spatial sont aussi utilisés dans les chaînes concernées. Aucun ancien niveau n’est dupliqué ni réinitialisé.

Les coûts de recherche par niveau conservent la croissance ×2 et les durées de recherche la croissance ×1,8. Les durées de bâtiment conservent ×1,6. Ces courbes restent des paramètres distincts des multiplicateurs de rang. La migration attribue les données historiques au rang Basique; les opérations déjà en file conservent leurs coûts et durées enregistrés.

Les technologies de combat couvrent structure, bouclier, attaque, précision, suivi, esquive, signature, cadence de tir, vitesse, réservoir et soute. Le tir supplémentaire est une chance indépendante par tir de base pour conserver une résolution entière et éviter des valeurs de tirs fractionnaires.

Pour les effets discrets de colonisation, chaque rang contribue son quota pondéré, puis les fractions de colonies sont arrondies vers le bas; la limite de colonies configurée par l’univers reste prioritaire. Les productions et capacités de stockage de plusieurs bâtiments-rangs s’additionnent, car chaque rang fonctionne comme une installation distincte. Les coûts des ressources non énergétiques suivent les budgets de valeur par famille; l’électricité n’est pas facturée lors de la construction. Les usines reçoivent un rendement de secours minimal de 10 % en déficit électrique, selon une simulation segmentée hors connexion; la production d’antimatière est limitée par l’hydrogène et s’arrête sans apport. La consommation électrique nominale des extracteurs est configurée à 0,05/s/niveau; l’usine d’antimatière consomme 8 H par A produite.


### Research et art temporaire

Les 19 recherches ont reçu des libellés français distincts et des slugs dédiés, sans modifier leurs effets. Leurs états sauvegardés, files de recherche et surcharges d’univers sont renommés par une migration compatible. Les défenses sont regroupées en quatre tourelles au sol : anti-léger (intercepteur, chasseur, chasseur lourd, chasseur furtif), anti-escorteur (corvette, frégate, destroyer), anti-lourd (croiseur, cuirassé) et anti-capital. Les tourelles laser couvrent les classes légères et escorteurs; les tourelles plasma couvrent les classes lourdes et capitales. Chaque tourelle reçoit un bonus de dégâts contre sa classe. La collection complète des 150 illustrations de catalogue est intégrée aux cartes des 13 bâtiments, 19 recherches, 14 vaisseaux et 4 défenses, aux rangs basic, intermediate et superior. Chaque image est associée au slug et au rang de sa carte; les originaux PNG sont conservés dans les dossiers source de public/images/catalog.


## Rééquilibrage progression et combat — 02/11/2026

Les courbes, bonus communs entre rangs, profils de mobilité, ciblage furtif, retarget après destruction et matrice de contres sont décrits dans [BALANCING_IMPLEMENTATION_2026-11-02.md](agent/BALANCING_IMPLEMENTATION_2026-11-02.md). Ces choix sont validés pour l’implémentation ; les jalons économiques demeurent des objectifs à mesurer en Alpha.


### Tirs secondaires et contres — 4 novembre 2026

Les destroyers, croiseurs, cuirassés et vaisseaux capitaux disposent d’une arme secondaire avec une correction de précision de +30 points. Cette correction est ajoutée avant le calcul de la chance de base ; le coefficient de contre est ensuite appliqué relativement à la chance bornée, puis les limites finales de 15–95 % sont réappliquées. Les coefficients concernent les tirs principaux et secondaires ainsi que les défenses.

Les armes secondaires ne déclenchent pas les tirs supplémentaires de la recherche de cadence. Leur cible doit correspondre à la catégorie configurée pour l’arme. Les classes admissibles sont décrites dans le catalogue ; une cible inadmissible ne reçoit aucun tir secondaire, ce qui est différent d’une probabilité de toucher de 0 %.


Les noms des cibles de contre sont ceux des familles génériques de coque, communs aux trois rangs. Par exemple, la famille Capital couvre Vaisseau capital, Dreadnought et Vaisseau amiral. Les défenses au sol partagent une famille générique de cible lorsqu’aucune relation particulière à une tourelle n’est définie. La classe cible Chasseur regroupe le Chasseur standard, le Chasseur furtif et l’Intercepteur, à leurs trois rangs. Le Chasseur lourd reste séparé. Un contre envers Chasseur s’applique à chacun des trois types ; leurs profils de tir offensifs et leurs statistiques restent propres à chaque coque. Lorsque des anciennes relations vers ces membres avaient des coefficients différents pour un même tireur, la classe reprend le coefficient le plus fort, sans cumul.
