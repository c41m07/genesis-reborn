# Roadmap Genesis Reborn

## Messagerie : raccourci et Markdown — 14 novembre 2026

Lot 4 sur feature/messagerie-markdown, depuis dev 2170607 après fusion autorisée de la PR #61. Raccourci et doublons 3/5 ; Markdown 4/5 ; historique/migration 3/5. Privé, guilde et Global, nouveaux messages seulement. CommonMark ajouté avec autorisation ; format historique et soumission identifiée persistés par une migration préparée, non appliquée à la base habituelle. Vérifications ciblées : 137 tests PHP distincts après compléments et 10 parcours navigateur réussis après reprises ; validation Compose, compilation et lints ciblés réussis. Migration non appliquée, suite locale complète et CI du lot non revendiquées ; aucune publication/PR du lot ni livraison Alpha. Voir [le suivi](MESSAGERIE_MARKDOWN.md).

## Profils des joueurs et présentations de guildes — 14 novembre 2026

Lot 3 sur `feature/profils-joueurs-guildes`, depuis `dev` `4c13f20` après fusion autorisée de la PR #60.
Complexités : profils 3/5 ; personnalisation et stockage des guildes 4/5.
Périmètre validé : mêmes univers et informations déjà publiques ; aucun titre/trophée nouveau.
Description de 2 000 caractères, logo JPEG/PNG/WebP 1 Mo / 512×512 et bannière 2 Mo / 1920×640 ; commandant seul.
GD ajouté aux deux images du projet après autorisation explicite pour décodage/réencodage et suppression des métadonnées.
Migration `Version20261114133852` préparée : création de `guild_profile` seulement, non appliquée à la base habituelle.
Vérifications ciblées sur bases et stockages isolés : 79 tests PHP / 386 assertions, 11 parcours navigateur distincts réussis après reprises, dont persistance des images après redémarrage ; validation Compose, compilation, lints ciblés et construction des images réussis. Synchronisation de la base habituelle non exécutée ; ces contrôles locaux ciblés sont distincts de la CI complète ci-dessous. Voir [le suivi](PROFILS_JOUEURS_GUILDES.md).
La [PR #61](https://github.com/c41m07/genesis-reborn/pull/61) est fusionnée avec autorisation dans dev au commit 2170607, après cinq jobs quality réussis sur 6389774 : 952 PHP, 13 Python et 194 parcours navigateur. GitGuardian classé comme identifiant de test par le propriétaire (skipped). Migration non appliquée à la base habituelle ; aucun déploiement Alpha.

## Simulateur : dix combats — 14 novembre 2026

Lot 2 sur `feature/simulateur-dix-combats`, depuis `dev` `a1abf08`, après fusion autorisée de la PR #59.
Complexité 4/5 : dix graines distinctes, estimation des victoires de l’attaquant sur dix, issues séparées et détails repliables, sans pertes moyennes.
Moteur, limites par combat, propriété des renseignements et absence d’écriture de jeu conservés.
42 tests PHP ciblés / 4 879 assertions, validation Compose, lint et compilation réussis ; parcours navigateur du simulateur réussi en clair/sombre, au clavier, sans JavaScript et à 320/390/1440 px. Contraste corrigé sans affaiblir le contrôle axe. Ce sont des vérifications ciblées, pas la suite complète ni la CI du nouveau lot.
Le [suivi et les mesures](CADRAGE_OUTILS_STRATEGIQUES.md) détaillent les limites et la reprise.
La [PR #60](https://github.com/c41m07/genesis-reborn/pull/60) a été fusionnée avec autorisation dans dev au commit `4c13f20`, après CI complète verte sur `c3eef94` : 925 PHP, 13 Python et 192 scénarios navigateur. Aucune migration ni livraison Alpha de ce lot.

## Interfaces des vols et classement — 14 novembre 2026

Lot 1 fusionné dans dev, issu de `feature/interfaces-vols-classement` et de `origin/dev`
`d5ec2a5` (PR #58 fusionnée, vérifiée sur GitHub). Complexité 3/5.
Le nom de mission ouvre composition et fret ; rappel à droite de la barre,
missions distinguées visuellement et propriété indiquée en texte. Les deux
commandes des catalogues échangent leur position sans changement de comportement.
Classement joueur croissant/décroissant, avec catégorie et sens conservés dans
la pagination et au retour du signalement ; instantanés et rangs conservés.
Les colonnes de guilde non triables perdent leur flèche.

Validation locale partielle : validation Compose, lint et compilation réussis.
Premier passage navigateur : 9/11 ; deux attentes de tri ont été ajustées pour
conserver le départage des égalités. Reprise : détails des vols et contrôle axe
réussis, puis redémarrage de l’environnement. Les deux parcours de tri et la
suite PHP/Python restent sans bilan final ; Docker était indisponible après ce
redémarrage. La [PR #59](https://github.com/c41m07/genesis-reborn/pull/59) a ensuite été fusionnée avec autorisation au commit `a1abf08`, après CI complète verte sur `ee49a42` : 913 PHP, 13 Python et 192 scénarios navigateur. Les limites locales ci-dessus restent historiques. Protection de dev vérifiée : PR et cinq jobs quality requis, règles imposées aux administrateurs. Aucun déploiement Alpha ni migration de ce lot.
Le [suivi](EVOLUTIONS_INTERFACES_FLOTTES.md) distingue les lots
suivants et les décisions validées du comportement encore à développer.

## Evolution de l’espionnage — 14 novembre 2026

Branche `feature/evolution-espionnage`, issue de `dev` actualisée à `cd58d52` après fusion autorisée de la PR #57 et sa CI complète verte (902 PHP, 13 Python, 191 navigateur). Les règles validées sont une base d’essais Alpha : [détection par rang et défense plafonnée](REGLES.md#espionnage). Aucun déploiement effectué.

Complexité 4/5 : configuration et calcul centralisé ; moyenne des rangs envoyés, tranches défensives et plafond ; conservation des niveaux technologiques, renseignements et traitements d’arrivée. Régression ciblée locale : 26 tests PHP / 1 986 assertions, validation Compose et lint complet réussis. Suite locale complète : 910 tests PHP / 211 572 assertions et 13 tests Python réussis ; trois scénarios navigateur réussis (150 fiches, mission PNJ et rapport privé, exploration réelle). Après la précision finale de texte, 14 tests du catalogue / 1 896 assertions repassent. Publication autorisée par le propriétaire ; les résultats de la CI complète sont à consulter dans la PR vers `dev`. La validation locale ne remplace pas cette CI. Aucune fusion ni aucun déploiement de ce lot autorisés.

Reprise : attendre et examiner la CI complète sur la dernière version de la PR, puis obtenir un accord explicite avant fusion. Aucun changement de schéma ni migration pour ce lot. Le suivi Alpha du 6 novembre conserve son verdict historique et ne décrit pas cette nouvelle livraison.

## Parcours UI/UX et économie — 13 novembre 2026

Lot local sur `feature/amelioration-parcours-ui-ux`, depuis `dev` 7189f45. Navigation, comparatif des colonies, catalogues condensés, recherche du manuel, rubriques des pages et bilan économique : voir le [suivi courant](PARCOURS_UI_UX.md). Le revenu métal/nano total de 10/h et le coût ponctuel de 100 E par minute entamée sont validés explicitement ; [règles](REGLES.md) et [étude](ETUDE_COUT_ELECTRIQUE.md). La branche est publiée avec la [PR #54 vers dev](https://github.com/c41m07/genesis-reborn/pull/54), sans fusion ni déploiement. Les résultats locaux et limites sont consignés dans le suivi ; la CI complète sur PR reste obligatoire avant fusion.

## Guildes et alliances — intégration du 11 novembre 2026

PR #36 fusionnée dans `dev` (`50e7f33`) après revue finale sans anomalie bloquante et CI verte : grades, relations personnelles, trois traités et projets collectifs. Le [guide](GUIDE_GUILDES_ALLIANCES.md) décrit l’utilisation et l’administration ; le [suivi](GESTION_GUILDES_ALLIANCES.md) conserve les preuves et les bilans historiques. Les nouvelles migrations sur base existante, le déploiement et les réglages/activations des modèles restent à réaliser séparément avec les autorisations prévues.

Mise à jour documentaire : 8 novembre 2026. [REGLES](REGLES.md) et
[DECISIONS](DECISIONS.md) restent les sources des arbitrages de jeu.

## Équilibrage des producteurs — 9 novembre 2026

Candidat confirmé après simulations, appliqué sur `codex/equilibrage-production-basique` issue de `dev`. Débits, ratio 4 H/A, prix et durées révisés ; courbes, rangs et files conservés. Les [mesures et limites](EQUILIBRAGE.md#production-des-batiments) distinguent construction cumulée et progression financée. Publication sur dev demandée et préparée depuis 1b02f4f, sans fusion vers Alpha ni déploiement. Autres travaux locaux exclus. Contrôles : validate, lint, suite complète de 522 tests PHP et 13 Python, compilation et diff. Navigateur : 121/122 en exécution complète, seul échec corrigé sans affaiblir le test, puis onglets 2/2 et intégration finale 15/15 ; résultats consolidés, pas une suite complète verte d’un seul passage sur la dernière base. CI GitHub non déclarée validée.

## État connu

L’Alpha est hébergée selon le propriétaire ; son domaine, sa version, son digest
et sa configuration ne sont pas vérifiés par cette consolidation documentaire.
Le jeu comprend comptes, univers/colonies, ressources, recherches, files, flottes,
combat, diplomatie, administration, wiki et compteur de fermeture.

Les anciens audits comptaient 14 phases validées sur 15, la phase d’accessibilité
manuelle restant partielle. Il s’agit de leur verdict historique, conservé avec
[la matrice d’exigences](archives/EXIGENCES_WORK.md), pas d’une validation nouvelle
des 15 phases ni d’une certification de production.

## Refonte de l’administration — 8 novembre 2026

Le premier lot sépare l’accueil et les rubriques et regroupe la surveillance
et les transports, avec fiches détaillées et lectures journalisées.
Ses contrôles locaux sont décrits dans le [cadrage de la refonte](REFONTE_ADMINISTRATION.md).
La publication ne vaut pas déploiement ni validation de la CI.

Les lots suivants préparent les fiches joueurs, la présence et le temps de jeu
estimés par univers, les interventions sur les ressources et échéances de flottes,
puis les dossiers et alertes configurables. Le cadrage décrit les vérifications
et les migrations préparées ; leur présence dans Dev ne vaut pas activation sur
une base existante.

Les boîtes, les 100 places partagées et la création guidée des événements sont
réalisées et vérifiées localement. Le périmètre fonctionnel validé de la refonte
est préparé sur Dev. L’activation sur une base existante reste distincte :
quatre migrations additives, sauvegarde vérifiée et validation explicite.
Les migrations seront soumises à validation avant application hors bases jetables.
## Priorités restantes

| Priorité | Travail et preuve attendue |
| --- | --- |
| État serveur | Relever domaine, version/digest exécutés, HTTPS et configuration : [déploiement](DEPLOIEMENT.md). |
| Mails de compte | Vérifier SMTP, expéditeur, liens HTTPS et réception réelle. Les tests utilisent un transport nul : [architecture](ARCHITECTURE.md). |
| Processus et alertes | Vérifier les volumes partagés, signaux, relais, destinataire privé et réception ; installer/superviser le job autorisé : [exploitation](EXPLOITATION.md). |
| Sauvegarde et reprise | Planifier, externaliser et protéger les archives ; tester la restauration distante et la reprise du transport RabbitMQ : [sauvegardes](SAUVEGARDES.md). |
| Référencement | Vérifier le vrai domaine, les réponses publiques et Search Console : [SEO](REFERENCEMENT.md). |
| Charge | Mesurer la capacité, la contention des verrous et définir supervision/SLA. Aucun résultat local ne fixe une capacité de production. |
| Accessibilité | Effectuer la revue manuelle avec lecteur d’écran et contrôles RGAA complets ; les tests axe et les contrastes échantillonnés ne la remplacent pas. |
| Équilibrage | Collecter les durées réelles, attentes, gains/pertes et reconstitution des forces. Soumettre toute évolution des paramètres à décision explicite. |

## Empires oubliés — chantier validé le 7 novembre 2026

**Socle d'activité préparé ; système complet non activé.**
Les [décisions validées](DECISIONS.md#empires-oubliés-vacances-et-occupation-jce--décisions-du-7-novembre-2026)
définissent l'inactivité à 14 jours, la transformation PNJ à 30 jours, les vacances
de 90 jours avec 15 jours entre activations et l'occupation JcE de 24 heures.

Le chantier utilise une branche `feature/empires-oublies` issue de `dev`.
Complexité globale : **4/5**, en raison des transferts de propriété, des missions
en cours et des opérations concurrentes. Les lots prévus sont :

| Lot | Périmètre ciblé | Preuves attendues |
| --- | --- | --- |
| 1 — Activité | Suivi par univers dans `PlayerUniverse`, visites authentifiées, états actif/inactif, alertes à J14/J27 et protections de pillage. | Frontières J14/J30, reconnexion, indépendance des univers, absence de doublons de courriels et initialisation sûre des comptes existants. |
| 2 — Vacances | Préférences, durée maximale, délai entre activations, suspension/reprise du compteur et blocage des missions incompatibles. | J90, délai de 15 jours après la fin, reprise sans remise à zéro, contrôles au départ et à l'arrivée des missions concernées. |
| 3 — Empire PNJ | Transformation transactionnelle, retrait de l'accès de l'ancien joueur, conservation des forces et recherches, gestion des opérations en cours et nouveau départ. | Aucun transfert partiel ou doublon ; compte et historique conservés ; aucune reprise de l'ancien empire ; pas de garnison standard injectée. |
| 4 — Vie des PNJ | Production plafonnée et reconstruction limitée ; conservation des comportements particuliers des PNJ existants. | Stockage et consommations respectés, coût/délai/plafond de reconstruction, pas de reconstruction en combat. |
| 5 — Occupation JcE | Extension de `FleetService` et `CombatEventService`, occupation persistée, place réservée, défense et capture après 24 h. | Rival et remise à zéro, perte/retrait de flotte, colonisateur consommé une seule fois, limites de colonies, production/pillage sans gestion anticipée et courses à l'échéance. |
| 6 — Interface et livraison | Libellé « Conquête JcE », statuts, échéances, avertissements, rapports, wiki et règles actuelles. | HTML rendu, clavier/focus/libellés/contrastes concernés ; contrôles locaux ciblés pendant le développement et CI complète réussie avant fusion. |

Le socle du lot 1 ajoute `PlayerUniverse.lastActivityAt`, un calcul des échéances
à 14/30 jours et l'enregistrement transactionnel des visites HTML réussies dans
l'univers sélectionné. Les nouvelles participations partent de leur création ;
les anciennes dates inconnues restent à `NULL` jusqu'à une première visite.
Les requêtes JSON, actualisations automatiques, erreurs et pages de compte ne
remettent pas le compteur à zéro. La migration `Version20261107180000` est
appliquée sur la base locale après accord et sauvegarde le 7 novembre 2026.
Les courriels J14/J27 sont préparés avec journal, commande de prévisualisation et
envois désactivés. Les deux textes sont validés le 7 novembre 2026 ; la migration
du journal `Version20261107220000` est appliquée localement après un accord
distinct et sauvegarde. Aucun envoi réel ni planification.
La suspension des protections à J14 est préparée, désactivée par défaut ; les échéances calculées ne
transforment aucun empire et ne bloquent pas encore une reconnexion à J30.

Vérifications locales du socle : `make validate` et une exécution complète de
`make test` réussissent (260 tests PHP, 13 tests Python), après correction des
premiers tests de visite. `make lint` échouait uniquement sur l'ajout de
`last_activity_at` en attente de migration. Après l'accord de l'utilisateur,
sauvegarde vérifiée et application de l'unique migration attendue,
`make validate` et `make lint` réussissent intégralement, schéma actif compris.
Voir la [trace d'application locale](DECISIONS.md#application-locale-du-socle-dactivité--7-novembre-2026).
Compilation frontend et parcours navigateur
non lancés pour ce lot sans changement d'interface. Aucun commit ni push.

Autres points d'intégration repérés : `PlanetNpcSeeder` initialise les forces standard ;
`CombatEventService` limite actuellement la conquête aux planètes PNJ sans
propriétaire. La transformation et l'occupation devront donc être traitées
explicitement, avec des tests d'intégration, plutôt que par un simple renommage.

### Sous-lot courriels J14/J27 — préparé le 7 novembre 2026

Les textes J14 et J27 ont été validés par le propriétaire le 7 novembre 2026.
Cette validation de contenu n'autorise ni migration supplémentaire ni envoi réel.
La commande et le journal sont préparés ; la nouvelle migration
`Version20261107220000` a été appliquée localement après accord distinct et
sauvegarde le 7 novembre 2026. Aucune activation ni installation
de job n'a été réalisée. Les [conditions et limites d'exploitation](EXPLOITATION.md#avertissements-dinactivité-j14j27)
précisent les relances normales et les cas où un résultat SMTP ambigu peut
encore produire un doublon.

Fichiers de ce sous-lot :

| Rôle | Fichiers ajoutés ou modifiés |
| --- | --- |
| Journal et sélection | `app/src/Entity/EmpireInactivityWarning.php`, `app/src/Entity/PlayerUniverse.php` (index), `app/src/Repository/GameRepository.php`, `app/migrations/Version20261107220000.php` |
| Envoi et commande | `app/src/Service/Player/EmpireInactivityWarningService.php`, `app/src/Command/EmpireInactivityWarningsCommand.php`, `Makefile` |
| Configuration par défaut | `app/config/services.yaml`, `.env.example`, `.env.prod.example` ; les fichiers privés restent conservés |
| Tests | `app/tests/Integration/EmpireInactivityWarningTest.php` |
| Documentation | `README.md`, `docs/DECISIONS.md`, `docs/FEUILLE_DE_ROUTE.md`, `docs/EXPLOITATION.md` |

Vérifications : `make validate` et une exécution complète de `make test` réussissent
(270 tests PHP, 13 tests Python, dont 10 nouveaux tests d'avertissement).
PHP, YAML, Twig, conteneur et mapping passent dans `make lint` ; la synchronisation
de la base active reste en échec attendu, car le nouveau journal et l'index
attendent validation pour leur migration. Aucune compilation frontend ni suite
navigateur n'a été relancée pour ce sous-lot sans modification d'interface.

Après accord pour cette migration, sauvegarde vérifiée et application locale,
`make validate` et `make lint` passent intégralement. `make empires-warn` donne
zéro éligible, sans écriture ni envoi ; l'interrupteur d'envoi est confirmé à
`false` dans la console locale. Voir la [trace d'application du journal](DECISIONS.md#application-locale-du-journal-davertissements--7-novembre-2026).

### Sous-lot protections à J14 — préparé le 7 novembre 2026

Le service `PlayerProtection` détermine la suspension commune au contrôle des
attaques et aux informations du classement et de l'interface. La protection de
récupération après combat n'est plus accordée à un empire inactif lorsque le
cycle est activé. Les échéances existantes et le délai entre activations ne
sont pas modifiés par la suspension. Un retour rétablit les règles normales,
sans renouvellement rétroactif. La limite d'écart de score, les alliances et le
stock protégé restent conservés.

Le nouveau réglage `FORGOTTEN_EMPIRES_ENABLED` vaut `0` par défaut ; les fichiers
privés ne sont pas modifiés. Aucune migration ni activation réelle dans ce lot.
La conversion à J30 et les vacances restent à développer avant activation globale.

Fichiers de ce sous-lot : `app/src/Service/Player/PlayerProtection.php`,
`app/src/Service/Combat/CombatEventService.php`,
`app/tests/Unit/PlayerProtectionTest.php`, `app/tests/Integration/CombatEventTest.php`,
`app/tests/Service/PlayerProtectionTest.php` (construction du service, assertions conservées),
`app/config/services.yaml`, `.env.example`, `.env.prod.example`, `README.md`,
`docs/DECISIONS.md`, `docs/FEUILLE_DE_ROUTE.md`, `docs/EXPLOITATION.md`.

Vérifications de ce sous-lot : `make validate`, `make lint` (schéma compris)
et une exécution complète de `make test` réussissent : 280 tests PHP et 13 tests
Python. Les 17 tests ciblés de protection/combat passent également. La première
exécution complète avait signalé un ancien test construisant le service avec
deux arguments ; son adaptation conserve toutes ses assertions, puis la suite
complète a été relancée avec succès. La syntaxe de ce test adapté est vérifiée
séparément après le lint. `FORGOTTEN_EMPIRES_ENABLED` est confirmé à `false`
dans la console locale. Aucune modification des templates ou assets par ce
sous-lot ; compilation frontend et tests navigateur non relancés. Aucun commit,
push, envoi réel ou migration dans ce sous-lot.

### Sous-lot calendrier des vacances — préparé le 7 novembre 2026

Décisions complémentaires validées : production et files suspendues, nouvelles
missions interdites, consultation autorisée, sortie volontaire après 48 heures
minimum. Les limites déjà validées de 90 jours et 15 jours entre activations
restent applicables. Complexité du mode complet : **4/5**, en raison des reprises
hors connexion et des traitements asynchrones.

Ce premier socle prépare le modèle de période et sa politique temporelle,
sans persistance automatique, route d'activation ni changement du gameplay.
La migration `Version20261107230000` prépare le seul historique des vacances ;
elle a été appliquée sur la base locale après accord explicite et sauvegarde
vérifiée le 7 novembre 2026.

Fichiers : `app/src/Entity/VacationPeriod.php`,
`app/src/Service/Player/VacationPolicy.php`, `app/tests/Unit/VacationPolicyTest.php`,
`app/migrations/Version20261107230000.php`, `README.md`, `docs/DECISIONS.md`,
`docs/FEUILLE_DE_ROUTE.md`, `docs/EXPLOITATION.md`.

Les 8 tests ciblés vérifient les bornes de 48 h/90 j/15 j, une expiration traitée
tardivement, le refus d'un chevauchement ou d'une période d'un autre empire,
le calcul du temps suspendu et le changement d'heure.

`make validate` et une exécution complète de `make test` réussissent : 288 tests
PHP et 13 tests Python. Le premier `make lint` signalait uniquement l'absence de
la table `vacation_period` et de sa clé étrangère. Après accord explicite,
sauvegarde vérifiée et application de l'unique migration en attente,
`make validate` et `make lint` réussissent intégralement, schéma actif compris.
Voir la [trace d'application locale](DECISIONS.md#application-locale-de-lhistorique-des-vacances--7-novembre-2026).
Aucun changement d'interface, aucune activation de vacances, aucun envoi réel,
commit ou push dans ce sous-lot.

Le raccordement économique est réalisé dans le sous-lot suivant.
Le raccordement militaire est réalisé dans le sous-lot suivant. Les décisions
complémentaires imposent le retour de toutes les flottes du joueur avant entrée,
et conservent uniquement les stationnements alliés déjà acceptés jusqu'à leur terme.
Suite requise avant ouverture : préférences avec accès, CSRF et validations clavier/HTML.

### Sous-lot pause/reprise économiques — 7 novembre 2026

La production et les consommations s'arrêtent sur les périodes réellement
enregistrées, puis reprennent sans rattraper les ressources des vacances.
Les stocks antérieurs sont conservés ; les événements restent évalués selon
leurs propres dates. Les trois files conservent durée de travail, coûts payés,
quantités et dépendances. Les têtes sont reportées avec de nouveaux jetons et
messages ; une sortie anticipée retire seulement le report inutilisé. L'expiration
à 90 jours et les traitements en retard fonctionnent sans visite ni nouvelle
commande d'expiration. Ajout, annulation et boosts des files sont bloqués pendant
la pause.

Le service interne démarre les vacances sous verrou et refuse les attaques
entrantes, combats en attente et missions militaires sortantes concernées.
Son activation est désactivée par défaut. Aucun endpoint ni période réelle
créée sur la base active ; aucune migration supplémentaire dans ce sous-lot.

Fichiers : `app/src/Service/Player/VacationCalendar.php`,
`app/src/Service/Player/VacationEconomyService.php`,
`app/src/Service/Player/VacationPolicy.php` (commentaires),
`app/src/Repository/GameRepository.php`, `app/src/Service/Colony/ResourceService.php`,
`app/src/Service/Queue/QueueService.php`, `app/tests/Integration/VacationEconomyTest.php`,
`app/tests/Service/BuildingBalanceTest.php` (construction du service, assertions conservées),
`README.md`, `docs/DECISIONS.md`, `docs/FEUILLE_DE_ROUTE.md`, `docs/EXPLOITATION.md`.

Vérifications du raccordement économique : `make validate`, `make lint`
(schéma compris) et une exécution complète finale de `make test` réussissent :
304 tests PHP et 13 tests Python. Les 18 tests ciblés (13 scénarios de vacances
et 5 contrôles de production existants) passent également. Une correction finale
enregistre les dates reportées des trois files avant leur rechargement à la sortie ;
la suite complète a été relancée après cette correction, sans échec.
L'activation locale reste confirmée à `false`. Aucun template ni asset n'est
modifié par ce sous-lot ; compilation et suite navigateur non relancées pour ce
lot interne sans interface exposée. Les travaux parallèles sur les rapports et
courriels sont préservés. Aucun commit, push ni nouvelle migration.

### Sous-lot compteur et avertissements en vacances — 7 novembre 2026

Complexité **4/5** : cohérence entre calcul d'activité, échéances et sélection
par lots. Les périodes réelles suspendent le compteur ; une consultation pendant
la pause conserve la dernière visite et un retour après la pause la renouvelle.
Les inconnues restent inconnues pendant les vacances. Les univers restent indépendants.

Les échéances J14/J30 sont prolongées uniquement par les vacances rencontrées
avant le seuil concerné. Les avertissements J14/J27 utilisent l'inactivité
effective : aucune alerte pendant la pause, aucun doublon J14 après reprise, et
date limite ajustée dans le courriel existant. La requête MariaDB filtre les
pauses, les seuils et les avertissements envoyés avant LIMIT ; les empires en
attente n'occupent pas toutes les places d'un lot. Une entrée en vacances après
la sélection est vérifiée de nouveau sous verrou. Une entrée lorsque J30 est
déjà dû est refusée.

Fichiers : `app/src/Service/Player/EmpireActivityService.php`,
`app/src/Service/Player/EmpireInactivityWarningService.php`,
`app/src/Service/Player/VacationPolicy.php`,
`app/src/Service/Player/VacationEconomyService.php`, `app/src/Repository/GameRepository.php`,
`app/tests/Controller/EmpireActivityTest.php`, `app/tests/Integration/EmpireInactivityWarningTest.php`,
`app/tests/Integration/VacationEconomyTest.php`, `app/tests/Unit/VacationPolicyTest.php`,
`app/tests/Unit/EmpireActivityServiceTest.php`, `app/tests/Unit/PlayerProtectionTest.php`,
`app/tests/Service/PlayerProtectionTest.php` (construction des services, assertions conservées),
`README.md`, `docs/DECISIONS.md`, `docs/FEUILLE_DE_ROUTE.md`, `docs/EXPLOITATION.md`.

Vérifications : `make validate`, `make lint` (schéma compris) et une exécution
complète de `make test` réussissent : 313 tests PHP et 13 tests Python.
Les 45 scénarios ciblés ont également réussi après adaptation d'une reconnexion
dans un test avançant l'horloge de 88 jours : la session de connexion expire
normalement indépendamment du compteur d'inactivité de l'empire. La suite
complète couvre ensuite le scénario supplémentaire d'une période terminée après
48 heures, sans bloquer les autres candidats d'un lot limité.
Tests de visites en vacances, plusieurs périodes, univers indépendants, activité
inconnue, échéances J14/J27/J30 ajustées, absence de doublon et entrée en pause
après sélection. Aucune nouvelle migration, activation, modification de texte
des courriels, envoi réel, commit ou push dans ce sous-lot. Aucun changement de
template ou d'asset : compilation frontend et suite navigateur non relancées.

### Sous-lot missions et protection des vacances — 8 novembre 2026

Complexité **4/5** : contrôles au départ/à l'arrivée, conservation des cargaisons,
des sondes et du carburant, transition des demandes d'orbite sous verrou.
Choix validés : toutes les propres flottes doivent être rentrées ; les stations
alliées acceptées gardent leur échéance ; les demandes d'orbite en attente sont
renvoyées à l'activation. Les transports tiers sont refusés et retournés s'ils
arrivent pendant les vacances.

Les huit départs sont interdits et les arrivées militaires devenues impossibles
reviennent sans combat ni espionnage. Les cargaisons refusées suivent le retour
normal, et les boosts de flotte sont bloqués avant consommation. Le service de
protection commun expose le libellé « Mode vacances » et sa date de fin. Aucune
migration, route, activation réelle ni modification d'interface dans ce sous-lot.

Fichiers : `app/src/Service/Player/VacationCalendar.php`,
`app/src/Service/Player/PlayerProtection.php`, `app/src/Service/Combat/CombatEventService.php`,
`app/src/Repository/GameRepository.php`, `app/src/Service/Player/VacationEconomyService.php`,
`app/src/Service/Fleet/FleetService.php`, `app/src/Service/Fleet/TransportService.php`,
`app/src/Service/Fleet/EspionageService.php`, `app/src/Service/Boost/BoostUseService.php`,
`app/tests/Integration/VacationMissionsTest.php`, `app/tests/Integration/VacationEconomyTest.php`,
`app/tests/Integration/CombatEventTest.php`, `app/tests/Unit/PlayerProtectionTest.php`,
`app/tests/Service/PlayerProtectionTest.php` (construction des services, assertions conservées),
`README.md`, `docs/DECISIONS.md`, `docs/FEUILLE_DE_ROUTE.md`, `docs/EXPLOITATION.md`.

Vérifications : `make validate`, `make lint` (schéma compris) et une exécution
complète de `make test` réussissent : 341 tests PHP et 13 tests Python.
Les 28 nouveaux cas couvrent les huit départs, les propres flottes déployées,
la protection et son expiration, les arrivées bloquées sans perte de vaisseaux,
le retour des transports refusés, les orbites et défenses alliées, l'absence de
débit après un départ refusé et le blocage des boosts avant consommation.
L'activation locale est confirmée à `false`. Aucun template ni asset n'est
modifié par ce lot backend ; compilation et tests navigateur non relancés.
Aucune migration supplémentaire, activation réelle, envoi de courriel,
modification du travail parallèle sur les rapports, commit ou push dans ce lot.

### Sous-lot préférences et affichage des vacances — 8 novembre 2026

Complexité 3 : formulaires sur l'univers courant, contrôles d'accès et CSRF,
reprise économique existante et affichage des files. Aucune migration.

Les préférences proposent une confirmation avant activation, les raisons d'un
blocage, les dates de début/fin, la sortie après 48 heures et le délai de 15 jours.
Les formulaires vérifient l'univers courant et son accessibilité ; le service
revalide les conditions sous verrou. Le drapeau désactivé masque l'interface et
interdit les activations directes. Une période enregistrée reste terminable.
Les files affichent leur progression figée et leur travail restant sans compteur
animé ; les annulations et constructions/recherches sont retirées pendant la pause.

Fichiers : `app/src/Controller/PreferencesController.php`,
`app/src/Service/Player/VacationEconomyService.php`,
`app/src/Service/Colony/GameShell.php`, `app/templates/player/preferences.html.twig`,
`app/templates/ui/colony_queues.html.twig`, `app/templates/ui/building_cards.html.twig`,
`app/tests/Controller/VacationPreferencesTest.php`, `README.md`,
`docs/DECISIONS.md`, `docs/EXPLOITATION.md`, `docs/FEUILLE_DE_ROUTE.md`.

Vérifications : `make validate`, `make lint` et `git diff --check` réussissent.
Une exécution complète de `make test` réussit avec 348 tests PHP et 13 tests Python.
Deux cas ajoutés ensuite sont validés avec la reprise ciblée des neuf tests de
`VacationPreferencesTest` (52 assertions) : ne pas présenter ce résultat comme
une exécution complète de 350 tests. Les tests couvrent confirmation, accès,
CSRF, refus d'une sortie prématurée, sortie à 48 heures, délai entre périodes,
flotte en retour, drapeau désactivé et progression figée.
`make test-browser BROWSER_ARGS="keyboard-navigation.spec.mjs wireframe-theme-screens.spec.mjs"`
réussit : trois scénarios existants, avec navigation clavier, thèmes et préférences.
Ils vérifient les régressions lorsque le drapeau est désactivé ; les états actifs
sont contrôlés par les tests HTTP et le HTML rendu, sans revue visuelle ni clavier
spécifique aux nouvelles commandes. Aucun asset modifié par ce sous-lot ;
compilation non relancée. Aucune migration, activation réelle, envoi de courriel,
commit ou push.

### Sous-lot prévisualisation J30 — 8 novembre 2026

Complexité 4 pour la conversion complète : propriété, accès, recherches, missions,
combats, files et relations sociales partagent l'inscription dans l'univers.
Ce sous-lot prépare uniquement une lecture contrôlable, sans nouvelle migration.

`make empires-preview PREVIEW_ARGS="--limit=100 --after-id=0"` parcourt les
échéances effectives après déduction des vacances, avant limitation du lot.
Il relève les colonies, flottes propres sorties et étrangères accueillies,
commandes payées, recherches existantes, combats ouverts sur place ou ailleurs,
alliances et responsabilités de guilde. Les comptes verrouillés ou non vérifiés
sont signalés, sans décider de leur traitement. Aucun courriel, rappel de flotte,
changement de propriétaire ou écriture n'est exécuté. Aucun mode d'application
n'existe. Une lecture locale de cinq lignes maximum n'a trouvé aucun empire dû.

Règles complémentaires validées : laisser finir les commandes déjà payées puis
figer les recherches ; renvoyer les propres flottes avec leurs cargaisons et
attendre la résolution d'un combat ouvert avant conversion. Mettre fin aux alliances
et quitter la guilde ; transférer sa direction au membre actif le plus ancien ou
archiver la guilde avec son historique s'il n'existe aucun successeur actif.

Fichiers : `app/src/Repository/GameRepository.php`,
`app/src/Service/Player/ForgottenEmpirePreviewService.php`,
`app/src/Command/ForgottenEmpirePreviewCommand.php`,
`app/tests/Integration/ForgottenEmpirePreviewTest.php`, `Makefile`, `README.md`,
`docs/DECISIONS.md`, `docs/EXPLOITATION.md`, `docs/FEUILLE_DE_ROUTE.md`.

Vérifications : `make validate`, `make lint` (schéma compris) et `git diff --check`
réussissent. Après correction d'un doublon de nom de galaxie dans les données du
nouveau scénario, une nouvelle exécution complète de `make test` réussit :
357 tests PHP et 13 tests Python. Les sept tests ajoutés vérifient les seuils
exacts, dates inconnues, univers inaccessibles, vacances actives/terminées/expirées,
filtrage avant limitation, pagination, inventaire et absence d'écriture, comptes
restreints et options invalides. Les combats sur une planète étrangère ainsi que
les liens de guilde et d'alliance sont inclus. La commande locale a été exécutée
en lecture seule. Aucun changement d'interface ou d'asset dans ce sous-lot :
compilation et tests navigateur non relancés. Aucune migration, conversion réelle,
activation, programmation, envoi de courriel, suppression, commit ou push.

Plan restant pour la conversion :
1. Le détachement d'identité et le nouveau départ sont préparés dans le sous-lot
   ci-dessous ; leur migration locale a été appliquée après accord le 8 novembre.
2. Revalidation J30, accès tardifs et journal préparés dans le sous-lot interne
   ci-dessous, sans activation.
3. Retours, files, alliances, succession et archivage sont raccordés au service
   interne ; la migration de journal et d'archive a été appliquée après accord local.
4. Distinguer ces empires des garnisons PNJ standard, conserver leurs recherches
   et leurs véritables forces, puis raccorder la conquête et l'occupation.

### Sous-lot identité détachée et nouveau départ — 8 novembre 2026

Complexité 4 : changement de relation de propriété, anciens accès, historiques et
traitements automatiques. Après comparaison puis validation explicite, le joueur
est détaché comme propriétaire de l'empire oublié ; `formerPlayer` est une simple
référence historique, sans droits. L'identifiant de l'empire et les relations des
colonies, forces, recherches, files et messages sont conservés. La contrainte
unique joueur/univers est inchangée : un seul empire possédé, plusieurs anciens
empires sans joueur. Une nouvelle inscription n'hérite pas des recherches ni des
déblocages de la précédente.

Les anciennes sélections et les actions directes sont bloquées. Les empires
détachés quittent le classement des joueurs et les recherches de destinataires ;
les anciens échanges restent lisibles par leurs interlocuteurs sans nouvelles
réponses. Les affichages de carte, rapports, messages et guildes utilisent un nom
compatible avec l'absence de propriétaire. Les transports et protections de joueur
ne s'appliquent plus à ces empires. Les actions humaines sont distinguées des
traitements automatiques, qui peuvent terminer les commandes déjà payées.

Fichiers principaux : `app/src/Entity/PlayerUniverse.php`,
`app/migrations/Version20261108010000.php`, `app/src/Service/Universe/UniverseService.php`,
`app/src/Service/Universe/UniverseContext.php`, `app/src/Service/Colony/ActionGuard.php`,
`app/src/Service/Colony/GameShell.php`, `app/src/Service/Queue/QueueService.php`,
`app/src/Repository/GameRepository.php`, `app/src/EventListener/EmpireActivitySubscriber.php`,
`app/src/Service/Player/EmpireActivityService.php`,
`app/src/Service/Player/EmpireInactivityWarningService.php`,
`app/src/Service/Player/VacationCalendar.php`, `app/src/Service/Player/PlayerProtection.php`,
`app/src/Service/Player/LeaderboardService.php`, `app/src/Service/Player/DiplomacyService.php`,
`app/src/Service/Player/RelationResolver.php`, `app/src/Service/Player/SocialMessageService.php`,
`app/src/Service/Fleet/TransportService.php`, `app/src/Service/Fleet/EspionageService.php`,
`app/src/Service/Combat/CombatEventService.php`, `app/src/Controller/PreferencesController.php`,
`app/src/Controller/ColonyController.php`, `app/src/Controller/DiplomacyController.php`,
les templates de noms dans `app/templates/player/` (messages, contacts, guildes),
`app/templates/universe/map.html.twig`, `app/templates/universe/map_v2.html.twig`,
`app/templates/admin/index.html.twig`, `app/tests/Integration/ForgottenEmpireIdentityTest.php`,
les fabriques de joueurs de `app/tests/Unit/PlayerProtectionTest.php` et
`app/tests/Service/PlayerProtectionTest.php` (assertions inchangées), ainsi que
README, décisions, exploitation et cette feuille de route.

La migration a été appliquée sur la base locale après accord explicite le
8 novembre 2026. Elle ne détache aucun empire. Une sauvegarde locale a été créée dans
`var/backups/forgotten-identity-before-20261108010000/` :
`genesis-genesis_reborn_v2-20261107T235329Z-RbpeDnQMYVEo.sql.gz`.
Intégrité gzip et manifeste vérifiés ; aucun essai de restauration de cette archive.
Le convertisseur J30 et les transitions de guildes/flottes restent à implémenter.

Vérifications finales : `make validate` et `git diff --check` réussissent.
Une nouvelle exécution complète de `make test` réussit : 369 tests PHP et
13 tests Python. Les douze tests de ce sous-lot couvrent nouveau départ sans
héritage, plusieurs empires détachés et unicité de l'empire possédé, anciens accès,
autre univers sélectionné, fin des recherches payées, messages historiques,
refus des nouveaux échanges/transports, prévisualisation et classement, schéma,
application/retour de migration sur la base isolée et refus d'un retour destructeur.
Après adaptation des fabriques de joueurs dans quatre anciens scénarios de
protection, leurs assertions restent inchangées et la suite complète est verte.
`make test-browser BROWSER_ARGS="keyboard-navigation.spec.mjs report-feedback-details.spec.mjs"`
réussit sur le contenu final : quatre scénarios existants (clavier, renseignements,
combats, suivi admin), sur une base éphémère avec migrations. Les états détachés
sont vérifiés par les tests HTTP et le HTML rendu, sans audit visuel complet.
`make lint` valide PHP, Twig, YAML, injection et mapping ; il échoue uniquement sur
la synchronisation de la base locale, car la nouvelle migration attend l'accord.
Le schéma isolé est validé après application de la migration dans les tests.
Aucun asset modifié par ce sous-lot : compilation non relancée. Aucun commit,
push, envoi, activation ou détachement d'empire réel. Le code reste en attente de
l'application locale de `Version20261108010000` avant utilisation (attente levée
par l'application autorisée ci-dessous).

Application locale autorisée : une seule migration était en attente. L'intégrité
gzip et le manifeste de l'archive précitée ont été revérifiés avant application.
`Version20261108010000` a exécuté ses trois requêtes avec succès. `make validate`,
`make lint` et `git diff --check` réussissent désormais, schéma actif synchronisé.
Une lecture de contrôle compte six empires, zéro empire oublié et zéro référence
d'ancien propriétaire : aucun empire réel n'a été détaché. Les deux drapeaux
`FORGOTTEN_EMPIRES_ENABLED` et `EMPIRE_INACTIVITY_WARNINGS_ENABLED` sont confirmés
à `false` dans le conteneur de commande. Les 369 tests PHP, 13 tests Python et
quatre scénarios navigateur avaient réussi avant application sur ce même code ;
ils ne sont pas relancés sans changement applicatif. Aucun commit, push ou envoi.

### Sous-lot conversion J30 interne — 8 novembre 2026

Complexité 4 : transactions de conversion, missions asynchrones, droits d'accès,
recherches et guildes. Décisions complémentaires validées pendant ce lot : retour
des flottes alliées stationnées, maintien des attaques étrangères en route et même
échéance pour les comptes verrouillés ou non vérifiés avec activité connue.

Le service revalide J30 et les vacances sous verrou, diffère toute conversion
pendant un combat concerné (y compris ailleurs), termine les commandes payées
arrivées à échéance et rappelle les flottes avant détachement. Les retours déjà
engagés restent intacts, les anciens jetons sont invalidés. Les transports non
livrés gardent leur cargaison et un rapport de refus cohérent. Le retour d'une
flotte d'empire désormais sans joueur peut être traité automatiquement.
Les alliances sont terminées sans suppression de leurs lignes historiques.
La guilde reçoit le plus ancien adhérent actif admissible ou est archivée ;
ses messages et sa composition passée sont conservés, ses adhésions libérées.
Une visite après J30 ne réinitialisera plus le compteur quand le cycle sera activé ;
sélection d'univers, pages de jeu et commandes de joueur bloquent cet accès.
Les pillages d'empires effectivement convertis utilisent leur vraie garnison et
leurs recherches, sans protection ni écart de score de joueur. La conquête de
ces colonies avec occupation reste à raccorder.

Fichiers : `app/src/Entity/EmpireConversion.php`, `app/src/Entity/Guild.php`,
`app/migrations/Version20261108020000.php`,
`app/src/Service/Player/EmpireConversionService.php`,
`app/src/Service/Player/EmpireActivityService.php`, `app/src/Repository/GameRepository.php`,
`app/src/Service/Fleet/FleetService.php`, `app/src/Service/Fleet/TransportService.php`,
`app/src/Service/Combat/CombatEventService.php`, `app/src/Service/Colony/ActionGuard.php`,
`app/src/Service/Colony/GameShell.php`, `app/src/Service/Universe/UniverseContext.php`,
`app/src/Service/Universe/UniverseService.php`, `app/src/Service/Player/DiplomacyService.php`,
`app/src/Service/Player/RelationResolver.php`, `app/src/Service/Player/LeaderboardService.php`,
`app/src/Service/Player/SocialMessageService.php`, `app/src/Controller/DiplomacyController.php`,
`app/tests/Integration/EmpireConversionTest.php`,
`app/tests/Controller/EmpireConversionAccessTest.php`, README, décisions,
exploitation et cette feuille de route.

Vérifications : une exécution complète de `make test` réussit avec 388 tests PHP
et 13 tests Python. Les dix-neuf nouveaux cas couvrent les échéances exactes,
vacances, dates inconnues et comptes restreints ; rechargement de l'activité sous
verrou ; report de combat distant ; flottes, carburant et absence de duplication ;
transports ; succession, archive et historique ; rollback transactionnel complet ;
aller/retour de migration et protection de son journal ; accès HTTP à J30 et juste
avant, sélection d'univers et initialisation des dates inconnues.
Trois scénarios navigateur existants passent dans deux exécutions isolées :
`keyboard-navigation.spec.mjs`, puis `diplomacy-overview-anchors.spec.mjs` et
`diplomacy-empty-state.spec.mjs`. Un nom de scénario inexistant transmis au premier
filtre n'a sélectionné aucun cas supplémentaire ; les deux vrais scénarios de
diplomatie ont donc été exécutés séparément. Les situations converties sont
vérifiées par les tests HTTP et d'intégration ; pas d'audit visuel des archives.
`make validate` et `git diff --check` réussissent. `make lint` réussit jusqu'au
mapping, puis échoue sur le schéma local en attente de la nouvelle migration.
Le schéma isolé après cette migration est synchronisé. Aucun asset modifié,
compilation non relancée. Sauvegarde vérifiée et référence dans EXPLOITATION.md.
Le drapeau est confirmé à `false` dans le conteneur de commande. Aucun ordonnanceur,
point d'entrée d'application, conversion réelle, envoi, commit ou push ;
`Version20261108020000` attend l'accord explicite avant application locale.

Application locale autorisée le 8 novembre 2026 : une seule migration était en
attente, `Version20261108020000`. L'archive précitée a été revérifiée (gzip et
manifeste), puis la migration a exécuté ses trois requêtes avec succès.
`make validate`, `make lint` et `git diff --check` réussissent désormais, schéma
actif synchronisé. Les lectures de contrôle indiquent zéro conversion, zéro
guilde archivée et zéro empire oublié. Les deux drapeaux d'activation sont
confirmés à `false` dans le conteneur de commande. Les 388 tests PHP, 13 tests
Python et trois scénarios navigateur avaient réussi sur le même code avant
application ; ils ne sont pas relancés sans changement applicatif. Aucun
ordonnanceur, envoi, commit ou push. L'attente d'accord ci-dessus est levée
pour cette application locale uniquement.

### Sous-lot états et historique d'occupation — 8 novembre 2026

Complexité 5 pour la conquête complète : changement de propriétaire différé,
concurrence, capacité réservée, flotte défensive, carburant et traitements
asynchrones. Première étape ciblée : modèles et règles de transition, sans
modifier la résolution actuelle des combats. Décisions complémentaires validées :
24 heures non accélérables, carburant de stationnement prévu au départ avec
restitution de l'inutilisé, attente d'un combat ouvert et conservation du temps
de l'occupant qui reste vainqueur.

L'état unique d'une planète conserve l'occupant, la flotte de référence, le combat
d'origine, l'échéance, le statut et un jeton renouvelé aux changements. L'historique
garde les états avant/après. Une contrainte unique par combat empêche de rejouer
un ancien démarrage après remplacement ; l'essai transactionnel vérifie le rollback
et le maintien du nouvel occupant. Un remplacement repart pour 24 heures ; un
maintien conserve le délai et le jeton. Les états en échec libèrent leur réservation
sans effacer l'historique. Le comptage est prêt par inscription dans l'univers.

Fichiers : `app/src/Entity/PlanetOccupation.php`, `app/src/Entity/OccupationEvent.php`,
`app/src/Service/Combat/OccupationPolicy.php`, `app/src/Repository/GameRepository.php`,
`app/migrations/Version20261108030000.php`, `app/tests/Unit/OccupationPolicyTest.php`,
`app/tests/Integration/PlanetOccupationTest.php`, README, décisions et exploitation.

Reste à raccorder avant ouverture : victoire de combat, vérifications sous verrou,
réservations dans colonisation/conquête, immobilisation et renforts des forces,
combats contre l'occupant, carburant et restitution, traitements d'échéance,
transfert physique et consommation du vaisseau, interruption lors d'une conversion
J30 et affichages. La conservation des bâtiments/stocks et l'obligation de vaincre
les forces PNJ restantes sont désormais validées dans le sous-lot de transfert.
Les booléens de forces/capacité de la politique devront venir de l'état de base
verrouillé, jamais de paramètres de formulaire. Aucune route ni aucun job ne
déclenche cette politique. La capture immédiate actuelle reste inchangée.
`Version20261108030000` a été appliquée après accord local ; détail ci-dessous.

Vérifications : `make validate` et `git diff --check` réussissent. Une exécution
complète de `make test` réussit : 404 tests PHP et 13 tests Python. Les seize
nouveaux tests couvrent les 24 heures exactes, absence de capture prématurée,
maintien/remplacement, historique, perte de forces ou capacité, exclusions des
cibles, unicité par planète et combat, rejeu d'un ancien combat annulé en
transaction, libération des réservations et aller/retour protégé de migration.
`make lint` valide PHP, Twig, YAML, injection et mapping, puis échoue sur le
schéma local en attente des deux nouvelles tables ; le schéma isolé après
migration est synchronisé. Aucun template, asset ou parcours existant modifié :
compilation et navigateur non relancés. Une seule migration est en attente.
Sauvegarde créée et vérifiée (gzip et manifeste) :
`var/backups/occupation-before-20261108030000/genesis-genesis_reborn_v2-20261108T002415Z-q4HS6uEhmIJn.sql.gz`.
Aucun essai de restauration revendiqué. Aucune migration locale appliquée,
occupation réelle, modification de capture, activation, envoi, commit ou push.

Application locale autorisée le 8 novembre 2026 : une seule migration était en
attente. L'archive précitée a été revérifiée (gzip et manifeste), puis
`Version20261108030000` a exécuté ses huit requêtes avec succès. `make validate`,
`make lint` et `git diff --check` réussissent désormais ; le schéma actif est
synchronisé. Les lectures de contrôle comptent zéro occupation, zéro événement
d'occupation et zéro conversion. Les deux drapeaux restent à `false` dans le
conteneur de commande. Les 404 tests PHP et 13 tests Python avaient réussi
sur le même code avant application ; ils ne sont pas relancés sans changement
applicatif. Aucun changement de capture, ordonnanceur, envoi, commit ou push.
L'attente de migration décrite ci-dessus est levée pour la base locale uniquement.

### Sous-lot démarrage d'occupation après victoire — 8 novembre 2026

Complexité 5 : résolution du combat, forces survivantes, carburant réservé,
capacité de colonisation, conversions et répétitions de messages. Aucun nouveau
schéma. Le branchement reste derrière `FORGOTTEN_EMPIRES_ENABLED=0`.

`OccupationService` est appelé après les pertes d'une victoire de conquête. Les
conquérants et la planète sont verrouillés par le résolveur. L'occupation conserve
la planète chez son propriétaire actuel, le vaisseau de colonisation et la flotte
sur place ; elle ne donne ni récompense de capture ni butin à la flotte conquérante.
Le journal par combat est consulté avant création. Le statut `occupying` est
visible dans les flottes, la carte et le rapport avec son échéance, sans annoncer
une planète déjà capturée. Les libellés de mission sont renommés « Conquête JcE ».

Les réservations sont déduites des limites au devis et à l'arrivée de colonisation,
ainsi qu'à une nouvelle victoire de conquête ; elles restent comptées après
désactivation du drapeau. Le devis d'une nouvelle conquête prévoit 24 heures
de stationnement et la mission en garde la durée. Une désactivation pendant le
trajet ou le combat renvoie cette mission sans capture immédiate. Une flotte non
retenue récupère la réserve inutilisée. L'occupation empêche les vacances et les
boosts de trajet. Une conversion J30 journalise son annulation, libère la place
et renvoie sa flotte avec le carburant de stationnement inutilisé.

Le coordinateur de capture à 24 heures et les combats contre l'occupant restent
à développer. Pour empêcher une reprise sans affronter sa flotte, attaques et
conquêtes d'une cible déjà occupée sont temporairement refusées dans ce mode
incomplet. Renforts, concurrence et capture physique doivent être raccordés avant
toute activation ; aucune tâche ne déclenche actuellement cette capture.

Fichiers : `app/src/Service/Combat/OccupationService.php`,
`app/src/Service/Combat/CombatEventService.php`, `app/src/Service/Fleet/FleetService.php`,
`app/src/Repository/GameRepository.php`, `app/src/Service/Player/EmpireConversionService.php`,
`app/src/Service/Colony/ColonyView.php`, `app/src/Controller/ColonyController.php`,
`app/src/Controller/FleetController.php`, `app/templates/combat/report.html.twig`,
`app/templates/fleet/index.html.twig`, `app/templates/ui/flights.html.twig`,
`app/templates/universe/index.html.twig`, `app/tests/Integration/OccupationStartTest.php`,
`app/tests/Integration/EmpireConversionTest.php` (nouvelle dépendance du constructeur),
`app/tests/browser/conquest-report.spec.mjs` (libellé attendu), README, décisions,
exploitation et cette feuille de route. Aucun travail parallèle sur le détail des
rapports n'est remplacé : ajout ciblé du seul état d'occupation.

Vérifications : `make validate`, `make lint` (schéma compris) et `git diff --check`
réussissent. Une exécution complète de `make test` réussit : 414 tests PHP et
13 tests Python. Les dix nouveaux cas couvrent la victoire simulée et le maintien
du vaisseau/carburant, absence de butin et de récompense prématurée, répétitions,
affichages HTTP et HTML, devis à 24 heures, réservation et deuxième victoire sans
place, empire oublié avec stocks/recherches conservés, vacances et boosts,
absence de vaisseau de colonisation, annulation par conversion et désactivation
en cours de combat. Les anciennes conquêtes immédiates restent couvertes sans
affaiblir leurs assertions, derrière le drapeau désactivé.
`make test-browser BROWSER_ARGS="keyboard-navigation.spec.mjs conquest-report.spec.mjs"`
réussit : deux scénarios existants. Le libellé du titre attendu est adapté au
renommage demandé ; la confidentialité et les autres assertions sont conservées.
Ces scénarios couvrent les régressions du mode actuel ; les occupations actives
sont vérifiées par les tests HTTP, sans revue visuelle complète de cet état.
Aucun asset modifié : compilation non relancée. Drapeau confirmé à `false` dans
le conteneur de commande ; lecture de contrôle : zéro occupation, zéro événement
d'occupation, zéro conversion. Aucune migration supplémentaire, activation,
capture réelle, envoi, commit ou push.

### Sous-lot contrôle des forces et transfert définitif interne — 8 novembre 2026

Complexité 5 : propriété, capacité réservée, retours ennemis, files payées,
recherches et déblocages permanents, répétitions et rollback. Décisions confirmées :
bâtiments et stocks sans dégâts supplémentaires ; forces hostiles restantes ou
reconstruites à vaincre sans remettre le délai à zéro ; attente des flottes PNJ
en retour puis défaite de leurs forces ; annulation à la capture des commandes
non terminées et de leurs dépendances sans bénéfice pour le conquérant.

`OccupationReadiness` distingue troupes propres, alliés, défenses/vaisseaux PNJ,
garnisons et flottes hostiles de passage ou de retour. Les quantités nulles ne
bloquent pas. Les alliés seuls ne remplacent pas les forces et le vaisseau propres
nécessaires à la capture. La lecture ne modifie pas le monde.
`OccupationCaptureService` revalide sous verrou état et jeton, participations,
univers et planète. Il traite d'abord les commandes échues de l'ancien empire,
puis vérifie les forces et la capacité. Les attentes gardent le temps acquis.
Il annule les commandes restantes et leurs dépendances sans remboursement,
matérialise les stocks, consomme exactement un vaisseau et transfère la planète
dans une transaction. Bâtiments, stocks et anciennes recherches sont conservés
à leur emplacement d'origine ; seules les recherches du conquérant s'appliquent
après capture. Les bâtiments hérités ne copient pas les déblocages permanents.
La récompense est déclenchée selon les règles existantes, sans doublon. Le rapport
de combat reçoit le résultat final sans compter le vaisseau consommé en perte
de combat. Le journal garde annulations et flottes renvoyées. Les survivants
rentrent comme dans la conquête actuelle ; aucune flotte vide n'est mise en retour.
Les échecs d'occupation libèrent la réserve et renvoient les survivants avec le
carburant de stationnement inutilisé. Tout échec annule ensemble les changements.

Fichiers : `app/src/Service/Combat/OccupationReadiness.php`,
`app/src/Service/Combat/OccupationCaptureService.php`,
`app/src/Service/Combat/OccupationPolicy.php`, `app/src/Repository/GameRepository.php`,
`app/src/Service/Queue/QueueService.php`, `app/src/Service/Fleet/FleetService.php`,
`app/src/Service/Requirement/CatalogAccess.php`,
`app/tests/Integration/OccupationReadinessTest.php`,
`app/tests/Integration/OccupationCaptureTest.php`, README, décisions et exploitation.

Vérifications : `make validate`, `make lint` (schéma compris) et `git diff --check`
réussissent. Une exécution complète finale de `make test` réussit : 431 tests PHP
et 13 tests Python. Les dix-sept nouveaux cas couvrent absence d'écriture de la
lecture, forces PNJ/défenses reconstruites/garnisons/alliés, retours et flottes
vides, maintien du délai après combat, perte du vaisseau, capture conservant les
stocks et bâtiments sans recherches/déblocages hérités, consommation unique et
récompense sans doublon, commandes annulées et anciens messages inopérants,
chaînes de recherche ailleurs, commande de défense échue à l'échéance, attente
du retour hostile puis défaite, messages prématurés/périmés, carburant inutilisé,
rollback après modification du propriétaire, refus sous drapeau désactivé et
capture avec le seul vaisseau de colonisation. Les assertions de rollback lisent
la base après l'échec, sans réutiliser les identifiants remis à zéro en mémoire
par Doctrine lors d'une suppression annulée.
Aucun template, route ni asset modifié dans ce sous-lot : compilation et tests
navigateur non relancés. Pas de nouvelle migration. Le drapeau est confirmé à
`false` dans le conteneur de commande ; les lectures actives comptent zéro
occupation, zéro événement d'occupation et zéro conversion.
Le service reste interne et désactivé, sans point d'entrée d'application ni
traitement automatique d'échéance. Reste à raccorder renforts, combats contre
l'occupant et concurrence, puis déclenchement/reprises des captures avant toute
activation. Aucune capture réelle, envoi, suppression de fichier, commit ou push.

Les arbitrages ouverts dans DECISIONS.md précèdent les lots concernés. Les migrations,
envois réels de courriels et actions réseau restent soumis aux validations du dépôt.
REGLES.md sera actualisé avec les lots applicatifs pour distinguer comportement
livré et décisions futures.

## Limites de la dernière revue manuelle UI

Les notes UI historiques signalaient encore des variantes à compléter : chaînes
de prérequis et lecteur d’écran ; gestes canvas, vues avec flottes et contrastes
complets ; combats/défense/orbite et fin réelle des trajets ; clavier/thème clair
de l’inventaire ; transitions temporelles des événements ; suppression d’univers
en administration et erreur 500 réelle. Les tests ultérieurs ne suffisent pas à
clore automatiquement chacune de ces grilles manuelles. Leur trace datée est dans
[la validation UI](archives/VALIDATION_INTERFACE_2026-11-05.md) et l’historique Git.

## Mesures Alpha

Distinguer nouveaux joueurs et joueurs avancés. Relever jalons de progression,
fréquence de connexion, périodes d’attente, ressources limitantes, pertes et coût
de reconstitution. L’administration expose l’état actuel ; elle ne reconstitue
ni le temps de jeu actif, ni des dates de jalons ou d’anciennes files supprimées.
La couverture et les limites sont dans [EXPLOITATION](EXPLOITATION.md).

## Vérifications de référence

Le lot applicatif 453c414 a validé Compose, lint, 232 tests PHP, 13 tests Python
et compilation Node 24. La suite navigateur a donné 97/98, puis une reprise 3/3
après adaptation du retour vers le nouvel accueil : 98 cas couverts par résultats
consolidés, sans unique exécution complète verte. Ce relevé ne vaut ni validation
de la CI du lot documentaire ni preuve de déploiement de l’Alpha.

La consolidation courante modifie les documents et leurs liens, sans changer
les paramètres de jeu, les sources PHP/JS, les secrets ou la base.

### Renforts et combats de contestation — lot du 8 novembre 2026

Complexité 5/5 : le résolveur précédent regroupait tous les défenseurs. Le mode
spécifique aux occupations distingue désormais les PNJ, le titulaire avec ses
alliés, et les camps rivaux ; le comportement des autres combats reste conservé.
Les recherches sont prises dans chaque empire, y compris pour une garnison de
l'ancien joueur. La limite de rounds existante conserve la retraite des attaquants.
Le titulaire encore présent avec son propre vaisseau de colonisation conserve son
délai après victoire ; un nouveau conquérant admissible reçoit 24 heures complètes.
Une attaque seule ne réserve ni place ni planète. Les protections débutant et de
score ne bloquent pas ces combats sur le monde occupé ; les alliances s'appliquent.

Les renforts du titulaire et de ses alliés sont admis sans remise à zéro, y compris
pendant un combat ouvert. La réserve couvre le temps restant, au minimum la fenêtre
de combat ; la consommation court depuis le stationnement effectif et la part non
utilisée revient avec la flotte. Un jeton lie chaque départ à son occupation : une
reprise invalide les renforts encore en route. Le retour en fin de capture/échec et
le rappel à J30 incluent aussi les soutiens alliés, sans transférer leurs vaisseaux.

Fichiers : `OccupationDeployment.php`, `Version20261108040000.php`,
`OccupationService.php`, `CombatEventService.php`, `CombatSimulator.php`,
`OccupationCaptureService.php`, `FleetService.php`, `EmpireConversionService.php`,
`OccupationStartTest.php`, `PlanetOccupationTest.php`, `CombatSimulatorTest.php`,
README, décisions et exploitation. Les autres travaux locaux sont conservés.

La migration ajoute seulement la table de suivi des renforts et deux clés
étrangères. L'aller/retour est testé dans la base isolée, dans l'ordre des migrations,
et le retour arrière refuse de supprimer un historique non vide. La base active
attend un accord distinct pour cette migration ; `make lint` valide PHP, YAML, Twig,
conteneur et mapping, puis signale cette seule table manquante au contrôle du schéma.
La sauvegarde préalable a été créée et vérifiée (gzip et manifeste, sans essai de
restauration) :
`var/backups/reinforcements-before-20261108040000/genesis-genesis_reborn_v2-20261108T013432Z-v7FeIMpOYzSP.sql.gz`.

Les deux drapeaux restent à `false` dans la console ; les lectures comptent zéro
occupation, événement d'occupation et conversion. Aucun courriel modifié ou envoyé,
aucune activation, migration active, capture réelle, suppression de fichier, commit
ou push. Restent le déclenchement automatique et les reprises des échéances, ainsi
que les vérifications du parcours complet avant activation. Les résultats finaux
de tests sont consignés ci-dessous après la fin des exécutions.
Validation finale de ce lot : exécution complète `make test` réussie avec 443 tests
PHP (206 885 assertions) et 13 tests Python. Les 12 nouveaux tests couvrent les camps
indépendants, le déterminisme et les arrivées différées, les renforts propres/alliés,
leur devis et remboursement, les jetons périmés, le délai conservé, la reprise avec
nouveau délai, l'attaque simple, les recherches PNJ, le renfort pendant un combat,
la perte du vaisseau propre malgré celui d'un allié et la protection de l'historique.
Le premier passage ciblé a révélé l'ordre de dépendance à adapter dans le test de
migration ; le suivant, le montage explicite du service interne dans le test.
Ces corrections ont été revérifiées par les tests ciblés puis la suite complète.
`make validate` réussit. `make lint` ne passe pas intégralement : le dernier contrôle
attend l'application autorisée de `Version20261108040000` ; les autres étapes passent.
Aucun template ni asset n'a été modifié dans ce sous-lot ; compilation et tests
navigateur non relancés. Ces résultats sont locaux, sans affirmation sur la CI.
### Migration locale et contrôles différés des captures — 8 novembre 2026

L'application locale autorisée de `Version20261108040000` a réussi après nouvelle
vérification gzip/SHA-256 de la sauvegarde existante. Trois requêtes ont été
exécutées ; `make validate` et `make lint`, contrôle du schéma inclus, réussissent.
La lecture des données actives constate zéro déploiement, occupation et conversion.

Sous-lot suivant de complexité 4/5 : réutilisation de la boîte d'envoi transactionnelle
et du transport Messenger existants. `OccupationScheduler` programme le contrôle
initial à l'échéance et un nouvel examen après maintien de l'occupation au combat.
`OccupationDueHandler` appelle la capture sous verrou ; les jetons périmés et les
messages rejoués sont sans effet après fin/changement de titulaire. Une réception
prématurée reprogramme l'échéance sans modifier les 24 heures. En cas de forces
hostiles/retours PNJ/combat ouvert, le contrôle s'arrête sans capture et la victoire
suivante le reprogramme. Aucune boucle ne sonde en permanence les forces à vaincre.
La commande de reprise manuelle examine les occupations actives par lots, en lecture
par défaut. `--apply` programme les contrôles et est refusé quand le drapeau est à
zéro. Un contrôle déjà publié peut être rejoué sans double capture ; l'indication
`already_queued` concerne la boîte d'envoi SQL, pas l'état du courtier de messages.
Les univers inaccessibles sont signalés sans être traités.

Fichiers modifiés dans ce sous-lot : `app/src/Message/OccupationDue.php`,
`app/src/MessageHandler/OccupationDueHandler.php`,
`app/src/Service/Combat/OccupationScheduler.php`,
`app/src/Command/OccupationResumeCommand.php`,
`app/src/Service/Combat/OccupationService.php`,
`app/src/Service/Combat/OccupationCaptureService.php` (commentaire),
`app/src/Service/Notification/OutboxRelay.php`, `app/src/Repository/GameRepository.php`,
`app/config/packages/messenger.yaml`, `Makefile`,
`app/tests/Integration/OccupationStartTest.php`, README, décisions, exploitation et
présente feuille de route. Aucun travail parallèle n'est remplacé.

Le premier passage ciblé a révélé la liste des événements à compléter dans le
sélecteur du relais et deux comparaisons d'identité des objets date après relecture
sous verrou. Elles comparent désormais leurs valeurs, sans changer le délai attendu.
Les 25 tests ciblés d'occupation réussissent ensuite, dont cinq nouveaux cas couvrant
le relais/délai réel, la réception anticipée, les doublons, le combat à l'échéance,
la reprogrammation après maintien, la commande sans écriture puis l'application
idempotente avec curseur, les paramètres invalides, les jetons périmés, l'univers
fermé et le refus d'action sous drapeau désactivé. La suite complète est ensuite
lancée ; ses résultats finaux seront consignés après sa fin.

`make occupations-resume` est exécuté localement en prévisualisation : zéro
occupation et aucune écriture. Aucun `--apply`, lancement de nouveau processus,
activation, envoi, capture réelle, suppression de fichier, commit ou push. Aucune
nouvelle migration nécessaire pour ce sous-lot. Restent l'automatisation J30, les
vérifications des points d'entrée du cycle complet et son activation autorisée.
Validation finale : exécution complète `make test` réussie, avec 448 tests PHP
(206 942 assertions) et 13 tests Python. `make validate`, `make lint` (PHP, YAML,
Twig, conteneur, mapping et schéma actif) et `git diff --check` réussissent.
`debug:messenger` confirme le message et son traitement. Les drapeaux restent à
`false` dans la console. Aucun template ni asset modifié dans ce sous-lot : build
et tests navigateur non relancés. Ces résultats sont locaux et ne décrivent pas
la CI GitHub. L'automatisation J30 et le parcours complet restent à traiter.
### Commande de conversion J30 par lots — 8 novembre 2026

Complexité 5/5 : la conversion est irréversible pour l'accès du joueur à l'ancien
empire. Le lot réutilise le service transactionnel existant sans réécrire ses règles
ni modifier les migrations. `EmpireConversionBatch` réutilise la sélection de
prévisualisation (vacances effectives avant limite, univers accessibles, activité
connue) et fait revalider chaque empire sous verrou. La commande dédiée propose
`--limit`, `--after-id`, `--all` et l'application explicite `--apply` conditionnée
au drapeau. Par défaut, aucune écriture ni envoi.

Un combat ouvert reporte l'empire et le parcours `--all` poursuit les autres lots.
Cela évite qu'un premier lot rempli de combats bloque les suivants lors d'un
futur passage planifié. Les passages suivants repartent sans curseur pour reprendre
les reports. Une erreur technique arrête le lot et indique le dernier identifiant
traité, afin de reprendre sans sauter l'empire en erreur. La transaction courante
est annulée ; les conversions précédentes restent validées. Les détails de l'erreur
sont journalisés et la commande renvoie un échec, sans poursuivre avec un gestionnaire
Doctrine potentiellement fermé. Les nouveaux passages ignorent les empires déjà
convertis et ne dupliquent pas le journal.

Fichiers : `app/src/Service/Player/EmpireConversionBatch.php`,
`app/src/Command/EmpireConversionCommand.php`,
`app/src/Service/Player/EmpireConversionService.php` (commentaire), `Makefile`,
`app/tests/Integration/EmpireConversionTest.php`, README, décisions et exploitation.
Les modifications parallèles restent préservées sur `feature/empires-oublies`.

Les 23 tests ciblés de conversion réussissent (151 assertions), dont huit nouveaux
cas pour la lecture sans écriture, le refus d'application sous drapeau désactivé
avec candidats ou lot vide, les lots après combat bloquant puis leur réexamen,
les comptes verrouillés/non vérifiés sans exemption, les dates inconnues et vacances
effectives, le mode explicite et les paramètres invalides, le rollback après une
commande payée invalide avec conservation de la conversion précédente et du curseur,
et le parcours complet malgré un premier lot intégralement reporté. La suite
complète est lancée ensuite ; ses résultats finaux seront consignés à sa fin.

`make empires-convert` est exécuté localement en lecture : zéro éligible, conversion,
report ou erreur. Aucun `--apply` actif, migration, nouveau processus, planification
périodique, courriel, conversion réelle, suppression de fichier, commit ou push.
Le traitement est prêt à être planifié après les vérifications du parcours complet
et l'autorisation d'activation ; aucun passage périodique n'est annoncé comme installé.
Validation finale de ce lot : exécution complète `make test` réussie avec 456 tests
PHP (207 014 assertions) et 13 tests Python. `make validate`, `make lint` (PHP, YAML,
Twig, conteneur, mapping et schéma) et `git diff --check` réussissent. Les tests
ciblés et complets n'ont révélé aucun échec dans ce lot.
Une seconde prévisualisation locale avec `--all --limit=1` confirme un parcours
terminé avec zéro candidat, sans écriture. Le drapeau traité dans la console est
`false` ; la base active conserve zéro conversion, occupation et déploiement.
Aucun template ni asset modifié dans ce sous-lot : compilation et navigateur non
relancés. Les résultats décrivent les contrôles locaux, pas la CI GitHub.
Prochain sous-lot : vérification du parcours complet J14/J30, retour à zéro,
vacances et conquête JcE, avant toute activation ou planification réelle.
### Parcours complet et actions des cartes — 8 novembre 2026

Complexité 5/5 : vérification ciblée des enchaînements d'inactivité, protections,
avertissements, conversion, identité et capture, sans audit global du projet.
`ForgottenEmpireJourneyTest` utilise exclusivement la base isolée, un `MockClock`,
un transport de courriels simulé et les services réels. Le premier parcours fait
passer l'empire par J14/J27/J30, vérifie la perte d'accès, crée via le formulaire un
nouvel empire sans les recherches de l'ancien, refuse l'ancienne colonie et part
via le formulaire de flotte à la conquête de cet ancien empire. Le combat conserve
les recherches PNJ véritables ; l'occupation attend ses 24 heures puis capture une
seule fois sans transmettre ces recherches. Le même parcours vérifie les liens
des deux cartes pour titulaire, rival et allié, avec leur échéance UTC.

Un second parcours vérifie le retour à J29 : visite reconnue, protections encore
valables restaurées, conversion empêchée à l'ancienne échéance et nouveau cycle
d'avertissement. Le troisième active 90 jours de vacances à J13 : consultation
sans réinitialisation d'activité, production et commande payée suspendues, échéance
J30 déplacée de 90 jours, avertissements effectifs puis conversion et achèvement
de la commande déjà payée.

La vérification a révélé un écart concret : les cartes ne proposaient pas la
conquête des empires oubliés ni les renforts/contestations des occupations. Correction
ciblée dans `ColonyController`, les deux cartes et un fragment commun. Les contrôles
de combat et d'alliance déjà validés sont réutilisés. La disponibilité des places
reprend la limite de recherche existante et ses réservations, au lieu du seul plafond
d'univers. Les mondes oubliés sont identifiés comme PNJ dans les données de carte.
Aucune règle ni valeur d'équilibrage changée.

Fichiers : `app/tests/Integration/ForgottenEmpireJourneyTest.php`,
`app/src/Controller/ColonyController.php`, `app/templates/universe/map.html.twig`,
`app/templates/universe/map_v2.html.twig`,
`app/templates/ui/map_conquest_actions.html.twig`, README, décisions et présente
feuille de route. Les travaux parallèles sont conservés.

Les trois parcours ciblés passent avec 91 assertions. Les premières exécutions ont
nécessité de recharger les entités entre requêtes HTTP/changements de joueur et de
comparer les propriétaires par identifiant persistant plutôt que par identité
mémoire ; aucun contournement ou assouplissement des contrôles métier n'est ajouté.
Les liens nouveaux sont des ancres natives, avec libellés explicites et échéances
`time[datetime]` en UTC ; les styles/focus existants sont réutilisés. Ces contrôles
RGAA ciblés ne constituent pas un audit de conformité. Les parcours navigateur
existants clavier/carte V2/conquête sont lancés dans l'instance jetable, sous les
règles par défaut ; les cas d'occupation activés sont vérifiés par les tests HTTP
isolés. Aucun nouveau script navigateur ni dépendance installé.

La suite complète et les vérifications ciblées de navigateur sont lancées ; leurs
résultats finaux seront consignés après achèvement. Aucune nouvelle migration,
activation, planification réelle, conversion active, envoi, suppression de fichier,
commit ou push. Les textes de courriels déjà validés restent inchangés.
Validation finale : une exécution complète `make test` réussit avec 459 tests PHP
(207 105 assertions) et 13 tests Python. Un dernier ajustement de l'envoi rapide
classique supprime le formulaire de colonisation quand aucune place n'est disponible,
y compris avec un autre vaisseau au dépôt. Le fragment
`app/templates/ui/map_quick_mission.html.twig` fait donc aussi partie du lot.
Après cette retouche, les trois parcours complets sont relancés et réussissent
avec 93 assertions, dont le cas de place réservée. L'échéance V2 réutilise ensuite
la classe claire `gv2-panel-note`, plutôt que la classe grisée de la carte classique,
pour conserver un contraste lisible sur son panneau sombre ; les trois parcours
et `make lint` sont de nouveau relancés avec succès. Aucun style nouveau n'est ajouté.
Les rapports de contraste calculés à partir des couleurs CSS réutilisées sont
supérieurs à 4,5:1 pour les liens et le texte d'échéance concernés ; ce calcul
ciblé ne remplace pas une mesure complète du rendu ni un audit RGAA.

Une première exécution navigateur ciblée réussit intégralement : trois scénarios
(conquête/rapport, carte V2 et carte classique, clavier). Après la retouche du
formulaire, les deux scénarios carte/clavier sont relancés et réussissent. Il s'agit
de réussites locales consolidées par reprises ciblées, pas d'une exécution de toute
la suite navigateur ni d'une validation de CI. Les trois parcours nouveaux utilisent
les règles activées uniquement dans les tests HTTP/services ; le navigateur conserve
les valeurs par défaut pour vérifier leur absence de régression.

`make validate`, `make lint` (PHP, 87 Twig, YAML, conteneur, mapping et schéma) et
`git diff --check` réussissent. Aucun asset modifié dans ce lot : compilation non
relancée. Le drapeau traité par la console reste à `false` ; les lectures réelles
comptent zéro conversion et zéro occupation. Aucun courriel réel, migration active,
activation, planification, suppression de fichier, commit ou push.
Prochaine étape : revue finale ciblée de la fonctionnalité et préparation de sa
mise en service, avant toute activation ou installation de passage périodique.
### Revue finale et préparation de mise en service — 8 novembre 2026

Complexité 5/5 : revue limitée aux fins/reprises d'occupation, récompenses, rapports,
conversion par lots et activation. Trois cas de régression ont d'abord reproduit
des défauts : un renfort humain pouvait recevoir un boost lorsque le camp PNJ
remportait le combat ; un rapport pouvait encore annoncer une occupation active
après remplacement ou échec. Correction du filtrage des récompenses par camp,
avec conservation du retrait existant à la limite des rounds. Le verdict affiché
est aussi fondé sur le camp réel et distingue la position maintenue.

`OccupationReportState` complète la lecture des rapports depuis les événements de
fin/remplacement, en vérifiant planète, titulaire et échéance. Il ne réécrit pas
l'instantané historique. Un rapport de maintien affiche ainsi la capture ultérieure,
et un ancien rapport remplacé ne devient pas une victoire de l'ancien titulaire
si un rival capture ensuite la planète. Les changements parallèles des autres
rapports sont conservés par des modifications limitées.

La revue du chemin de désactivation a également relevé qu'un rival vainqueur d'un
combat déjà ouvert ne devait pas tenter de commencer une nouvelle occupation sous
drapeau désactivé. Le combat se termine désormais normalement et sa flotte rentre,
sans remplacement ni capture immédiate. Un test recharge le conteneur sous drapeau
à zéro pour vérifier ce cas réel de configuration, sans modifier les règles actives.

Fichiers du lot : `app/src/Service/Combat/OccupationReportState.php`,
`app/src/Service/Combat/CombatEventService.php`,
`app/src/Service/Combat/CombatReportPresenter.php`,
`app/src/Controller/CombatReportController.php`,
`app/templates/combat/report.html.twig`,
`app/tests/Integration/OccupationStartTest.php`,
`app/tests/Service/CombatReportPresenterTest.php`, README, décisions, exploitation
et présente feuille de route. Aucune migration ni dépendance supplémentaire.

Les tests ciblés passent après correction. Le cas de consultation une journée
plus tard a nécessité de laisser expirer la session normalement sur la connexion,
puis de reconnecter le client de test : aucune protection de session n'a été modifiée.
La compilation `make assets` (qui exécute `npm run build`) réussit. Une première
suite complète réussit avec 464 tests PHP et 13 tests Python ; le test supplémentaire
de désactivation passe ensuite (9 assertions), puis une nouvelle suite complète
est lancée sur le contenu final. `make validate` et `make lint`, schéma compris,
réussissent. Le navigateur complet est lancé avec ses 107 scénarios ; les résultats
finaux seront consignés après sa fin.

Les quatre commandes de prévisualisation réussissent sans écriture et ne trouvent
aucun candidat. La base locale est à jour (41 migrations, zéro en attente), compte
six empires joueurs dont quatre sans date d'activité connue, et reste sans occupation
ni conversion. Aucun backfill des dates inconnues n'est effectué.

La section courante de mise en service d'EXPLOITATION.md donne les prévisualisations,
le chargement des deux drapeaux dans les services, la cadence proposée de cinq
minutes, l'ordre avertissements/conversions avec verrou, les reprises et le comportement
de désactivation. Aucune ligne cron ni configuration privée n'est modifiée. La
réception SMTP réelle reste à valider sur une adresse explicitement autorisée ;
les textes J14/J27 déjà validés ne sont pas modifiés. Aucun envoi, activation,
conversion réelle, suppression de fichier, commit ou push pendant la revue.
Clôture de la revue locale : les six contrôles requis réussissent sur le contenu
à conserver. `make validate` et `make lint` valident configuration, PHP, 87 modèles
Twig, conteneur, mapping et schéma. `make assets` exécute `npm run build` avec succès.
La dernière exécution complète `make test`, après le cas de désactivation, réussit
avec 465 tests PHP (207 161 assertions) et 13 tests Python. La suite navigateur
complète réussit en une seule exécution : 107 scénarios, sans reprise ciblée ni
échec final (33,7 minutes). `git diff --check` réussit. Ces contrôles sont locaux
sur l'arbre courant, qui conserve aussi les travaux parallèles ; aucune CI GitHub,
publication ou livraison sur un serveur n'est annoncée.

La vérification de transport affiche uniquement son type (`smtp`), sans identifiant,
mot de passe, hôte ou envoi. L'aide de `mailer:test` confirme qu'un essai peut être
fait sans activer le cycle et sans passer par Messenger. La réception réelle n'est
pas encore vérifiée : l'étape suivante proposée est un seul courriel de test,
après validation de son contenu et autorisation d'une adresse de réception.
Les deux courriels d'inactivité déjà validés restent inchangés.

Aucune activation, installation cron, conversion réelle, envoi SMTP, migration,
suppression de fichier, commit ou push pendant cette revue. Le code et la procédure
sont préparés ; les autorisations de réception réelle puis de mise en service restent
distinctes de cette validation locale.
### Essai SMTP autorisé — 8 novembre 2026

Après validation du contenu et fourniture de l'adresse de réception dans le chat,
un unique courriel de test a été envoyé par `mailer:test`, avec l'expéditeur configuré
du jeu. Objet : `[TEST] Réception des courriels — Genesis Reborn`. La commande s'est
terminée avec le code 0 après l'appel au transport SMTP, sans nouvelle tentative.
Cela établit l'acceptation par le transport, pas la réception en boîte : confirmation
du destinataire encore attendue. Le destinataire n'est pas recopié dans le dépôt.
Aucun drapeau, passage périodique, empire ou journal d'avertissement n'est modifié.
### Alignement sur la conversion quotidienne de minuit — 8 novembre 2026

Complexité 5/5 : changement validé de calendrier et de perte d'accès, avec impacts
sur sélection SQL, visites concurrentes, vacances et contenu d'avertissement.
`EmpireConversionSchedule` calcule en UTC le premier minuit parisien après le seuil
et le dernier passage civil. Le calcul ne remplace pas le calendrier par 86 400
secondes : les changements d'heure sont testés. Les échéances et le journal de
conversion reprennent cette date arrondie. La sélection déduit les vacances et
filtre l'échéance de minuit avant la limite du lot.

Les blocages anticipés de GameShell, UniverseContext, ActionGuard via le contrôle
d'activité, les visites, les vacances et les occupations sont retirés : seul le
détachement effectif retire le contrôle. Les visites légitimes avant conversion
peuvent donc sauver l'empire, y compris après minuit si le passage est retardé ou
reporté par un combat. Les protections et accès après détachement restent en place.
La commande d'avertissements dispose de --all avec --send pour vider les lots au
passage quotidien et s'arrêter en cas d'échec ou de stagnation. Aucun envoi réel.

Fichiers concernés : `app/src/Service/Player/EmpireConversionSchedule.php`,
`EmpireActivityService.php`, `ForgottenEmpirePreviewService.php`,
`EmpireInactivityWarningService.php`, `VacationEconomyService.php`,
`app/src/Repository/GameRepository.php`, `app/src/Service/Colony/GameShell.php`,
`app/src/Service/Universe/UniverseContext.php`,
`app/src/Service/Combat/OccupationService.php`,
`app/src/Command/EmpireInactivityWarningsCommand.php`,
les tests ciblés d'activité/accès/conversion/prévisualisation/avertissements/parcours
et vacances, `app/tests/Unit/EmpireConversionScheduleTest.php`, README, décisions,
exploitation et présente feuille de route. Les travaux parallèles sont conservés.

58 tests ciblés de calendrier et de conversion, puis 15 tests d'avertissement,
réussissent. Le premier passage complet relève trois attentes antérieures à adapter :
dates brutes de vacances et rejet des vacances dès J30. Elles sont remplacées par
les assertions de minuit et de contrôle jusqu'au détachement, avec un test distinct
maintenant le refus pour un empire réellement oublié. Les contrôles sont relancés
sur le contenu final ; leurs résultats seront consignés à leur terme.

Le fuseau du Debian local est confirmé `Europe/Paris`. L'exemple de cron courant
est préparé pour `0 0 * * *`, en précisant que le fuseau du planificateur doit être
vérifié sur chaque hôte ; aucun réglage global de fuseau, crontab, drapeau ou service
n'est modifié dans ce lot. La réception du précédent courriel SMTP a été confirmée
par l'utilisateur. Les deux phrases révisées des courriels doivent être présentées
pour validation avant activation. Aucune nouvelle migration, conversion active,
suppression de fichier, installation, commit ou push.
Validation finale de l'alignement : les 56 tests ciblés d'activité/vacances/calendrier
et avertissements réussissent après adaptation des trois anciennes attentes. La
nouvelle exécution complète `make test` réussit : 473 tests PHP (207 198 assertions)
et 13 tests Python. `make validate`, `make lint` (PHP, Twig, YAML, conteneur, mapping,
schéma) et `git diff --check` réussissent. Les prévisualisations J30 et avertissements
restent à zéro sans écriture. Le planificateur crontab est présent et le fuseau
réel de l'hôte pointe sur `/usr/share/zoneinfo/Europe/Paris` ; rien n'est installé.
Les occupations et leurs 24 heures ne sont pas modifiées par ce calendrier.

Pas de nouvelle interface, asset, dépendance ni migration : compilation et
navigateur ne sont pas relancés pour ce sous-lot de calendrier/accès. Les 107
scénarios navigateur du lot précédent restent un résultat antérieur, pas une
nouvelle exécution sur ce changement. Les parcours d'accès concernés sont couverts
par les tests HTTP sous drapeau activé uniquement dans la base isolée. Les deux
maquettes de courriels révisées doivent être validées avant activation et installation
du passage quotidien. Aucun envoi réel ni drapeau privé modifié dans ce lot.

### Activation locale autorisée — 8 novembre 2026

Complexité 3/5 : configuration privée, redémarrage ciblé et planification avec
effets différés sur les empires. L'utilisateur valide les deux maquettes de
courriels révisées et la mise en service à minuit Paris. Une sauvegarde fraîche
vérifiée est conservée dans `var/backups/empires-activation-local-20261108-01/`,
avec copies privées de la configuration et de la crontab précédente.

Les deux drapeaux sont maintenant à `1` localement. Les services app, worker,
relay et notifier sont recréés et sains ; aucun service de données n'est recréé.
La crontab de `caim` contient le bloc quotidien à 00 h Europe/Paris, protégé par
`app/var/run/empires-daily.lock`. Premier passage prévu : 9 novembre 2026 à 00 h CET.
L'environnement minimal du planificateur a été vérifié par prévisualisations :
zéro avertissement et zéro conversion éligibles, aucun envoi ni conversion réel.

Fichiers de code ciblés : `app/tests/bootstrap.php` et
`docker/compose.browser-test.yaml`, qui conservent explicitement les drapeaux
désactivés pour les tests ordinaires malgré l'activation locale. Les tests dédiés
activent toujours le cycle dans la base isolée. README, décisions et exploitation
sont actualisés ; la configuration privée et la crontab restent hors dépôt.
Les 104 tests ciblés passent avec les variables d'activation héritées à `1` avant
l'isolation par bootstrap. Les autres travaux locaux restent préservés.
Aucune nouvelle migration, dépendance, suppression, commande Git risquée ou mise
en service distante. Aucun courriel de test supplémentaire envoyé.

Validation après activation : `make validate`, `make lint` et `make test`
réussissent : 473 tests PHP (207 200 assertions) et 13 tests Python. Le parcours
navigateur ciblé `conquest-report.spec.mjs` réussit en une exécution dans le
Compose isolé après activation (1 scénario). Les quatre services locaux sont
confirmés sains avec les deux drapeaux à `1` ; Symfony les interprète à `true`.
`git diff --check` réussit. La compilation et les 107 scénarios navigateur complets
sont des résultats du lot précédent, sans nouvelle exécution complète pour cette
activation. Aucun commit, push ou contrôle de CI distant effectué.


### Préparation de publication sur dev — 8 novembre 2026

Après demande explicite de commit et push, le lot des empires oubliés est isolé
sur `feature/publication-empires-oublies`, à partir de `origin/dev` (`8665074`).
Les travaux de mails de compte et de rapports déjà publiés sont conservés.
Aucune configuration privée, sauvegarde ou capture générée n'est incluse.
Le contenu applicatif de publication est comparé aux sources locales vérifiées.

Les six contrôles requis réussissent : `make validate`, `make lint`, `make test`
(473 tests PHP, 207 200 assertions ; 13 tests Python), `make assets`
(`npm run build`), `make test-browser` (107 scénarios en une exécution complète,
33,6 minutes) et `git diff --check`. La suite navigateur complète est cette fois
rejouée après l'alignement sur minuit et l'isolation des drapeaux de test.
Ces contrôles locaux ne constituent pas une validation de la CI GitHub.
La publication du code ne déploie pas le jeu et n'active pas le cycle à distance.


## Gestion des guildes et alliances — 11 novembre 2026

Sur `feature/gestion-guildes-alliances`, les grades et les rivalités personnelles sont complétés par les trois traités officiels : alliance complète, non-agression et défense mutuelle. Permissions séparées, propositions/signatures versionnées, paix bilatérale, historique et contrôles de flotte sont implémentés. La validation locale compte 634 tests PHP, 13 tests Python et 154 scénarios navigateur consolidés ; voir [le suivi détaillé](GESTION_GUILDES_ALLIANCES.md) pour les reprises et limites.

Prochaine étape : projets collectifs à paliers avec dons définitifs et boosts, selon les décisions utilisateur du suivi. Chaque fonctionnalité terminée est committée ; la PR #36 reste en brouillon jusqu’à la fin du périmètre. Ni migration sur une base existante ni déploiement de ces extensions. Le conflit de numéro de migration avec le chantier de reconstruction doit être résolu avant intégration.


### Complément terminé localement : projets collectifs — 11 novembre 2026

Les projets de guilde sont implémentés avec paliers successifs, dons atomiques définitifs, coûts/minimums figés au lancement, permission de lancement déléguable et un boost maximum par compte et modèle. L’administration configure chaque univers, sans modèle actif par défaut. La distribution asynchrone est reprenable et respecte l’inventaire existant ; départ, dissolution et archivage ont un traitement explicite.

Validation finale locale : `make validate`, `make lint`, compilation, 670 tests PHP (209722 assertions), 13 tests Python et 156 scénarios navigateur consolidés ; les reprises sont expliquées dans [le suivi](GESTION_GUILDES_ALLIANCES.md). Aucun déploiement ni nouvelle migration de la base habituelle. La PR #36 reste en brouillon, notamment pour coordonner le numéro de migration déjà utilisé par le chantier de reconstruction. Les fonctionnalités demandées sont implémentées ; l’intégration des deux branches et l’activation des modèles restent distinctes.

### Reconstruction ciblée des empires oubliés — 11 novembre 2026

Implémentation sur `feature/reconstruction-empires-oublies`, depuis `dev`
synchronisée. Le périmètre validé comprend les références par planète à la conversion,
les vaisseaux et défenses de tous rangs, la réparation partielle déduite, le paiement
et les durées normaux au chantier partagé, la priorité aux unités finançables les
moins coûteuses et la suspension/reprise cinq minutes après combat. La reconstruction
continue sous occupation et cesse à la capture, sans modifier les PNJ classiques.
Les anciennes commandes payées restent acquises sans relever le plafond ; les
anciens empires oubliés sans référence sont exclus.

La migration additive `Version20261111010000` est préparée pour une seule nouvelle
table. Elle ne doit pas être remplacée par une synchronisation globale du schéma,
notamment lorsque la base locale contient des éléments d'autres branches.
Les tests couvrent conversion, rangs et modèles, coûts/durées, priorité et ressources
manquantes, retours, commandes préexistantes, réparation partielle, interruptions,
occupation, capture, messages rejoués et migration sur base isolée.
Validation locale finale : `make validate`, `npm run build`, `make test`
(607 tests PHP, 209 704 assertions et 13 tests Python), `make lint` avec la
connexion de test isolée (schéma compris), et `git diff --check` réussissent.
Les 153 tests navigateur passent en deux lots complets indépendants (89 et 64),
avec application de la migration finale dans leurs bases jetables. Les reprises
antérieures interrompues ne sont pas comptées comme des exécutions complètes.

Le lint lancé sur la base de jeu signale un écart expliqué : la nouvelle table
n'y est pas appliquée, et cette base conserve le schéma de guildes d'un autre
chantier. Aucun élément de ce schéma n'a été supprimé ni modifié. La migration
de jeu reste non appliquée, sans conversion rétroactive ni déploiement distant.
Ces contrôles locaux ne constituent pas une validation de la CI GitHub.
Un commit pour cette fonctionnalité terminée, puis une seule PR vers `dev`
pour le périmètre complet.


Réconciliation du 11 novembre 2026 : `dev` intégré à la branche guildes ; le numéro historique partagé est pris en charge par `Version20261111173000`, sans modifier la migration originale des grades ni appliquer les migrations à la base habituelle. Voir [le suivi](GESTION_GUILDES_ALLIANCES.md). PR #36 toujours en brouillon.

Validation locale de la réconciliation : installation vierge isolée, 23 tests ciblés (205 assertions), 701 tests PHP (209981 assertions), 13 tests Python, 158 scénarios navigateur consolidés après reprise clavier inchangée, lint/schéma, compilation et contrôles Git réussis. Aucune migration de la base habituelle ; détails et limites dans [le suivi](GESTION_GUILDES_ALLIANCES.md).


## Interfaces et flottes — lot démarré le 11 novembre 2026

Branche feature/interfaces-classements, depuis dev actualisée (68ca8d9).
Premier lot : catégories et présentation du classement, prérequis navigables,
signalement privé, colonnes de surveillance et retrait du journal des missions.
Aucune migration ni modification des règles de déplacement dans ce lot.
Le [suivi](EVOLUTIONS_INTERFACES_FLOTTES.md) donne les complexités, décisions,
dépendances et vérifications réelles. Le premier lot est désormais intégré ; les trois maquettes Diplomatie, Profil
et Inventaire sont validées ; leur intégration est terminée dans le lot K.


### Validation locale du premier lot

Les six contrôles locaux sont réussis : validation Compose, lint/schéma,
compilation, make test (703 tests PHP, 209995 assertions et 13 tests Python),
159 scénarios navigateur distincts consolidés après reprises et contrôle du diff.
Le focus des prérequis préserve les mises à jour automatiques ; les tests de
missions suivent l’historique conservé après retrait du journal. Le wiki est
synchronisé et vérifié. La limite de PHPUnit est fixée à 512 Mo dans la seule
commande de test du Makefile après un épuisement initial à 128 Mo.

Ces résultats ne constituent ni une validation de CI GitHub ni un déploiement.
Le [suivi](EVOLUTIONS_INTERFACES_FLOTTES.md) détaille les reprises, les fichiers et
les décisions métier désormais confirmées pour les lots suivants. Les trois
maquettes sont validées avant leur intégration.

### Intégration du lot A et démarrage du lot B

Le lot A est fusionné dans dev par la [PR #41](https://github.com/c41m07/genesis-reborn/pull/41),
au commit b58b3b41ca121649093248ba0539921ae30da3ab. La CI complète
[43107155705](https://github.com/c41m07/genesis-reborn/actions/runs/43107155705)
est réussie : cinq jobs quality, y compris les contrôles de sauvegarde/restauration
isolés. La protection de dev impose la PR et ces cinq jobs. Aucun déploiement Alpha.

Le lot B part de cette dev sur feature/carte-confidentialite-flottes :
cercle extérieur vert des systèmes possédés, bouton distinct de retour au système
dans la fiche planète (la croix conserve le retour galaxie), sondes étrangères
masquées côté serveur et résumé des autres flottes visibles par nombre et points.
Aucun élargissement de visibilité ni migration de la base de jeu.
Validation locale terminée le 12 novembre : six contrôles exécutés, 706 tests PHP
(210052 assertions), 13 tests Python et 161 scénarios navigateur distincts réussis.
Le parcours clavier général a nécessité une reprise seule après dépassement de
durée en parallèle ; aucun test assoupli. Le [suivi](EVOLUTIONS_INTERFACES_FLOTTES.md)
détaille les preuves. Lot B désormais fusionné dans dev par la PR #42 au commit fe39a001340548e32ad0b52ef00f686f5a004bd6,
après CI complète verte 43120982563 (cinq jobs quality et sauvegarde/restauration
isolées vérifiés). Aucun déploiement Alpha.

### Lot C — branche démarrée depuis dev actualisée

Branche feature/colonies-journal-absence, issue de fe39a00.
Les détails privés des colonies et flottes sont intégrés ; tests ciblés réussis :
1 test serveur (15 assertions), 1 parcours survol/clavier/toucher, et 25 tests de
transition pendant la vérification de la migration sur base jetable.
Lint/schéma et compilation réussissent. Le lot complet n’est pas encore validé.
La migration additive des acquisitions et du journal est préparée, non appliquée
à la base de jeu locale. Accord de sauvegarde/migration et choix du point de départ
du journal demandés, en attente. Ordre par acquisition et journal restent à réaliser.
Voir le [suivi](EVOLUTIONS_INTERFACES_FLOTTES.md) ; aucun commit/push/PR du lot C.

### Suite du lot C après décisions

Le démarrage du journal à la première ouverture est confirmé. La migration locale
Version20261112010000 est appliquée après sauvegarde vérifiée (intégrité gzip et
manifeste). Les nombres de planètes, ressources, flottes et notifications restent
identiques avant/après. Aucun serveur Alpha concerné.
L’acquisition est désormais enregistrée sur les quatre voies, et les trois listes
de colonies partagent l’ordre avec reprise approximative explicite. Le journal
filtre les catégories validées et conserve les non-consultés ; POST contrôlé par
participation, propriété, CSRF et identifiants affichés. Les mises à jour automatiques
ne visitent ni ne consultent le journal. Les 16 tests ciblés passent (127 assertions).
Les vérifications complètes du lot C restent à exécuter avant tout commit.

### Validation locale finale du lot C

Les six contrôles sont exécutés et examinés : make validate, make lint (mapping/
schéma compris), make assets, make test (713 tests PHP, 210134 assertions et
13 tests Python), make test-browser et contrôle du diff.
163 scénarios navigateur distincts réussis, couverture exacte consolidée après
adaptation de trois parcours à la consultation explicite et au nouvel état vide.
Le clavier est exécuté sans concurrence, ainsi qu’une reprise du journal et de
la notification de construction réelle sur la version finale. Aucun test assoupli.
La capture mobile des colonies et les contrôles ciblés d’accessibilité sont relus ;
cela ne constitue pas un audit complet RGAA.
Consommateurs locaux rechargés, tous healthy. Lot validé localement ;
publication, PR et CI GitHub du lot C non effectuées, aucune intégration de C
dans dev ni déploiement Alpha. Voir le suivi pour les résultats détaillés.

## Lot C intégré et lot D validé localement — 12 novembre 2026

Le lot C est intégré via PR #43, commit 607971469628ca4155c821b18a370c4be53b43ff,
après CI complète 43134524413 verte. Aucun déploiement Alpha.
Le lot D part de cette dev actualisée sur feature/destinations-flottes-pnj.
Trois modes, suggestions privées de pseudonyme et contexte prérempli sont
implémentés. Décision PNJ confirmée : Attaque pour combattre, Conquête JcE
pour capturer ; aucun nouveau butin PNJ ni modification d’équilibrage.
Les 32 tests combat/occupation passent (314 assertions) et 14 tests d’accès
(154 assertions) après maintien du préremplissage de transport en lecture.
Les parcours ciblés des trois modes, suggestions clavier/toucher, envoi PNJ,
wizard et verrouillage de prévisualisation réussissent. Les six contrôles locaux
réussissent : 718 tests PHP (210198 assertions), 13 Python et 165 scénarios
navigateur distincts ; validation, lint/schéma, assets et diff vérifiés.
Relecture mobile et reprise clavier sur le template final réussies ; détails
et rapports dans le suivi. Publication à autoriser avant le lot E.
Aucune migration ou dépendance supplémentaire ; aucun push/PR/CI du lot D.

## Lot D intégré, lot E en validation — 12 novembre 2026

PR #44 fusionnée dans dev (63e380f), après CI complète 43169578878 verte.
Lot E sur feature/administration-vaisseaux-archives : attribution auditée au sol,
archives personnelles restaurables, tri mixte avant pagination. Tests en cours,
aucun commit/push/PR du lot E. Migration locale autorisée après sauvegarde vérifiée,
comptages inchangés ; aucun déploiement Alpha.

Validation locale finale du lot E : six contrôles réussis, 738 tests PHP
(210294 assertions), 13 tests Python et 167 scénarios navigateur distincts.
Clavier général et deux nouveaux parcours revérifiés après l’ajustement CSS final.
Sauvegarde locale et migration vérifiées ; documentation et wiki intégré synchronisés.
Lot prêt localement, publication à autoriser ; aucune CI GitHub du lot E ni Alpha.

## Lot E intégré, lot F en préparation — 12 novembre 2026

PR #45 fusionnée dans dev (bbbd72e), après CI complète 43177729961 verte.
Lot F sur feature/tags-guildes-signalements-joueurs : tags obligatoires à la
création, reprise sans tag fictif, préfixe au classement et signalements privés
de joueurs avec contexte serveur. 64 tests ciblés réussissent (207 assertions).
Migration Version20261112030000 préparée ; application locale en attente d’accord.
Suites complètes isolées en cours ; aucun commit/push/PR du lot F ni Alpha.

Validation du lot F sur bases isolées : 761 tests PHP (210374 assertions),
13 Python, complément de 23 tests ciblés (84 assertions) et 170 scénarios
navigateur distincts réussis. Les six contrôles passent ; make lint utilise
explicitement la configuration et la base des tests PHP, schéma compris.
Code prêt à publier sous réserve d’accord. La base locale de jeu n’est pas
migrée : sauvegarde vérifiée et autorisation de Version20261112030000 encore
requises, puis vérification du schéma local. Aucun push/PR/CI du lot F ni Alpha.

Migration locale F explicitement autorisée, sauvegarde vérifiée deux fois et
Version20261112030000 appliquée : onze comptages inchangés, ancienne guilde sans
tag inventé. make lint complet réussit aussi sur la base locale migrée.
Publication F autorisée ; fusion conditionnée à la CI complète, puis lot G.
Aucun serveur Alpha concerné.

## Lot F intégré et rappel du lot G — 12 novembre 2026

PR #46 fusionnée dans dev (1818e8e) après CI complète 43189015280 verte.
Lot G sur feature/rappel-flottes-et-repli, complexité 5 : navigation figée,
progression réelle après boosts, rappel transactionnel des missions éligibles,
repli explicite après perte de l’origine, fret protégé et anciens messages sans effet.
Anciennes flottes conservées : refus expliqué si les données ne permettent pas
de retour fiable. Les règles validées de combat et stockage restent applicables.

Migration locale Version20261112040000 autorisée puis appliquée après sauvegarde
vérifiée deux fois ; onze comptages et valeurs historiques des flottes inchangés.
71 tests ciblés passent (543 assertions). Contrôles complets en cours.
Publication du lot G non autorisée à ce stade ; aucun push, PR ou déploiement Alpha.

Validation locale finale de G : six contrôles réussis, 793 tests PHP
(210610 assertions), 13 Python et couverture exacte de 173 scénarios navigateur
distincts après reprise ciblée du test d’estimation. Le rappel est disponible
localement, avec contrôles clavier/tactile et confidentialité du repli.
Documentation et wiki intégré synchronisés. Publication en attente d’accord ;
aucune PR/CI GitHub G ni fusion ou déploiement Alpha. H à K restent à réaliser.

## Lot G intégré et lot H en validation locale — 12 novembre 2026

PR #47 fusionnée dans dev (30f548b), CI complète 43205470072 verte sur f627c9c,
cinq quality et GitGuardian vérifiés. Aucun déploiement Alpha.

Lot H sur feature/detection-attaques-visibilite : politique serveur centralisée,
préavis validé par somme simple, alertes actualisées sur les pages de jeu et carte
synchronisée, origine inconnue et sondes masquées. Espionnage et règles de mission
conservés. Univers local abc-1 : aucun remplacement des plafonds tactiques,
5/5/5 et préavis théorique maximal de 80 minutes avant limite du demi-trajet.
20 tests ciblés passent (177 assertions) ; suites complètes en cours.
Aucune migration requise, publication H non autorisée à ce stade. I à K restent à réaliser.

Validation finale de H : six contrôles locaux réussis, 809 tests PHP
(210723 assertions), 13 Python et couverture exacte de 175 scénarios navigateur.
Conflit du watch local identifié et références rétablies ; 30 reprises vertes
sur compilation stable, puis clavier général et variantes H. Le mode de
développement est rétabli après les contrôles, sans modifier les sources.
Aucune migration ni publication H ; accord utilisateur requis pour push/PR,
puis CI complète verte avant fusion et passage à I. Aucun déploiement Alpha.

Publication de H autorisée, PR #48 ouverte. La CI 43235864806 réussit côté
serveur et sur trois groupes navigateur ; un test de calendrier lit trop tôt
la base après soumission. Attente de la même valeur persistée ajoutée, reprise
locale réussie. Nouvelle CI complète requise ; H non fusionné, I non commencé.


### Lot I — demandes d’orbite (fusionné, PR #49)
Demandes au départ, accord allié automatique, réponse neutre avant arrivée,
refus/retrait par retour partiel, accord revérifié et retour sans réponse.
Transition des anciennes missions et défense des hôtes neutres validées.
Suivi privé dans le JSON des trajets existant ; aucune migration.
Vérifications locales réussies : 835 tests PHP, 13 Python et 176 scénarios
navigateur distincts, avec compléments ciblés après revue. PR #49 fusionnée
vers dev après CI complète 43260133554 verte sur 03aad13 ; sauvegarde/restauration
et cinq jobs quality examinés. Aucun déploiement Alpha lancé.


### Lot J — abandon (intégré dans dev, PR #50)
Bilan, confirmation par nom et contenu courant, dernière planète par univers,
blocages des opérations et dépendances entre colonies ; perte des actifs présents,
annulation sans remboursement, trace et conservation des historiques.
Kit non renouvelé pour le compte qui a abandonné cette même planète ; kit normal
conservé pour un autre propriétaire (décision explicite).
Compatibilité des orbites rétablie pour les anciennes planètes PNJ actuellement
contrôlées par un joueur, selon la définition déjà validée au lot D.
Vérifications locales : 868 tests PHP, 13 Python et 181 scénarios navigateur
distincts réussis après corrections/reprises. Publication autorisée par
l’utilisateur ; PR et CI complète requises avant fusion vers dev. Aucune migration.

### Lot K — interfaces validées (implémenté)

Intégration des maquettes approuvées de Diplomatie, Profil et Inventaire :
périmètres explicites, gestion de guilde conservée, changement de mot de passe
facultatif repliable et actions destructives distinctes ; inventaire par usage,
choix d’opération et examen annulable avant consommation. Les permissions,
formulaires sécurisés, clés idempotentes et règles de jeu restent identiques.

La recette manuelle A à K est dans [RECETTE_EVOLUTIONS_INTERFACES](RECETTE_EVOLUTIONS_INTERFACES.md).
La fusion vers dev nécessite la CI complète de la dernière version.
Aucun déploiement Alpha prévu ; les retouches seront décidées avec l’utilisateur.

Vérifications K : validation et lint réussis, 868 tests PHP, 13 Python, compilation et 31 parcours navigateur concernés réussis après reprises. Le manifeste complet comporte 183 scénarios ; la CI complète reste obligatoire avant fusion. Voir le suivi Interfaces et flottes pour les preuves et les limites.

### Retouches de recette — en cours de vérification locale

Bulles flottantes des colonies et flottes, rapports en lignes compactes,
en-têtes du classement cliquables et retrait de deux textes explicatifs.
La nouvelle organisation Diplomatie est validée et intégrée localement : cinq onglets
avec guilde (Ma guilde, Membres, Grades et permissions, Relations guildes, Relations commandants),
trois sans guilde (Créer une guilde, Rejoindre une guilde, Relations commandants). Les membres
affichent En ligne en vert ou Hors ligne en rouge selon l’activité récente dans cet univers.
Les fonctions et droits existants sont conservés ; publication et CI de cette retouche
restent distinctes de la livraison A à K déjà fusionnée.

Dernières retouches locales : bulles sans croix avec fermeture automatique ;
adhésion dans un onglet distinct ; trois actions de guilde regroupées ; aperçu
de trois messages vérifié ; départ/dissolution directement visibles selon le rôle.
Tests ciblés réussis ; CI complète sur cette dernière version avant fusion.


## Lot 2 — Outils stratégiques et recherche avancée

Le lot UI/UX est fusionné dans `dev` par la PR #54. Le lot 2 part de `f3b9623`
sur `feature/outils-strategiques-et-recherche`, dans la PR #55 distincte.
Intégré dans `dev` par la PR #55 après CI complète réussie. La PR #56 prépare l’Alpha ; aucun déploiement effectué.

| Sous-tâche | Complexité | État |
| --- | --- | --- |
| Simulateur réutilisant le moteur réel | 5/5 | Résultat illustratif reproductible, forces propres et rapport daté, hypothèses et calcul borné implémentés. |
| Compositions personnelles | 4/5 | CRUD, plafond verrouillé de 20, versions et chargement explicite avec manques implémentés. Migration locale autorisée après sauvegarde vérifiée. |
| Recherche avancée messages/rapports | 4/5 | Sélection SQL par droits/univers, filtres, pagination et conservation du contexte implémentés. |

Voir le [suivi détaillé](CADRAGE_OUTILS_STRATEGIQUES.md) et les décisions validées.
La CI complète sur la dernière version et l’accord du propriétaire restent
nécessaires avant fusion. Aucune migration Alpha effectuée.
