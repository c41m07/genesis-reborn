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
Trois queues indépendantes par planète. QueueItem : type, slug, niveau cible ou quantité, durée, started_at, queued_at, dispatch_token, depends_on. Ordre id ASC. Échéance non persistée : T0 + somme des durées. Un item actif par type. Prérequis achevés à l'ajout ; exception niveaux successifs via dépendance. Suppression récursive des dépendants. Boost cumulable sur le temps restant de l'actif uniquement. Ancien message ignoré si token différent ; doublon ignoré après traitement. Persistance et déduction des ressources dans une transaction avec verrou.

## Trajets (PDF 3 p.2–9)
UA universelle. Intra-système : abs(positionB-positionA) × 10. Inter-systèmes : sortie 100−positionA×10, hyperespace hypot(dx,dy)×100, entrée 100−positionB×10. Temps par segment = distance / (vitesse minimale de flotte × ratio). Ratio entre 0.1 et 1. Aller-retour symétrique. Carburant aller + stationnement + retour réservé avant départ. Consommation proportionnelle au ratio de vitesse. Chaque vaisseau possède un petit réservoir exclusivement pour le carburant ; les soutes cargo peuvent embarquer le fret et le carburant supplémentaire. Consommation d'orbite horaire indépendante du ratio. Navigation inter-galaxies non définie et refusée jusqu'à spécification.

## Combat (PDF 1 p.6–8, PDF 2 p.2–6)
Fenêtre fixe dès premier attaquant, renforts échelonnés par rounds. Alliances directes et guildes définissent les camps ; défense extérieure intégrée au camp défendeur même sans alliance. FFA si attaquants non alliés. Unité individuelle, tirs bornés, cible vivante, destruction avant tir possible. Chance clamp(15,95,precision+tracking−evasion+signature/5−15). Matchups ×1.30 /1.15 /0.85 /0.70, HP restaurés après combat. Bonus technologiques figés au début de résolution. Notifications avec identité masquée selon découvertes ; destinataires limités. Après victoire offensive, les attaquants survivants peuvent piller le stock au-delà de 10 % de la capacité de stockage par ressource; leurs soutes libres se partagent le butin au prorata des stocks pillables, carburant de retour protégé. Au retour, le stockage restant de la planète d’origine plafonne le dépôt et le surplus est perdu.

## Architecture (PDF 4)
Controller → service métier → manager → repository. Entités et repositories plats, services regroupés par domaine ; messages et handlers fins ; intégration Mercure indépendante du jeu. Slugs uniquement pour référencer les catalogues. Aucun catalogue dynamique en base.

## Compléments opérationnels appliqués
Les opérations en retard sont rejouées à leurs dates théoriques avant dépense/lecture de colonie, avec production et plafonds successifs. Les notifications utilisent un relais indépendant des échéances du jeu. Une sonde ou un vaisseau-colonie consommé sans flotte survivante termine la mission à l’arrivée ; un transport revient et livre une seule fois.

Guildes : recrutement ouvert provisoire, adhésion exclusive dans chaque univers, transfert du commandement à un autre membre, départ après transfert, dissolution par le dirigeant. Alliances personnelles et alliances de guildes exigent le consentement réciproque. Une hostilité de guilde invalide les contrats personnels incompatibles.

Conquête JcE : seules les planètes neutres marquées PNJ sont attaquables; une victoire avec un vaisseau colonie survivant capture la planète. Bâtiments, stocks et développement sont conservés; flotte et défenses PNJ disparaissent. Les planètes vierges restent colonisables et aucune planète détenue par un joueur n’est conquérable.

Espionnage : une mission cible une planète connue du même univers autre que celles de l’émetteur. Elle transporte uniquement des sondes, détruites à l’arrivée. La détection avertit le propriétaire et bloque le rapport; les renseignements et rapports suivent les niveaux et règles résumés dans docs/agent/PHASE_02_DECISIONS.md.

Périmètre restant : fermeture asynchrone, classements, administration, CI et audits finaux. Attaque, défense, orbite, résolution, butin, rapports, conquête JcE et espionnage sont livrés en première version; les équilibres PNJ et espionnage restent provisoires.
