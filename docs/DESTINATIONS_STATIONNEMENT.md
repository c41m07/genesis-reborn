# Destinations et Stationnement — lot 5

## État et plan

Branche feature/destinations-stationnement depuis dev bc4930a après fusion
autorisée de la PR #62. CI préalable sur b3686bb : 988 PHP / 212 335 assertions,
13 Python et 198 parcours navigateur, cinq jobs quality et GitGuardian réussis.
Implémentation locale terminée. Publication de la branche et PR en brouillon vers
dev autorisées, avec suivi et correction de la CI. Le statut courant figure dans
la [PR associée](https://github.com/c41m07/genesis-reborn/pulls?q=is%3Apr+head%3Afeature%2Fdestinations-stationnement).
Aucune fusion du lot, application de migration à la base habituelle ou livraison
Alpha autorisée.
Les modifications locales préexistantes de PARCOURS_UI_UX.md et controllers.json
restent préservées hors du lot. Destinations initialement 4/5, réévaluées à 5/5 pour la synchronisation des listes et suggestions ; Stationnement 5/5.

1. Catégories selon la matrice, saisie personnalisée système/planète et validation
   serveur. Les listes et les permissions restent distinctes.
2. Réserve embarquée à quai, départs suivants, limites de capacité et déchargement.
3. Stationnement : arrivée, rappel, repli, attente et reprise ; transitions sous
   les verrous existants, jetons d’outbox invalidés et transferts atomiques.
4. Interfaces, visibilité autorisée, actions de reprise et déchargement, tests
   d’isolation et rejouabilité, documentation puis CI avant toute fusion.

## Décisions validées le 14 novembre 2026

- Exploration : systèmes non explorés, coordonnées seules. Colonisation : planètes
  neutres découvertes et colonisables. Transport/défense/orbite : mes planètes et
  planètes alliées selon les droits propres à chaque mission. Attaque/espionnage :
  PNJ et ennemis déclarés séparés. Conquête JcE : PNJ. Stationnement : mes planètes.
  Destination personnalisée disponible pour chaque mission, sans contourner
  découvertes, protections, univers ou autorisations.
- Les empires oubliés sont des PNJ selon les règles existantes. Une ancienne
  planète PNJ détenue par un joueur actif est catégorisée selon ce propriétaire.
- Autorisation explicite d’orbite sur ses propres planètes, acceptée automatiquement,
  avec durées, séjour et retour existants ; droits chez les autres inchangés.
- Stationnement : nouvelle base à l’arrivée réussie, pas de retour automatique.
  Réserver deux fois le coût aller selon les formules actuelles, consommer l’aller
  à l’installation et conserver le carburant restant à bord.
- Rappel avant arrivée selon le devis existant. Réserve embarquée mutualisée avec
  la flotte inactive de la colonie, utilisée avant le stock planétaire lors des
  départs suivants. Un départ est refusé si la capacité des vaisseaux restants ne
  peut plus porter la réserve et le fret conservés.
- Fret déchargé dans les capacités disponibles ; surplus conservé à bord.
  Actions explicites de déchargement, sans perte automatique du surplus.
- Destination perdue : d’abord l’origine possédée et atteignable avec la réserve,
  sinon la colonie possédée atteignable au trajet le plus court. Revérifier à chaque
  arrivée. Ne jamais consommer automatiquement l’hydrogène du fret comme carburant.
- Sans repli possible : attente comme un trajet, vaisseaux indisponibles, règles de
  visibilité des vols, sans nouveau combat ou coût de séjour. Notification unique.
  Reprise manuelle après devis vers une colonie possédée avec la réserve existante.
  Si rien n’est possible, l’attente persiste sans transfert ni perte automatique.

## Architecture implémentée

Les vaisseaux à quai sont regroupés dans une flotte inactive par colonie.
FleetHold distingue storedFuel (à quai), reservedFuel (voyage) et le fret
d’hydrogène. Chaque départ débite d’abord le stock embarqué puis la planète,
tout en embarquant physiquement le carburant total. Le fret conservé à quai
n’est pas rechargé automatiquement : il se décharge explicitement.

FleetStationing traite les arrivées avant le garde historique de la planète
d’origine, car cette origine peut être perdue. Il consomme une seule fois le
coût du tronçon enregistré, choisit un repli ou met en attente. Les trajets de
repli utilisent les formules actuelles ; à égalité de durée, tri par identifiant
de colonie. L’historique de départ reste conservé et la carte suit le tronçon réel.
FleetDispatch mutualise la planification existante, sans changement de transport.
Une attente n’a aucune échéance ; son ancien jeton est invalidé et ne produit
aucun nouveau message de trajet. Les verrous existants de participation/univers
sérialisent les écritures ; réception, dépôt et fusion des vaisseaux restent
dans la même transaction. Les arrondis sont plafonnés au carburant physique.

Flottes présente les réserves de la colonie active et tous les stationnements
en attente de l’empire, même après perte de l’origine. La carte et les vues
administratives reconnaissent le nouvel état, avec les permissions existantes.
Les vaisseaux en attente ne deviennent ni une garnison ni un nouveau combat.
Leur état compte comme flotte déployée et empêche les vacances comme un trajet.
Une conversion en empire oublié ne transforme pas une attente en installation
gratuite ; l’état reste conservé, sans inventer un mécanisme de sauvetage.

Les stocks à quai restent conservés avec les défenseurs survivants d’une colonie
toujours possédée après combat. L’abandon confirmé inclut carburant et fret dans
les pertes annoncées et auditées, puis les vide. Aucun boost ni objectif de
transport n’est attribué au Stationnement.

## Données et activation ultérieure

Migration créée : app/migrations/Version20261114164227.php, ajout de
fleet.stored_fuel DOUBLE PRECISION NOT NULL DEFAULT 0. Aucune autre modification
de schéma dans ce lot ; zéro pour les flottes existantes. La migration a été
exécutée uniquement dans des bases jetables de vérification.
Sur une base existante, prévoir séparément une sauvegarde vérifiée, l’autorisation
explicite d’appliquer les migrations en attente, puis le redémarrage des
consommateurs. La fusion ne constitue ni cette autorisation ni un déploiement.

## Vérifications

- make validate et make assets réussis.
- make lint : PHP, 78 YAML, 161 Twig, conteneur et mapping corrects ; seule
  la comparaison au schéma habituel échoue, les migrations y restant volontairement
  non appliquées. Lints ciblés repris après les derniers ajustements.
- make test : 1 000 tests / 212 430 assertions exécutés ; un seul échec,
  l’ancienne interdiction de l’orbite chez soi. Attente corrigée selon la décision
  validée, puis reprise réussie du fichier concerné. La suite complète n’a pas
  été relancée après cette correction et les dix scénarios complémentaires.
- 152 tests PHP ciblés distincts réussis au total après reprises : destinations,
  rappel, stationnement, orbite, visibilité, administration, combats et abandon.
  Dont 20 scénarios de Stationnement, couvrant droits, univers, devis périmés,
  réserve insuffisante sans consommation du fret, répétitions, arrondis, nouvelle
  base, repli et attente. Aucune assertion ou protection désactivée.
- 13 tests Python réussis.
- 11 parcours navigateur distincts réussis après corrections, dont deux nouveaux
  pour installation/déchargement et reprise. Après l’ajustement de disposition,
  ces deux parcours ont été repris avec succès. Captures réellement inspectées
  en 390 et 1 440 pixels, sans débordement horizontal. Axe sans anomalie sérieuse
  ou critique sur les zones testées ; clavier, focus et toucher vérifiés.
  Les noms accessibles des boutons Max incluent désormais le texte visible.
  Il ne s’agit pas d’un audit complet RGAA.
- make verify-install réussi : installation vierge, schéma synchronisé après
  les migrations et conservation après redémarrage, dans un projet jetable.
- Contrôle du diff avant commit ; aucune application de migration à la base
  habituelle, aucune livraison Alpha. Publication et PR en brouillon autorisées ;
  la PR associée fera référence pour les résultats de CI.
- Avant fusion : CI complète, sauvegarde/restauration isolées et autorisation
  explicite. Les trois documents maîtres Work ne sont pas disponibles localement ;
  aucune validation de leurs 15 phases n’est prétendue.

## Publication et correction de CI

PR #63 en brouillon vers dev, publication autorisée. Première CI sur c0089a3 :
1 010 tests PHP / 212 490 assertions et 13 Python réussis, lints et schéma
synchronisés, sauvegarde/restauration isolées réussies, GitGuardian réussi.
Trois scénarios navigateur de messagerie échouent lors de la lecture synchrone
du bouton, juste après une navigation. Le test attend désormais la fin de
chargement avant les événements synthétiques ; les vérifications du blocage
et de l’absence de double envoi sont conservées. Complexité 2/5, correction
ciblée de synchronisation du test, sans changement du comportement produit.
Les quatre parcours de message-markdown.spec.mjs réussissent localement après
cette correction, dans le projet navigateur jetable.
La PR associée fait référence pour les résultats de la nouvelle CI complète.

## Fichiers du lot

### Métier, données et contrôleurs

- app/migrations/Version20261114164227.php
- app/src/Controller/FleetController.php
- app/src/Controller/FleetStationingController.php
- app/src/Entity/Fleet.php
- app/src/Repository/GameRepository.php
- app/src/Service/Admin/AdminMissionMonitoring.php
- app/src/Service/Colony/ColonyAbandonmentService.php
- app/src/Service/Colony/ColonyView.php
- app/src/Service/Combat/CombatEventService.php
- app/src/Service/Fleet/DestinationCatalogue.php
- app/src/Service/Fleet/FleetDispatch.php
- app/src/Service/Fleet/FleetHold.php
- app/src/Service/Fleet/FleetService.php
- app/src/Service/Fleet/FleetStationing.php
- app/src/Service/Fleet/FleetVisibility.php
- app/src/Service/Fleet/OrbitPolicy.php

### Interfaces

- app/assets/controllers/fleet_destination_controller.js
- app/assets/controllers/fleet_fields_controller.js
- app/assets/controllers/fleet_preview_controller.js
- app/assets/controllers/galaxy_v2_controller.js
- app/assets/styles/fleet-destinations.css
- app/templates/admin/sections/operations.html.twig
- app/templates/fleet/holds.html.twig
- app/templates/fleet/index.html.twig
- app/templates/fleet/resume.html.twig
- app/templates/ui/fleet_destination.html.twig
- app/templates/ui/flights.html.twig

### Tests

- app/tests/Controller/FleetDestinationTest.php
- app/tests/Integration/ColonyAbandonmentTest.php
- app/tests/Integration/CombatEventTest.php
- app/tests/Integration/FleetStationingTest.php
- app/tests/Integration/OrbitDepartureRequestTest.php
- app/tests/browser/fleet-destinations.spec.mjs
- app/tests/browser/fleet-recall.spec.mjs
- app/tests/browser/fleet-stationing.spec.mjs
- app/tests/browser/message-markdown.spec.mjs

### Documentation

- README.md
- docs/DECISIONS.md
- docs/DESTINATIONS_STATIONNEMENT.md
- docs/FEUILLE_DE_ROUTE.md
- docs/MESSAGERIE_MARKDOWN.md
- docs/README.md
- docs/RECETTE_EVOLUTIONS_INTERFACES.md
- docs/REGLES.md
- docs/wiki.json
