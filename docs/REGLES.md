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
Métal, énergie, antimatière, hydrogène et carbone par planète. Calcul paresseux : stock + taux/seconde × temps écoulé. Pas de tick global, l'interface extrapole seulement. Changement de production : calculer d'abord le stock au taux précédent. Coûts et durées autoritaires côté serveur. Bâtiments uniques avec niveaux, catalogue statique YAML ; coûts et durées exponentiels. Technologies par PlayerUniverse avec laboratoire local. Même technologie interdite simultanément sur des planètes différentes.

## Queues (PDF 7 p.1–7)
Trois queues indépendantes par planète. QueueItem : type, slug, niveau cible ou quantité, durée, started_at, queued_at, dispatch_token, depends_on. Ordre id ASC. Échéance non persistée : T0 + somme des durées. Un item actif par type. Prérequis achevés à l'ajout ; exception niveaux successifs via dépendance. Suppression récursive des dépendants. Boost : chaque application retire 25 % du temps restant de l’opération active. Les applications successives se cumulent sur le nouveau temps restant; aucune file en attente n’est accélérée. Ancien message ignoré si token différent ; doublon ignoré après traitement. Persistance et déduction des ressources dans une transaction avec verrou.

## Trajets (PDF 3 p.2–9)
UA universelle. Intra-système : abs(positionB-positionA) × 10. Inter-systèmes : sortie 100−positionA×10, hyperespace hypot(dx,dy)×100, entrée 100−positionB×10. Temps par segment = distance / (vitesse minimale de flotte × ratio). Ratio entre 0.1 et 1. Aller-retour symétrique. Carburant aller + stationnement + retour réservé avant départ. Consommation proportionnelle au ratio de vitesse. Chaque vaisseau possède un petit réservoir exclusivement pour le carburant ; les soutes cargo peuvent embarquer le fret et le carburant supplémentaire. Consommation d'orbite horaire indépendante du ratio. Navigation inter-galaxies non définie et refusée jusqu'à spécification.

## Combat (PDF 1 p.6–8, PDF 2 p.2–6)
Cadence confirmée par Kévin le 01/11/2026 : un round réel par minute, jusqu’à 10 rounds; les renforts rejoignent le prochain round non résolu selon leur heure d’arrivée. Le premier round commence à +60 secondes; les suivants à +120, jusqu’au 10e à +600. Une flotte arrivée exactement au début d’un round est traitée avant ce round et peut y participer, notamment à +600 secondes avant le 10e round. Le combat se clôt immédiatement dès qu’un camp est éliminé; toute flotte arrivée après cette clôture repart sans rejoindre le combat. Le moteur persiste et résout les rounds en temps réel selon cette cadence. L’Alpha conserve un round par minute, jusqu’à dix rounds ou clôture anticipée; voir l’audit de combat et les tests récents.
Fenêtre fixe dès premier attaquant, renforts échelonnés par rounds. Alliances directes et guildes définissent les camps ; défense extérieure intégrée au camp défendeur même sans alliance. FFA si attaquants non alliés. Unité individuelle, tirs bornés, cible vivante, destruction avant tir possible. Chance clamp(15,95,precision+tracking−evasion+signature/5−15). Matchups ×1.30 /1.15 /0.85 /0.70, HP restaurés après combat. Bonus technologiques figés au début de résolution. Notifications avec identité masquée selon découvertes ; destinataires limités. Après victoire offensive, les attaquants survivants peuvent piller le stock au-delà de 10 % de la capacité de stockage par ressource; leurs soutes libres se partagent le butin au prorata des stocks pillables, carburant de retour protégé. Au retour, le stockage restant de la planète d’origine plafonne le dépôt et le surplus est perdu.

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
