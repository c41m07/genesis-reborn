# Rework V1 — suivi et reprise

## Dernière relecture du prompt et des conventions — validée localement

Demande complémentaire après `a9f5ddc` : rapprocher chaque exigence du prompt
des preuves disponibles et écrire les commentaires comme des notes de maintenance
du développeur. Lot 12/12 conservé, complexité 3/5 ; branche et exclusions inchangées.
État initial propre. Les 31 fichiers PHP applicatifs du lot cumulé sont recensés
dans `var/rework-last-conventions-inventory.json`, sans parcourir les dépendances.

Écart confirmé : GameManager, ActionGuard et les anciennes parties de QueueService
restaient condensés. Mise en forme limitée à ces trois fichiers, avec le formateur
déjà utilisé et installé ; AST avant/après strictement identique. Chaque instruction
et condition est développée, sans déplacement des verrous, requêtes ou écritures.
Preuve : `var/rework-last-format-proof.log`. Le lint complet et les 18 protections
du bandeau réussissent après ce changement. Ce complément est séparé des noms et
commentaires dans l’historique Git.

La relecture des noms, commentaires et critères détaillés est terminée ; les
résultats précédents restent historiques et sont distingués des nouvelles vérifications.

Noms : participation, définition, technologie, bâtiment, défense, reçu et message
de relais remplacent les abréviations ambiguës de trois fichiers déjà concernés.
Chaque méthode est vérifiée sans collision de variable ; la structure PHP reste
identique après application de cette seule correspondance de noms. Aucun appel
avec les anciens noms de paramètres n’a été trouvé dans les sources, tests et
configuration. Clés persistées, propriétés, routes et messages inchangés.
Preuve : `var/rework-last-renames-proof.json`. La nouvelle suite PHP/Python complète
réussit dans un projet isolé ; aucun schéma de la base habituelle n’est modifié.

Commentaires : relecture et complément de 12 fichiers applicatifs. Les explications
portent sur le rôle des traitements, les préconditions de verrouillage, l’état
périmé, les dépendances de niveaux, l’échéance réelle, les jetons, les remboursements,
les cycles du guide et les effets de consultation. Le ton reste simple ; chaque
explication porte sur une contrainte vérifiée du traitement. Les instructions PHP restent
strictement identiques après retrait des seuls commentaires et espaces :
`var/rework-last-comments-proof.json`. Ces ajouts sont séparés des renommages.

### Correspondance entre le prompt et les preuves

| Exigence | État et preuve / exception |
| --- | --- |
| Branche unique et travail préservé | `chore/rework-v1`, base `48c9a71`, état initial propre, aucun commit de fusion ; commits mécaniques, noms et commentaires séparés |
| Douze lots et inventaire de tous les domaines | Douze lots conservés ; inventaire initial/final et classement des 44 contrôleurs, dépendances et contrats dans ce suivi et `var/rework-final-domain-inventory.json` |
| Sources et consignes | AGENTS racine, conventions locales et sources du chantier consultés ; les trois documents Work restent absents du dépôt et des pièces jointes accessibles, sans prétendre les avoir lus |
| Conventions et commentaires | Relecture des 31 fichiers PHP applicatifs concernés ; trois fichiers transactionnels développés, noms clarifiés et commentaires de maintenance vérifiés. Les alias SQL et variables de comparaison courtes restent légitimes ; aucune normalisation des dépendances ou archives |
| Responsabilités proportionnées | Préparations ColonyView/Catalogue*, lectures dédiées AdminReadRepository/ReportSummaryRepository, transport LiveRegionResponse ; contrôles d’action conservés dans les services, sans nouvel accès global au conteneur |
| Catalogues, diplomatie, rapports, planètes, flottes, administration | Préparations ciblées, lectures groupées, filtrage d’univers et pagination décrits et mesurés par domaine ; listes de configuration réellement complètes conservées avec justification |
| Barre de ressources exclue | 18 comparaisons de fichiers/corps protégés à la base ; aucun changement du rendu, endpoint, calculs, fraîcheur, polling ou interactions du bandeau |
| Règles, coûts, données, confidentialité et permissions | Pas de changement des catalogues, entités, schéma ou dépendances ; caractérisations, refus d’accès et suites métier conservés. Les lectures d’affichage ne valent pas autorisation |
| Concurrence et traitements différés | Cinq courses à deux processus avec attente constatée, clés de requête et jetons obsolètes ; fin de commande relue sous verrou. Aucun ordre de verrou changé ; toutes les courses possibles ne sont pas revendiquées |
| Cache et synchronisation | Préparation économique limitée à la requête et à une planète ; synchronisation avant lecture des niveaux ; aucun cache partagé ajouté. Les snapshots complets du moteur économique restent une exception justifiée |
| Mesures comparables | Premiers passages séparés, dix mesures chaudes, médianes/étendues, SQL/temps/mémoire distingués ; mesures multi-volumes et 132 actualisations DOM. Pas de gain global inventé ni d’assimilation service/HTTP/rendu |
| Actualisations, formulaires et erreurs | Fragments partagés, générations de requêtes et réponses obsolètes, focus/saisie/journal préservés ; tests de refus et de réponse tardive ; aucune suppression de journalisation ni réussite factice |
| Interface, accessibilité, SEO et identité | Parcours réel ordinateur/mobile/deux thèmes et suite navigateur ; logos/SVG et métadonnées publiques conservés. Aucune certification RGAA ou validation avec un lecteur d’écran réel revendiquée |
| Vérifications locales | Nouvelle suite : 1 047 cas PHP / 213 253 assertions, un benchmark facultatif ignoré ; 13 Python réussis. Dernière suite navigateur : 202/202 sans reprise et 24 répétitions réussies. Validation/compilation inchangées depuis cette passe, lint complet relancé et réussi. Aucune assertion supprimée ou délai d’action relevé |
| Documentation, suivi et commits | README, architecture, index, feuille de route, worker et suivi synchronisés dans le lot ; six documents contrôlés pour leurs liens. Les anciens résultats restent historiques, les preuves générées restent ignorées |
| Opérations sensibles et publication | Aucune installation, suppression de source, migration de base habituelle, réécriture Git, PR, fusion ou publication ; seuls des projets de tests synthétiques propres au chantier sont créés puis fermés |
| Limites externes et prochaine étape | Wiki distant, emails externes, technologies d’assistance, CI et protection distante des branches non vérifiés ici. La CI complète et le contrôle des protections restent nécessaires avant une future fusion autorisée |

Cette correspondance valide le périmètre réalisable et ses exceptions explicites.
Elle ne transforme pas l’inventaire en audit de chaque ligne du dépôt, ni les tests
automatiques en preuve d’absence de bugs ou de conformité générale.

### Résultats et reprise de cette relecture

- Mise en forme : commit `0553e47`, trois fichiers et structure PHP inchangée.
- Noms : commit `8f88dc6`, trois fichiers, aucune modification hors correspondance de variables contrôlée ; recherches d’appels nommés effectuées.
- Commentaires : 12 fichiers, comparaison de leurs instructions et analyse syntaxique réussies ; `var/rework-last-comments-proof.json`.
- Nouvelle suite complète : 1 047 cas PHP, 213 253 assertions, 13 tests Python ; seul le benchmark facultatif reste ignoré dans la suite standard, avec ses mesures séparées déjà conservées. Durée PHP 9 min 03 s, pic 123 Mio ; ce n’est pas une mesure comparative du jeu. Journal `var/rework-last-test.log`.
- Lint final : PHP, YAML, Twig, conteneur et schéma synthétique réussis ; `var/rework-last-lint.log`. Liens des six documents, diff et 18 protections du bandeau réussis.
- Navigateurs et mesures : résultats complets précédents conservés ; aucun template, asset, réglage, formule ou traitement applicatif n’a changé dans cette relecture. Les différences exécutables se limitent aux noms explicitement vérifiés, sans nouvelle mesure ou nouvelle exécution navigateur revendiquée.
- Nettoyage : nouveau projet PHP fermé, anciens projets conservés, huit services habituels sains ; `var/rework-last-cleanup.json`.
- État : lot 12/12 terminé sur le périmètre accessible, aucun contrôle en cours ni question en attente ; changements en commits locaux sur la même branche.
- Prochaine étape : validation complémentaire de l’utilisateur si souhaitée ; aucune PR ou publication automatique. Les limites externes restent celles de la matrice ci-dessus.

## Recette autonome supplémentaire — validée localement

Demande du 15 novembre après `3cdfefa` : effectuer la recette complète en
autonomie et corriger les défauts, dont la stabilité des tests navigateur.
Branche, absence de PR et exclusion du bandeau conservées. Cette phase complète
la clôture technique historique ci-dessous. Les parcours représentatifs et les
contrôles locaux sont terminés ; aucune garantie d’absence de bugs n’en est déduite.

Complexité 4/5. Ordre retenu : stabiliser la préparation des tests, effectuer les
parcours réels avec des comptes synthétiques, puis valider le résultat cumulé.
La base habituelle, les dépendances et les règles de jeu restent hors mutation.

Constats et correction de l’infrastructure de test :

- La préparation synchrone faisait reconstruire le schéma pendant que les trois
  consommateurs fonctionnaient, puis leur redémarrage entrait dans le budget du
  parcours. Les appels Docker de cette préparation sont désormais asynchrones
  et bornés ; arrêt des consommateurs avant reconstruction, vérification de leur
  disponibilité après import. Les gardes du projet jetable restent actives.
- Budget distinct de 60 s pour la préparation, avec les mêmes 60 s pour les
  actions du parcours et les mêmes assertions. Le budget total d’un cas peut
  donc inclure ces deux phases ; ce n’est pas une accélération du jeu ni une
  hausse silencieuse du délai d’une interaction. Mécanisme documenté par
  [Playwright](https://playwright.dev/docs/test-fixtures#fixture-timeout).
- Profil navigateur uniquement : RabbitMQ démarre avec deux schedulers serveur
  et un pour ses outils CLI, au lieu de prendre les processeurs de l’hôte pour
  chaque instance et chaque sonde. Configuration selon la
  [documentation RabbitMQ](https://www.rabbitmq.com/docs/runtime), sans modifier
  les réglages de production. Les états sains attendus ne sont pas affaiblis.
- Profilage de référence sur Flottes et clavier, sans autre suite : 39,90 s dont
  20,85 s de préparation ; 38,22 s dont 21,43 s. Après correction : 18,41 s
  d’actions + 21,02 s de préparation ; 16,55 s + 20,60 s. Deux succès avant et
  après, aucun gain global revendiqué. Logs `var/rework-recipe-{before,after}.log`.
- Vérifications ciblées : syntaxe Node, validation du profil Compose, diff et
  18 protections du bandeau réussis. La suite complète et la recette UI sont
  détaillées ci-dessous.

Contrôle des commentaires demandé pendant la recette : quatre commentaires
anglais hérités dans QueueService et ActionGuard ont été traduits. Des explications
ciblées ont été ajoutées dans 18 fichiers, dont GameManager, les préparations de
diplomatie/flottes/profils, les lectures de rapports, la pagination, l’export CSV,
les compatibilités d’espionnage, les fragments Twig et colony-live. Elles décrivent
les raisons des verrous, les droits, les lectures périmées et les risques de perte
de saisie ; elles ne répètent pas chaque affectation ou boucle.

Les commentaires déjà présents dans ColonyView (synchronisation avant lecture),
OutboxRelay (republication après un arrêt), DailyLeaderboard (snapshot quotidien
et anciens rangs) et admin-list (réponses obsolètes et focus) sont conservés.
La précédente déclaration de clôture ne devait pas être interprétée comme une
vérification exhaustive de tous les commentaires du code ancien.

Preuves du complément : instructions de 14 fichiers PHP identiques après retrait
des commentaires et espaces, AST JavaScript identique, trois templates strictement
identiques après retrait des commentaires Twig. Syntaxes PHP/JS/Twig et diff
vérifiés ; aucun code exécutable modifié. Résultats :
`var/rework-comments-{php,frontend}-proof.json`. La compilation des assets a été
refaite après la suite navigateur pour éviter de remplacer ses fichiers en cours.
Les empreintes des fichiers d’entrée compilés sont identiques à celles testées
par la suite complète ; le commentaire JavaScript ne change pas le bundle final.

### Validation finale de cette recette

| Contrôle | Résultat courant |
| --- | --- |
| Stabilité des anciens échecs | Les 12 scénarios concernés répétés deux fois sur quatre projets simultanés : 24/24 au premier essai, aucune reprise ni erreur globale |
| Suite navigateur complète avec persistance | Quatre groupes de 51, 59, 43 et 49 cas : 202/202 au premier essai ; aucun cas ignoré, échec, reprise ou résultat instable |
| Couverture | Rapprochement avec un manifeste neuf : aucun scénario absent, supplémentaire ou dupliqué ; chaque résultat contient un seul essai réussi |
| `make validate` et `make lint` | Relancés et réussis après les ajouts de commentaires ; PHP, YAML, Twig, conteneur et schéma synthétique |
| `make assets` | Relancé et réussi après les navigateurs ; entrée 495 Kio et mêmes empreintes compilées |
| Suite PHP/Python | Résultat complet conservé sur les mêmes instructions applicatives : 1 047 cas PHP / 213 250 assertions, un benchmark optionnel ignoré dans la suite et mesuré séparément ; 13 Python réussis. Aucun code PHP exécutable changé depuis cette passe |
| Bandeau et diff | 18 comparaisons protégées réussies contre `48c9a71` ; `git diff --check` réussi |
| Nettoyage isolé | Les 11 projets créés pour ce complément ont disparu ; les deux anciens projets de tests sont conservés et les huit services habituels restent sains |

Durées des groupes navigateur : 36,4 / 26,9 / 34,7 / 38,4 minutes. Ce sont des
durées de suites sous concurrence, pas des mesures de performance du jeu.
Assertions et délais des actions restent inchangés ; le budget distinct de
préparation est expliqué plus haut. Les anciens dépassements et reprises ne
sont pas effacés des bilans historiques.

Preuves locales ignorées par Git : `var/rework-recipe-coverage.json`, les quatre
`var/rework-recipe-full-*.json`, `var/rework-stability-*.json`, les journaux
`var/rework-recipe-{validate,lint,assets}.log` et `var/rework-recipe-cleanup.json`.
La CI GitHub n’a pas été lancée et aucune PR, fusion ou publication n’a eu lieu.

### Recette interactive isolée du 15 novembre

Environnement propre à cette recette, comptes et noms synthétiques, aucun email
externe. Les préconditions (confirmation, rôle de test, colonies, technologies et
vaisseaux) sont préparées exclusivement dans ce projet jetable. Les commandes,
contributions, transports et utilisations sont ensuite effectués dans le vrai
navigateur. Les échéances accélérées restent des fixtures ; les consommateurs
réels effectuent les résolutions. Les parcours automatisés complémentaires sont
distingués des manipulations interactives ci-dessous.

| Domaine | Parcours interactif constaté |
| --- | --- |
| Comptes | Inscription invalide puis corrigée, conservation des saisies, connexion refusée avant confirmation puis réussie après préparation du compte synthétique |
| Univers et colonies | Omega et Turbo, création du second empire, refus d’accès à une colonie d’un autre univers actif, retour à Omega, sélection entre deux colonies et comparaison des stocks/activités |
| Catalogues et files | Mine construite, recherche commune niveau 2 puis niveau 3 depuis deux colonies, annulation de la seule dépendance avec aperçu du remboursement, fin de recherche et chasseur construit ; Max, prérequis et lien guide → chantier bloqué par manque de métal |
| Actualisation | Composition de flotte et saisie conservées pendant une fin de construction ; progression de nanofabrication sans onglet ouvert constatée en SQL avant tout retour HTTP, puis niveau 1 et journal d’absence visibles |
| Flottes et carte | Transport entre colonies avec aperçu, changement de vitesse conservant le fret, départ, livraison de 10 métaux et retour via les consommateurs ; carte interactive et liste des systèmes, destinations connues, rendu mobile |
| Rapports et messages | Détail du transport livré, archivage puis restauration ; canal de guilde, envoi Ctrl + Entrée et rendu Markdown |
| Guildes et projets | Création de guilde, modèle de projet synthétique créé dans l’administration, lancement, don de 10 métaux, clôture et récompense reçue |
| Inventaire | Boost reçu du projet, aperçu puis confirmation sur une commande active, réduction annoncée de 25 % et inventaire passé de un à zéro |
| Administration | Refus initial du compte non administrateur, reconnexion après changement du seul rôle synthétique ; accueil, filtres joueurs sans résultat puis administrateur, fiche joueur, journal filtré, export CSV contenant une seule action correspondante |
| Présentation et clavier | Carte à 390 px, thème clair à 1440 px, animations réduites et préférences conservées entre univers ; retour au thème sombre ; saisies, erreurs, raccourci d’envoi et fermeture du détail économique avec Échap |
| Bandeau préservé | Détail Métal ouvert, capacité, production/consommation et solde présents, fermeture clavier ; aucune modification de son code ou de ses calculs |

Captures réelles conservées hors Git : `rework-recette-mobile.jpg`,
`rework-recette-clair.jpg` et `rework-recette-desktop.jpg`, dans le dossier de
visualisations de cette session. Les essais de rangs, défenses, orbites, rappel,
stationnement, pénuries/vacances, messagerie privée/Global, pagination volumineuse,
refus de droits et fonctionnement sans JavaScript sont apportés par les scénarios
automatisés dédiés, sans les présenter comme tous rejoués manuellement.
Les emails externes et technologies d’assistance réelles restent des limites.

### Reprise de la recette autonome — historique

- Branche : `chore/rework-v1`, base `48c9a71` ; correction des tests `0f3fad0`, commentaires `831bf61`.
- Lot : 12/12 terminé sur le périmètre accessible, sans correction applicative ouverte.
- Dernière étape validée : recette interactive, 202/202 au premier essai, 24 répétitions réussies, compilation et fermeture des environnements propres à la recette.
- Contrôles en cours : aucun ; modifications regroupées en commits locaux.
- Questions en attente : aucune. Sources Work absentes, wiki distant, emails externes, technologies d’assistance et CI restent les limites identifiées.
- Prochaine action : recette complémentaire de l’utilisateur sur cette branche si souhaitée ; toute publication ultérieure exige une décision explicite et la CI complète de la future PR.

## Clôture technique précédente — historique

Clôture technique locale validée le 15 novembre 2026 sur le code applicatif
`e5ca6cb`, branche `chore/rework-v1`. Douze lots terminés sur le périmètre
réalisable : conventions, inventaire final, préparations ciblées, fiabilité,
mesures et documentation. Version prête pour la recette complète utilisateur.

Validation : 1 047 cas PHP / 213 250 assertions, 13 tests Python, lint,
compilation et couverture navigateur 202/202 après douze reprises isolées.
Le benchmark facultatif est ignoré dans la suite standard et a été mesuré
séparément. Barre de ressources protégée ; aucun push, PR, fusion ou déploiement.
Les passages précédents restent consignés plus bas comme historique.

## Cadre

### Compléments de clôture validés

- Conventions : 24 fichiers PHP déjà concernés remis en forme avec comparaison
  de leur structure PHP avant/après ; noms locaux et paramètres clarifiés dans
  DiplomacyController, CatalogueInsights et CatalogueDetails. Routes, clés Twig,
  contrats persistés et noms de propriétés conservés. Configuration du formateur
  temporaire : PHP 8.2, quatre espaces, LF, tableaux courts, séparation des méthodes.
  PhpParser était déjà installé ; aucune dépendance ajoutée. Les formules et
  méthodes protégées de ResourceService ne sont pas reformatées.
- Validation des noms : 60 tests / 2 248 assertions réussis. Commits séparés :
  `91b2ff8` (présentation) et `c439d95` (noms). Isolation de CatalogueDetailsTest
  corrigée dans `94e4481`, sans changer ses assertions.
- Concurrence : cinq scénarios nouveaux, deux processus et connexions distincts,
  barrière et attente simultanée constatée sur le verrou de participation. Dépense
  insuffisante, double soumission, doublon de message, fin pendant une dépense et
  message obsolète. Premier passage : 5/5, 44 assertions ; répétition : 25/25,
  220 assertions. Les anciens tests concurrents GamePersistenceTest sont conservés.
- Défaut reproduit : deux fins simultanées pouvaient provoquer l’erreur MariaDB
  1020, car la lecture de commande précédait le premier verrou transactionnel.
  Correction limitée au contexte préalable et à la relecture sous verrou, sans
  modifier ordre des verrous, calculs, jetons ou synchronisation des lectures.
  Le comportement de snapshot est décrit dans le
  [guide MariaDB 11.8](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/upgrading/mariadb-community-server-upgrade-paths/upgrading-from-mariadb-11-4-to-mariadb-11-8.md).
  Tests uniquement sur la base synthétique ; aucune configuration SQL modifiée.

- Branche unique : `chore/rework-v1`, créée depuis `origin/dev` actualisée à `48c9a71`.
- Arbre initial propre ; huit worktrees existants recensés et conservés.
- Douze lots conservés. Aucun push, PR, fusion, déploiement ou changement d’équilibrage.
- Barre de ressources exclue : endpoint, calculs, templates et fréquence inchangés. L’extension de ResourceService pour les cartes est optionnelle et n’est pas utilisée par le bandeau.
- Aucune dépendance nouvelle, migration ou suppression prévue.
- Tests PHP sur `database-test/genesis_v2_test` exclusivement ; tests navigateur dans un projet jetable distinct.
- Ne pas exécuter deux suites PHP en parallèle : elles reconstruisent le même schéma synthétique.

## Sources

Consignes racine présentes et lues ; aucune consigne imbriquée trouvée dans les
répertoires applicatifs, scripts et documentation. README, index, architecture,
feuille de route, règles et décisions consultés pour cadrer les domaines ; leurs
longs historiques ne constituent pas une preuve de l’état courant.
Les consignes supplémentaires de `docs/conventions-code-architecture:AGENTS.md`
ont aussi été examinées : elles sont présentes sur cette branche locale, pas
dans `dev`. Leurs principes figurent dans le prompt du chantier ; aucun commit
ou fichier de cet autre chantier n’a été intégré silencieusement. Leur application
au code ancien reste ciblée, sans revendication de conformité exhaustive.
Les documents Work suivants n’ont pas été trouvés dans le dépôt ni dans les
pièces jointes accessibles ; ils n’ont pas été lus :

- `GENESIS_REBORN_PROMPT_MAITRE_WORK_V4_COMPLET.md` ;
- `GENESIS_REBORN_BRIEF_FIGMA_WORK_V2_COMPLET.md` ;
- `GENESIS_REBORN_PROTOCOLE_DEVELOPPEMENT_ADAPTATIF_WORK_V1.md`.

Le wiki embarqué est `docs/wiki.json` ; aucune source distante n’a été synchronisée.

## Inventaire initial

Inventaire structurel : 45 contrôleurs, 130 fichiers de services, 3 repositories,
1 manager, 7 handlers, 161 templates, 51 contrôleurs Stimulus, 209 fichiers de
tests et 15 scripts. Dépendances, fichiers générés, médias d’identité, autres
worktrees et archives historiques exclus des transformations mécaniques.

| Lot | Domaine et points d’entrée | Constat initial | État |
| --- | --- | --- | --- |
| 1 | Inventaire, mesures, isolation | Référence et garde de base nécessaires | Socle ciblé validé, inventaire détaillé à compléter |
| 2 | Conventions et lisibilité | ColonyView très condensé ; aucun formateur PHP installé | Dépendances clarifiées sur le périmètre prioritaire |
| 3 | ColonyController, ColonyView, CatalogueInsights | Préparation ciblée et quantités regroupées | Implémentation et tests ciblés validés ; recette finale à faire |
| 4 | DiplomacyController, GuildProjectController | Onglet validé avant listes ; dons personnels séparés du détail | Tests ciblés validés |
| 5 | Rapports, classements, profils, messages | Rapports de page et contacts regroupés ; comptage SQL de guilde | Tests ciblés validés, exceptions documentées |
| 6 | Carte, planètes, destinations, flottes | Mondes découverts et compositions privées regroupés | Tests ciblés validés |
| 7 | AdminController et historiques | Compteurs SQL, aperçus bornés, préparations par écran | Tests ciblés validés ; pagination encore ouverte |
| 8 | colony-live, fragments | Fragments partagés, réponses obsolètes ignorées | Tests ciblés validés |
| 9 | Assets, HTML, accessibilité | Fragments strictement identiques ; entrée compilée 494 Kio | Contrôles automatiques locaux validés ; recette manuelle ouverte |
| 10 | Comptes, boosts, événements, handlers, exploitation | Sept contrats revus ; lectures du simulateur regroupées | Revue ciblée et tests validés ; concurrence réelle non démontrée |
| 11 | Documentation et écarts transversaux | Index réparé, documentation et relecture des changements | Effectué sur le périmètre modifié |
| 12 | Contrôles et recette cumulée | Six contrôles, mesures comparables et parcours manuels requis | Contrôles locaux du périmètre livré validés ; recette et exceptions ouvertes |

Cet inventaire recense les domaines, sans prétendre à une revue intégrale de
chaque fichier. Les états du tableau sont ceux de la première passe ; les
compléments et la validation courante sont consignés plus bas.

## Inventaire final par domaine — clôture

Relevé actuel : 44 contrôleurs classés sans omission, 142 attributs Route
(y compris préfixes et alias), 133 services, quatre repositories, un manager,
sept handlers, 11 commandes, cinq composants de sécurité, quatre listeners,
169 templates et 52 contrôleurs Stimulus. Le manifeste structurel avec routes,
imports et marqueurs de permissions est conservé dans
`var/rework-final-domain-inventory.json`.
Les nombres initiaux ci-dessus restent historiques.

L’examen est ciblé par parcours : points d’entrée, dépendances, préparation,
contrats, droits, écritures et tests associés. Il ne vaut ni relecture de chaque
ligne des dépendances ni audit de sécurité exhaustif. « Conservé » signifie
qu’aucun changement justifié n’a été retenu après cet examen.

| Domaine / points d’entrée et dépendances | Classement final et décision | Contrats, données et vérification |
| --- | --- | --- |
| Comptes : SecurityController, ProfileController ; RegistrationService, EmailVerificationService, PasswordResetService, ProfileService, AccountDeletionService | Conservé. Formulaires et services distincts ; aucun changement de stockage ou de réponse sensible | CSRF, validation, hachage, jetons et réponses neutres ; AuthenticationTest, PasswordResetTest, SecurityHeadersTest, parcours de validation et confirmation |
| Participation/univers : UniverseController, UniverseContext, UniverseService, UniverseLifecycleService et import | Conservé. Contexte validé côté serveur ; sélection et fermeture restent identifiables comme écritures | Propriété, univers accessible, fermeture différée avec jeton ; GamePersistenceTest, universe-colonies-ui et refus interunivers |
| Préférences/vacances : PreferencesController, PreferencesService, VacationEconomyService/Calendar | Conservé. Paramètres sauvegardés et modes économiques inchangés ; aucun cache partagé ajouté | CSRF lié à la participation, formulaire obsolète refusé, suspension/reprise ; PreferencesTest, VacationPreferencesTest, VacationEconomyTest et VacationMissionsTest |
| Colonies : ColonyController, ColonyView, CatalogueInsights/Details, CatalogFilters, RequirementChecker/CatalogAccess | Amélioration réalisée. Un seul catalogue par page, aucun sur l’aperçu ; arbre seul au guide ; noms et étapes clarifiés | Synchronisation avant niveaux, coûts et durées réels, recherche commune, quantités par rang ; ColonyPreparationTest, CatalogAccessTest, GamePersistenceTest et catalogue navigateur |
| Économie des cartes : CatalogueProductionContext, ResourceService | Amélioration dédiée aux cartes. Événements/vacances lus une fois par préparation, contexte limité à la planète | Formules réutilisées ; comparaison en événements, vacances et fin de commande. Méthodes économiques protégées et bandeau conservés |
| Barre de ressources : endpoint resources, GameShell, FactoryEconomy et fichiers du bandeau | Hors périmètre explicite. Aucun changement de rendu, polling, endpoint, détails ou calculs | 18 comparaisons de corps/fichiers à la base, caractérisations économiques et tests navigateur ; aucune optimisation revendiquée du bandeau |
| Files : QueueService, ActionGuard, OperationCalculator, GameManager | Défaut de concurrence traité dans complete ; présentation et noms clarifiés lors de la dernière relecture, règles conservées | Verrous participation/univers/planète, propriété, jeton, échéance et reçu idempotent ; cinq courses prouvées, tests métier existants et comparaisons de structure PHP avant/après |
| Diplomatie/guildes : DiplomacyController, RelationResolver, DiplomacyService, GuildRankService, GuildPresence, PublicProfileController | Amélioration réalisée. Onglet validé avant préparation, relations et identités regroupées, présences limitées au bon écran | Priorité des traités de guilde conservée ; aucune décision d’action fondée sur le modèle d’affichage ; PersonalDiplomacyTest, GuildTagPlayerReportTest, public-profiles |
| Projets de guilde : GuildProjectController, GuildProjectService/RewardService/Rules et administration associée | Amélioration réalisée. Don personnel dans la liste, identités des contributeurs dans la fiche ; règles conservées | Droits, univers, paiements et récompense unique ; suites de projets et permissions ; aucune visibilité supplémentaire des contributions |
| Rapports/simulation : CombatReportController, EspionageReportController, TransportReportController, CombatSimulationController ; ReportArchiveService, ReportSummaryRepository, lecteurs et PlayerCombatSimulation | Amélioration réalisée. Métadonnées seules en liste, preuves détaillées conservées, compositions regroupées | Filtrage d’accès avant pagination, dates/archives/restauration et anciens rapports ; ReportArchiveTest, tests des lecteurs et simulations |
| Classements/profils/signalements : LeaderboardController, PublicProfileController, PlayerActivityController, PlayerReportController ; DailyLeaderboard, SocialProfileView | Amélioration réalisée. Lecture d’un score de profil dans le snapshot quotidien ; agrégats courants et recalcul à minuit conservés | Refus interunivers, ancien snapshot avec rangs manquants, identité actuelle, permissions de guilde ; DailyLeaderboardTest, PublicProfilesTest et parcours associés |
| Messages/notifications : SocialMessageController, MessageSearchController, NotificationController ; SocialMessageService, MessageSearch, JournalService | Conservé après regroupements de contacts déjà livrés. Pas de refonte de messagerie ou du transport Mercure | Canaux privé/guilde/Global, CSRF, échappement/Markdown assaini, dates et pagination ; suites messages, recherche, message-markdown et clavier |
| Planètes/carte/abandon : ColonyAbandonmentController, ColonyController ; DestinationCatalogue, EmpireDetails, CoordinateResolver, ColonyAbandonmentService | Amélioration réalisée. Destinations connues filtrées en base et compositions privées groupées | Découverte et univers avant sérialisation, propriété et concurrence d’abandon ; FleetDestinationTest, FleetStationingTest et cartes classiques/V2 |
| Flottes : FleetController, FleetCompositionController, FleetRecallController, FleetStationingController, FleetSurveillanceController ; services de flotte/orbite/transport | Amélioration réalisée. Lectures groupées et fragments disponibles/comptages partagés ; actions conservées | Propriétaire, participation/univers, versions et jetons, carburant, rappels/repli/orbite ; suites flottes, composition concurrente et destinations |
| Inventaire/boosts/défis/événements : BoostController, ChallengeController, GameEventsController ; services Boost/Event, WeeklyRotationService | Conservé. Calculs, fenêtres temporelles et réclamations existants ; aucune modification d’équilibrage | Consommation unique, boîtes sur colonie autorisée, événements ciblés ; BoostSystemTest, ResourceBoxTest, WeeklyRotationTest et resource-boxes |
| Console admin : AdminController et contrôleurs AdminCase/Player/Mission/Notification/EventWizard/GameEvent/GuildProject/Challenge/ResourceBox/WeeklyRotation/LegalPreview | Amélioration réalisée. Compteurs et aperçus SQL, préparation par écran ; listes paginées à 50 et export complet par lots de 100 | ROLE_ADMIN, CSRF, confirmations, actions auditées, filtre avant LIMIT et curseurs de consultation ; AdminPaginationTest, AdminReadRepositoryTest, refus et tests admin navigateur |
| Feedback/annonces : FeedbackController, PatchNoteController et leurs repositories | Amélioration réalisée pour les listes administratives. Filtres et pagination conservés ; écrans joueurs et contenu identiques | Pièces jointes et permissions conservées, historique non purgé ; tests feedback, patchnotes et pagination |
| Asynchrone/exploitation : sept handlers, OutboxRelay, scheduler d’occupation/reconstruction, services de combat/flotte/projets/univers ; commandes relay/tick/occupation/empire | Conservé sauf fin de commande corrigée. Commentaire de reprise clarifié ; aucun verrou supprimé ni format de message changé | Jetons périmés, échéances, rejeux, transactions et reprises ; suites GamePersistence, occupation, reconstruction, projets ; publication et files en production non provoquées |
| Fragments/Stimulus : colony_live, admin_list, LiveRegionResponse et Twig partagés | Amélioration réalisée. JSON compact et compression négociée ; erreurs et réponses obsolètes protégées | Déconnexion/nettoyage, focus/saisies, navigation native GET ; LiveRegionResponseTest, live-refresh-focus, filtres admin et mesures HTTP/DOM séparées |
| Présentation/assets/accessibilité : base, fragments catalogues/flottes/admin, contrôleurs existants | Amélioration ciblée. HTML commun aux réponses complètes/partielles ; médias, logos, SVG et CSS conservés | Compilation, captures à 1440/390, clavier, thèmes, focus et contrastes ciblés ; pas de déclaration de conformité RGAA complète |
| Public/SEO/aide : CrawlerController, LegalController, WikiController, PublicPageMetadata, templates publics | Conservé. Métadonnées, noindex connexion, sitemap et robots examinés ; aucune publication juridique | PublicSeoTest, public-seo, liens wiki ; domaine réel et Search Console demandent l’environnement et le compte propriétaire |
| Infrastructure/configuration/scripts/dépendances : Compose, Makefile, packages Symfony, CI, scripts installation/sauvegarde/restauration/générateur | Conservé. Pas d’installation, changement de schéma, dépendance, configuration globale ou procédure de déploiement | validate/lint/test/assets/browser, tests Python incluant scripts ; secrets exclus, sauvegarde/restauration isolées dans CI existante. CI distante et protection GitHub non revérifiées |
| Documentation et sources externes : README, index, architecture, règles/décisions, feuille de route, guides et suivi | Synchronisation réalisée pour le comportement modifié. Règles et audits historiques conservés | Liens locaux vérifiés ; wiki distant et trois documents Work indisponibles, explicitement signalés ; aucune suppression documentaire ni purge d’assets |

Les dépendances installées, fichiers compilés, archives historiques, autres
worktrees et médias d’identité restent exclus des transformations mécaniques.
Aucune suppression n’a été nécessaire. Les listes d’administration complètes
restent disponibles par pagination/export ; les historiques utiles ne sont pas
tronqués pour obtenir une meilleure mesure. Les autres contrôleurs conservent
leurs réponses et leurs écritures ; les conditions d’accès sont également
exercées par les suites cumulées.

## Référence de performance

`ColonyPreparationTest` construit un univers, un joueur, une colonie position 5,
un laboratoire niveau 2, une recherche niveau 1, une flotte au sol contenant
7 chasseurs et 3 défenses. Cinq ressources initialisées, aucune commande,
aucun événement ni combat. Horloge fixée au 14 novembre 2026 à 12 h UTC.
PHP 8.4.26, Symfony test/debug, MariaDB 11.8, conteneurs locaux existants.
Collecteur Doctrine existant, avec instrumentation de débogage conservée avant/après.
Mesure du service seul : aucun rendu Twig, HTTP, transfert ou navigateur inclus.
Premier appel séparé de dix appels chauds dans le même processus et la même
identity map ; caches applicatifs existants, sans remise à zéro entre appels.

Activation : `docker compose --env-file .env.local exec -T --user 1000:1000 -e REWORK_MEASURE=1 app php bin/phpunit tests/Integration/ColonyPreparationTest.php`.
Ne pas lancer cette mesure pendant `make test`.

| Page/service | Scénario | SQL avant | SQL après | Temps avant | Temps après | Mémoire | Résultat fonctionnel |
| --- | --- | --- | --- | --- | --- | --- | --- |
| ColonyView (toutes pages avant ciblage) | Premier appel | 471 | non mesuré | 218,27 ms serveur / 103,39 ms SQL | non mesuré | 52,5 Mio | 150 cartes, quatre catalogues |
| ColonyView (toutes pages avant ciblage) | Dix appels chauds | 468 ; médiane | non mesuré | 238,98 ms serveur [222,74–321,23] ; SQL 111,31 ms [104,43–135,42] | non mesuré | 56,5 Mio alloués, pic 56,5 Mio | Stocks, défenses et état économique caractérisés |

La référence historique d’environ 520 requêtes n’est pas reproduite à l’identique :
la fixture et le périmètre sont différents. Les 468 requêtes concernent le service
isolé, pas une page HTTP complète. Aucun pourcentage global n’est déduit.

## Comparaison stabilisée des préparations — première passe

Deux projets PHP jetables utilisant la même image et la même fixture ont été
mesurés successivement : avant, le fichier ColonyView de `48c9a71` est monté
en lecture seule dans le seul conteneur de référence ; après, le fichier courant.
Aucun fichier applicatif du checkout n’a été remplacé pour mesurer l’ancien code.
Mesure finale du 15 novembre, après les suites : caches Symfony préchauffés
avant chaque série. Pour chaque scénario : un premier appel du service, puis
dix passages chauds. Tableau des médianes et étendues serveur en millisecondes ;
allocations mémoire du processus
(cumulatives entre scénarios), pas mémoire HTTP par visite. Instrumentation de
débogage identique. Les autres services sont ceux du checkout courant ; avec
la fixture sans flotte active, les changements de compositions ne modifient pas
le travail comparé. Valeurs de transfert, rendu et temps HTTP : non mesurés.

| Page | Scénario | SQL avant | SQL après | Temps avant | Temps après | Mémoire avant → après | Résultat fonctionnel |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Vue générale | 10 passages chauds | 468 | 35 | 227,08 [212,59–261,40] | 13,77 [12,29–18,50] | 54,5 → 42,5 Mio | Aucune carte ; files et économie conservées |
| Bâtiments | 10 passages chauds | 468 | 275 | 226,75 [208,52–330,11] | 108,58 [104,41–137,61] | 54,5 → 46,5 Mio | 39 cartes, coûts et prérequis conservés |
| Recherche | 10 passages chauds | 468 | 35 | 248,17 [212,30–281,67] | 49,12 [43,89–83,89] | 56,5 → 46,5 Mio | 57 cartes et recherche commune conservées |
| Chantier | 10 passages chauds | 468 | 37 | 228,00 [213,86–240,42] | 17,33 [15,80–21,53] | 56,5 → 46,5 Mio | 42 cartes et stocks par rang conservés |
| Défenses | 10 passages chauds | 468 | 36 | 226,90 [218,61–279,89] | 14,05 [13,00–17,84] | 56,5 → 46,5 Mio | 12 cartes et stocks par rang conservés |
| Guide | 10 passages chauds | 468 | 25 | 226,52 [201,69–248,52] | 12,25 [11,40–17,19] | 57,5 → 48,5 Mio | Arbre préparé sans cartes ni flottes |

Temps SQL médians [minimum–maximum] avant/après :

- Vue générale : 104,92 [102,21–113,95] / 7,94 [7,22–10,20] ms.
- Bâtiments : 105,27 [99,91–163,03] / 57,72 [56,13–70,68] ms.
- Recherche : 110,87 [99,85–119,00] / 8,77 [7,92–12,18] ms.
- Chantier : 103,59 [97,05–111,90] / 8,37 [7,92–10,49] ms.
- Défenses : 108,16 [99,02–112,97] / 7,74 [7,26–9,37] ms.
- Guide : 105,15 [99,75–113,86] / 5,64 [5,17–6,71] ms.

Les temps ne constituent pas des seuils CI.
Les échantillons bruts et le lanceur temporaire restent dans `var/rework-measures.json`
et `var/measure-rework.py`, ignorés par Git. Les budgets testés restent larges.

## Vérifications et incidents (journal)

- `make validate` réussi sur l’état initial.
- Caractérisation et mesure initiales : 2 tests / 5 assertions réussis.
- Première suite PHP de référence invalidée : lancement accidentel de la mesure
  pendant la suite, puis arrêt du seul processus PHPUnit de ce chantier après
  vérification de son identité. Aucun service partagé arrêté ; base habituelle intacte.
- Une nouvelle suite de référence a été lancée seule ; son erreur de fixture est détaillée ci-dessous.
- Contrôle en lecture seule de la base locale habituelle : schéma cohérent,
  sans migration ni modification des données.
- Première suite finale : 1 026 cas / 212 589 assertions, une erreur de montage
  du conteneur jetable (fichier `/docker/compose.prod.yaml` absent), benchmark
  optionnel ignoré. Montage Docker en lecture seule ajouté au seul environnement
  temporaire et cache préparé après recréation. Suite complète relancée sans
  modifier les sources ou les tests pour contourner ce défaut d’environnement.
- Navigateurs : deux dépassements de délai global observés sous charge (clavier
  sur toutes les routes et actualisation des flottes), sans échec d’assertion
  identifié lors de ces délais. Les deux reprises isolées réussissent ensuite
  (flottes 40,6 s ; clavier 38,0 s), sans changement de test ni de délai.
- La régression de réponse tardive et d’erreur réseau obsolète passe dans la
  suite complète ; correction ciblée enregistrée dans `6dd33b7`.
- Reprise PHP/Python complète du 15 novembre réussie : 1 026 cas PHP,
  212 591 assertions, un benchmark facultatif ignoré dans la suite (mesuré
  séparément), 13 tests Python réussis. Aucun échec PHP restant. Durée de cette
  passe sous charge : 17 min 30 s ; il ne s’agit pas d’un budget de performance.
- Lot 3 : 8 cas de caractérisation et mesure / 28 assertions réussis, puis
  16 tests d’accès, coûts, files communes et barre de ressources / 101 assertions.
  Après ajout des budgets larges de requêtes : 7 cas / 33 assertions réussis ;
  seule la mesure facultative est ignorée sans `REWORK_MEASURE=1`.
- Un projet Compose PHP jetable distinct, `genesis-rework-php-20261114`, isole
  base et cache des nouveaux tests. Sa configuration temporaire est dans `var/`
  ignoré par Git. Aucun service habituel arrêté ou reconfiguré.
- Le premier passage complet n’est pas une référence immuable : des fichiers
  ont évolué pendant son exécution. Ces résultats n’ont pas servi de validation
  finale ; la passe finale a porté sur un contenu stabilisé.
- Ce passage complet se termine avec 1 erreur sur 1 015 tests : la fixture de
  `FleetStationingTest::testNoHomeUsesShortestReachableOwnedColony` déplace une
  planète sur une position occupée (`planet_unique`). Fixture corrigée dans
  `5d0417a`, sans changer les règles de stationnement. La suite Python n’avait
  pas été atteinte lors de cette passe.
- Bâtiments garde des lectures dans les calculs économiques partagés (275 SQL
  sur la première mesure ciblée). Leur suppression nécessite une préparation
  économique dédiée et vérifiée ; aucun cache ajouté dans les services de la
  barre pour masquer ces lectures. Les combats restent à regrouper ; les
  compositions des flottes ont été regroupées au lot 6.

## Validation de la première passe — 15 novembre 2026

| Contrôle | Résultat |
| --- | --- |
| `make validate` | Réussi, sans affichage des secrets |
| `make lint` | PHP, 78 YAML, 164 Twig, conteneur et schéma synthétique réussis |
| Schéma local habituel | Cohérent, contrôle en lecture seule, aucune migration |
| `make test` | Réussi sur le projet PHP isolé : 1 026 cas, 212 591 assertions, benchmark facultatif ignoré ; 13 Python réussis |
| Mesure facultative | Exécutée séparément avant/après sur six scénarios, avec caches préchauffés et dix passages chauds chacun |
| `make assets` | Réussi ; compilation finale également réussie sous Node 24.17.0 |
| `BROWSER_PERSISTENCE=1 make test-browser` | Couverture complète : 201 scénarios distincts réussis après deux reprises isolées, sans changer les tests ni leurs délais |
| `git diff --check` | Réussi sur le lot cumulé |

Les groupes comptent 55, 54, 43 et 49 cas. Première passe : 199 réussites et
deux délais dépassés ; reprise ciblée : 2/2. Comparaison des résultats au
manifeste : 201/201, aucun scénario manquant ni supplémentaire.
Les tests couvrent aussi les deux abandons simultanés ; cela ne remplace pas
une étude de concurrence de tous les traitements sensibles.

Les fichiers du bandeau, de ResourceService, de FactoryEconomy et de GameShell
sont identiques à `48c9a71`. Le corps de l’endpoint ressources est également
identique. Aucun coût, durée, équilibrage, verrou ou contrat persistant modifié.
Les environnements PHP et navigateur temporaires ont été fermés ; les services
habituels sont préservés. Les preuves et configurations temporaires restent
dans `var/` et `/tmp/genesis-rework-browser-*`, sans données réelles ni secrets.
La CI GitHub n’a pas été exécutée ; elle restera obligatoire sur une future PR.

## Complément du lot 3 — économie des bâtiments, 15 novembre 2026

Les cartes partagent désormais un contexte immuable de vacances et de bonus
d’événements, créé après la synchronisation des commandes et relu à chaque
préparation. Aucun cache de service ni copie des formules économiques.
ResourceService accepte ce contexte uniquement comme argument optionnel de
`rate` et `factoryConsumption`. Ses appels existants, son snapshot, les
capacités, la simulation et les écritures conservent leurs traitements.
Le contexte refuse une utilisation avec une autre colonie.

Comparaison isolée avec `9332789`, même fixture et cache Symfony préchauffé,
premier passage séparé puis dix passages chauds :

| Mesure Bâtiments | Avant | Après |
|---|---|---|
| Requêtes SQL | 275 | 37 |
| Temps serveur médian [min–max] | 97,59 ms [95,23–120,49] | 39,26 ms [33,97–53,58] |
| Temps SQL médian [min–max] | 53,04 ms [52,35–67,24] | 8,64 ms [7,66–10,07] |
| Mémoire allouée médiane du processus | 46,5 Mio | 42,5 Mio |

Les 39 cartes et leurs détails sont conservés. Il s’agit de mesures du service,
pas d’un temps HTTP ou navigateur. Données brutes :
`var/rework-economy-measures.json` (ignoré). Les autres sections gardent leurs
nombres de requêtes. Le test de caractérisation compare toutes les cartes aux
appels sans contexte, après changement d’événement, vacances et reprise ; il
vérifie aussi la fin d’une commande et l’égalité du snapshot économique.
Contrôles : 62 tests, 2 475 assertions, un benchmark optionnel ignoré puis
exécuté séparément ; `make lint` réussi. La validation cumulative finale reste
à relancer après les autres compléments.

Les combats des flottes actives sont aussi lus en une requête, filtrée par
propriétaire et univers. Le premier participant historique reste la référence,
y compris si une flotte possède plusieurs participations : aucune sélection
silencieuse d’un autre combat. Test sur douze flottes : une lecture groupée,
cas vide et historique résolu conservés ; 12 cas / 370 assertions, dont le
benchmark optionnel ignoré. Les projections des compositions restent inchangées.

## Complément du lot 4 — relations et contributeurs

Les contrats personnels, relations des guildes et identités des commandants
sont lus par groupes pour l’affichage de Diplomatie. Les autres onglets ne
préparent pas la liste des commandants. Les priorités guildes/contrats, les
propositions entrantes communes et l’ordre des résultats de recherche sont
conservés. Aucun cache partagé ni modification des validations d’actions.
Les contributeurs de la fiche de projet sont hydratés ensemble, avec les
anciens membres et le filtre d’univers ; la liste des projets garde le seul
don personnel. Vérification : 69 tests / 418 assertions réussis, dont
25 combinaisons comparées à RelationResolver. Deux erreurs de préparation
(fixture sans proposant et alias DQL réservé) corrigées avant la reprise.

## Complément du lot 5 — résumés de combats et profil quotidien

La liste des rapports de combat lit des résumés scalaires : résultat, date,
planète et nombre de participants. Ni les rounds ni le snapshot complet ne
sont hydratés. Les entités détaillées restent complètes pour la lecture et
l’archivage. Les droits sont vérifiés avant pagination et à nouveau dans la
lecture des résumés ; les refus interunivers et tiers sont testés.

Le profil lit sa ligne dans le snapshot quotidien existant. Le rattrapage des
files, le verrou et le recalcul de minuit sont conservés ; les nouveaux joueurs
attendent toujours le prochain calcul. Les vieux snapshots sans les rangs
bâtiments/recherche utilisent le chemin de compatibilité existant. À chaud,
le test exige moins de quatre requêtes pour la lecture du score individuel.
Contrôles : 19 tests / 133 assertions réussis, preuve détaillée conservée,
dates et contexte de retour, nouveaux joueurs et minuit couverts.
Les listes d’espionnage et de transport utilisent également des résumés ciblés :
date, cible/résultat ou identités enregistrées à la mission. Les snapshots
d’intelligence, fret et preuves restent dans les rapports détaillés.
Vérification complémentaire : 25 tests Rapports/Outils stratégiques,
146 assertions, dont recherche sur 5 000 rapports synthétiques ; aucune entité
de rapport n’est hydratée pour les lignes de liste.

## Complément du lot 7 — pagination et historique administratif

Joueurs, journal, feedbacks, événements et patchnotes administratifs sont
paginés par 50. Les recherches et filtres s’appliquent avant pagination.
Les compteurs des feedbacks et leur curseur de consultation conservent la
sélection complète ; le filtre d’archives des événements conserve la sémantique
des anciens JSON. Aucun historique ni preuve supprimé.

Joueurs/Journal conservent la recherche immédiate avec fragments partagés,
focus et sélection ; les réponses obsolètes sont écartées. Le formulaire GET
fonctionne aussi sans JavaScript. L’export des lignes visibles reste disponible ;
un export de tous les résultats filtrés lit 100 lignes à la fois, avec plafond
d’identifiant et protection contre les formules CSV.

Contrôles : 4 tests PHP / 65 assertions, fixtures de 175 comptes, feedbacks,
événements, notes et actions ; recherches hors page, filtres combinés, archives,
comptages, curseur de consultation, export complet et refus d’accès couverts.
Les cinq scénarios navigateur du fichier comptes/flottes réussissent par
consolidation : quatre scénarios, dont le nouveau parcours de pagination, puis reprise
du scénario rubriques/journal après correction d’une sélection avant connexion
Stimulus et du texte « aucun résultat ». Aucun test ni délai affaibli.
`make assets` réussi (495 Kio bruts, contre 494 Kio avant ce complément).

Les modèles de projets/rotations et les catalogues de paramètres restent des
listes de configuration complètes : leurs écrans affichent et éditent réellement
l’ensemble, contrairement aux historiques de joueurs. Pas de pagination
automatique qui ferait perdre les liens d’édition et la vue d’ensemble. La
modération garde ses consultations journalisées existantes ; son éventuelle
pagination doit aussi définir la portée des consultations avant changement.

## Complément du lot 6 — compositions, combats et activités

Flottes réutilise la liste à quai pour les réserves et lit ses compositions
ensemble ; les quantités nulles restent exclues du formulaire. Les combats
utilisent la même lecture groupée que la colonie. Le périmètre de la colonie
active, les préremplissages et les demandes d’orbite sont conservés.
La liste des colonies compte les commandes par groupe SQL au lieu d’hydrater
chaque file. Filtrage propriétaire/univers conservé même avec des identifiants
forgés. Contrôles : 19 tests / 525 assertions, un benchmark optionnel ignoré.
Les snapshots par planète restent des appels complets au moteur existant :
leur regroupement nécessiterait une extraction plus large du moteur partagé
avec la barre exclue, sans bénéfice mesuré ici qui justifierait ce risque.

## Complément du lot 8 — fragments des flottes

Flottes répond aussi en JSON à X-Genesis-Live + Accept JSON : sélecteur de
colonie, ressources, compteurs, flottes disponibles, vols et demandes d’orbite.
Le HTML complet reste le repli. Les formulaires et compteurs proviennent de
fragments extraits sans changement de leur contenu. GameShell et le template
de ressources conservent leur préparation et leur cycle.

Contrôles : 7 tests du contrôleur Destinations réussis, puis comparaison des
six régions pour Exploration/Espionnage, incluant le bandeau économique
identique avec horloge figée. Les jetons de formulaire et de rejeu restent
présents ; leur valeur renouvelée est exclue de la comparaison textuelle.
Les cinq scénarios navigateur Destinations/Focus réussissent ensemble :
colonie active, compteurs, destination, requêtes rapprochées et réponse tardive.
Le JS de remplacement des régions n’a pas nécessité de modification.

## Mesures HTTP des fragments — 0, 5 et 25 flottes

Environnement navigateur éphémère, APP_DEBUG=0, un compte synthétique, univers
standard, colonie neuve ; volumes ajoutés sans action de jeu sur la base usuelle.
HTML complet et JSON des fragments comparés sur la même version, en alternance.
Premier passage conservé séparément, puis dix passages chauds par combinaison.
Les durées couvrent fetch et lecture du corps ; les octets transférés proviennent
de Resource Timing (entêtes inclus). SQL et mémoire par requête HTTP : non mesurés.

| Page | Flottes | HTTP HTML, médiane [min–max] ms | HTTP fragments, médiane [min–max] ms | Transfert HTML → fragments |
|---|---:|---|---|---|
| Bâtiments | 0 | 154,20 [140,70–187,00] | 144,20 [137,70–166,00] | 24,19 → 20,78 Kio |
| Bâtiments | 5 | 149,90 [139,00–308,00] | 154,65 [141,50–172,70] | 24,86 → 21,51 Kio |
| Bâtiments | 25 | 157,30 [141,00–195,00] | 158,25 [135,40–203,80] | 26,12 → 22,75 Kio |
| Flottes | 0 | 100,15 [95,10–159,10] | 104,35 [94,80–139,20] | 7,72 → 4,10 Kio |
| Flottes | 5 | 103,50 [96,50–212,70] | 104,55 [92,60–196,40] | 8,42 → 4,83 Kio |
| Flottes | 25 | 124,05 [102,10–183,00] | 114,50 [100,60–144,40] | 9,60 → 5,96 Kio |

Le temps HTTP ne démontre pas de gain général : plages variables et médianes
proches. Le transfert diminue dans les six scénarios. La première mesure a
révélé que le JSON non compressé transférait davantage que le HTML compressé.
Gzip seul restait insuffisant pour Bâtiments, à cause de l’encodage HTML dans
JSON ; le transport compact conserve le contenu après décodage. Les formats
acceptés et les refus `gzip;q=0` sont testés. Aucun changement global Apache,
aucune modification de l’endpoint ou de la fréquence de la barre.

Les 132 réponses de la mesure finale sont dans `var/rework-http-measures.json`.
Les essais précédents sont conservés dans les fichiers `*-uncompressed.json`
et `*-gzip-only.json`. La phase de captures de ce lanceur a attendu des images
masquées après la mesure ; son attente est corrigée séparément, sans répéter
les mesures. Le lanceur et les artefacts restent ignorés par Git.
La reprise limitée aux captures réussit ensuite (44,3 s), sans refaire les
132 mesures. Vérification visuelle réelle à 1440/390 px : illustrations
chargées, interface conservée et aucun débordement horizontal. Captures dans
`var/rework-{buildings,fleets}-{1440,390}.png` ; les images masquées restent
chargées à la demande, conformément au comportement existant.

## Nouvelle mesure cumulative du service

Même fixture synthétique, base `48c9a71` et version finale, cache Symfony chaud,
dix passages chauds, premier passage séparé. Le lanceur existant est réutilisé.

| Page | SQL avant → après | Serveur avant, ms médiane [min–max] | Serveur après, ms médiane [min–max] | Mémoire allouée médiane avant → après |
|---|---|---|---|---|
| Vue générale | 468 → 35 | 259,80 [209,85–371,76] | 13,06 [12,45–16,28] | 54,5 → 42,5 Mio |
| Bâtiments | 468 → 37 | 223,22 [202,50–289,58] | 37,38 [35,26–44,31] | 54,5 → 42,5 Mio |
| Recherche | 468 → 35 | 214,12 [197,14–299,92] | 43,75 [41,48–48,62] | 56,5 → 42,5 Mio |
| Chantier | 468 → 37 | 213,66 [194,70–237,73] | 18,04 [15,25–22,23] | 56,5 → 44,5 Mio |
| Défenses | 468 → 36 | 212,20 [194,16–237,40] | 14,65 [13,05–18,29] | 56,5 → 44,5 Mio |
| Guide | 468 → 25 | 214,03 [202,34–249,01] | 10,77 [9,96–11,52] | 57,5 → 45,5 Mio |

Mémoire du processus cumulée entre scénarios, pas allocation d’une requête HTTP.
SQL et temps SQL détaillés dans `var/rework-measures.json` ; journal
`var/rework-final-service-measures.log`. Les budgets de tests restent larges.

## Complément du lot 2 — lisibilité de ColonyView

Renommages locaux `membership`, `technologies`, `definition`, `queueItem`,
initialisation et cartes mises en lignes. Les clés d’affichage, signatures
persistées, routes, calculs et ordre de synchronisation sont conservés.
Absence d’appel avec l’ancien argument nommé vérifiée dans sources/tests/config.
Pas d’installation de formateur ni de formatage global. Contrôle : 21 cas /
409 assertions, dont un benchmark optionnel ignoré, avec les tests du transport.
Les blocs historiques de tri hors modification restent une exception à la
normalisation exhaustive ; les réécrire sans gain mesuré ajouterait du risque.

## Complément du lot 11 — nettoyage ciblé

Retrait de l’injection RequirementChecker sans usage dans ColonyView et d’une
méthode privée de fusion sans appel dans CatalogueInsights. Les usages directs,
configurations et appels nommés ont été recherchés avant retrait. Aucune
suppression de fichier, de document ou d’asset ; aucun formateur installé.
Le conteneur et le schéma passent `make lint`, puis les six scénarios du
benchmark sont réexécutés sur cette version.

## Validation cumulative des compléments — 15 novembre 2026

Code applicatif figé à `9c8abb7`. Les changements retenus dans les douze lots
sont livrés localement, avec les exceptions justifiées ci-dessous ; cette
validation prépare la recette utilisateur, sans revendiquer l’absence de bugs.

| Contrôle | Résultat |
|---|---|
| `make validate` | Réussi, configuration Compose validée sans affichage des secrets |
| `make lint` | Réussi : PHP, 78 YAML, 169 Twig, conteneur et schéma cohérent dans l’environnement synthétique |
| `make test` | 1 042 tests PHP, 213 209 assertions, un benchmark optionnel ignoré ; 13 tests Python réussis |
| Benchmark explicitement activé | Réussi séparément, six scénarios avant/après ; premier passage et dix passages chauds conservés |
| `make assets` | Réussi, entrée de production 495 Kio ; aucune modification des CSS, images ou polices source |
| `BROWSER_PERSISTENCE=1 make test-browser` | 202 scénarios distincts réussis après une reprise isolée, aucun scénario manquant ou supplémentaire |
| `git diff --check` | Réussi avant l’enregistrement du bilan |

Les quatre groupes navigateur utilisent chacun leur projet, base, cache et
répertoire de résultats : 51/51, 59/59, 42/43, 49/49. Le seul échec initial
est `keyboard-navigation.spec.mjs:21`, limité à 60 s pendant une navigation,
sans échec d’assertion de focus. Sa reprise sur un environnement seul réussit
en 39,5 s, avec le même test et le même délai. Aucune source applicative,
assertion ni limite modifiée entre la suite et cette reprise.

La couverture des cinq rapports JSON est comparée au manifeste de 202 scénarios :
`missing=[]`, `extra=[]`, `unresolved_failures=[]`. Les fichiers sont dans
`var/rework-final-browser-{1,2,3,4,retry}.json` et
`var/rework-final-browser-coverage.json`, ignorés par Git. Les contrôles incluent
les audits axe, le contraste échantillonné, le clavier, les formulaires natifs,
les refus d’accès et les parcours métiers, dont le bandeau de ressources.

Sur l’instance locale habituelle : services sains, `lint:container` réussi et
empreintes des fichiers ResourceService/LiveRegionResponse identiques au dépôt.
Aucune migration, restauration ou fixture exécutée sur sa base. Nos projets
jetables ont été retirés ; les deux anciens projets de tests déjà présents
sont conservés. Les preuves et configurations temporaires restent dans `var/`.
Aucun push, PR, CI GitHub, fusion ou déploiement Alpha. La future intégration
exigera sa CI complète après la recette et l’autorisation de publication.

## Clôture renforcée — conventions et isolation des fiches

Complexité 3/5. Inspection des fichiers PHP déjà modifiés, sans extension au
moteur ou aux traitements exclus de la barre. Utilisation locale du parseur PHP
déjà installé et verrouillé, configuré LF/quatre espaces/tableaux courts,
essayé d’abord sur CatalogueInsights, CatalogueDetails et DiplomacyController.
La mise en forme de 24 fichiers conserve leur AST ; le corps de l’endpoint
ressources est replacé à l’identique et les 18 frontières protégées restent
identiques. Aucune installation, aucun changement de verrou ou de contrat.

Le lancement isolé de CatalogueDetailsTest sur une base jetable vierge a
révélé une dépendance à la création préalable des tables par d’autres tests.
Sa préparation contrôle maintenant l’hôte et le nom de base, puis reconstruit
son propre schéma synthétique. Aucun test affaibli : 60 cas / 2 248 assertions
réussis après correction. Le lint complet réussit sur l’état remis en forme.

## Validation finale de la clôture

| Contrôle sur le lot cumulé | Résultat |
| --- | --- |
| `make validate` | Réussi |
| `make lint` | PHP, 78 YAML, 169 Twig, injection et schéma synthétique réussis |
| `make test` | 1 047 cas PHP, 213 250 assertions, un benchmark facultatif ignoré ; 13 tests Python réussis ; 9 min 13,824 s, pic 127 Mio |
| `make assets` | Réussi, entrée 495 Kio ; CSS et JavaScript identiques au précédent passage validé |
| `BROWSER_PERSISTENCE=1 make test-browser` | 202 scénarios distincts réussis cumulativement ; première passe 190 réussites, puis 12/12 en reprise isolée |
| `git diff --check` et liens documentaires | Réussis ; aucun lien local manquant dans les six documents du lot |
| Protections du bandeau | 18/18 comparaisons de sources identiques à la base |
| Environnement habituel | Services injectés correctement ; aucune action de jeu ni migration sur sa base |

Les groupes navigateur ont couvert respectivement 51, 59, 43 et 49 cas.
Résultats initiaux : 51/51, 53/59, 39/43 et 47/49. Les 12 incidents signalent
un dépassement du délai global de 60 secondes ; dix portent le statut
`timedOut`, deux le statut `failed` pendant un `expect.poll` après ce délai.
Aucune erreur globale de rapport. Reprise des seuls douze sélecteurs dans un
projet neuf, sans autre suite concurrente : 12/12 en 6,5 minutes, sans changer
code, assertions ou délais. Cette reprise conserve le réglage RabbitMQ standard
du projet neuf ; le réglage temporaire de la première passe est détaillé ci-dessous.

La réconciliation au manifeste donne 202 attendus / 202 réussis, aucun manquant,
supplémentaire ou échec non résolu. Rapports et couverture :
`var/rework-closure-browser-{1,2,3,4,retry}.json` et
`var/rework-closure-browser-coverage.json`. Les douze incidents initiaux sont
conservés dans ces preuves ; ils ne sont pas présentés comme une passe initiale
entièrement verte. Il s’agit de contrôles locaux, pas de CI GitHub.

Les projets PHP, les quatre projets navigateur et celui de reprise créés pour
cette clôture ont été fermés par leurs lanceurs. Les deux anciens projets de
tests arrêtés et les autres projets préexistants ont été conservés. Aucun
environnement temporaire de cette clôture ne subsiste dans l’inventaire Compose.
Les scripts, mesures et captures synthétiques restent dans `var/`, ignoré par Git.

## Reprise de la clôture précédente — historique

Contrôles de clôture déjà terminés sur le code applicatif `e5ca6cb` :
`make validate`, lint complet, compilation, 1 047 cas PHP / 213 250 assertions
(un benchmark facultatif ignoré dans la suite standard), 13 tests Python,
mesures serveur et 132 actualisations DOM. Le benchmark facultatif a aussi été
exécuté séparément dans les passages consignés plus haut. Le contrôle des
services de l’instance locale habituelle réussit, sans action de jeu sur sa base.

La passe navigateur a utilisé quatre projets jetables. Une charge moyenne proche
de 19 a été observée pour 12 processeurs logiques, sans swap utilisé. Pour les
seuls quatre courtiers RabbitMQ de cette passe, le nombre de schedulers Erlang
actifs a été réduit dynamiquement de 12 à 2 ; contrôle préalable des étiquettes
projet/service, sans redémarrage ni modification d’un fichier de configuration.
Ce réglage était local aux conteneurs jetables et a disparu avec eux. Les
réglages de l’instance habituelle et les délais/assertions de tests ne changent
pas. Preuve : `var/rework-closure-browser-resources.json`. Les mesures de
performance précédentes ont été faites avant cette passe, sans sa concurrence.

- Branche : `chore/rework-v1`, base `48c9a71`, première passe complète validée `6dd33b7` ; compléments depuis `9332789`.
- Lot actuel : 12/12 terminé ; code applicatif validé `e5ca6cb`.
- Dernière étape validée : six contrôles locaux, 202/202 scénarios navigateur réconciliés, mesures et captures, nettoyage des seuls environnements du chantier.
- Contrôles en cours : aucun ; aucune source applicative après les validations.
- Modifications en cours : aucune à la clôture ; changements regroupés en commits locaux, aucune correction applicative ouverte.
- Questions en attente : aucune ; recette complète utilisateur et accès externes distincts.
- Prochaine action précise : recette complète utilisateur sur cette branche, selon la liste en fin de document. Publication vers dev seulement après validation explicite, avec CI complète sur une future PR.
- Conserver cette branche unique ; aucune PR, fusion ou publication automatique.

## Travaux et contrôles ciblés de la première passe

- Référence du lot 1 : `9e62541`.
- Lot 2 : imports explicites et constructeur de ColonyView mis en lignes,
  sans changement de comportement ; syntaxe PHP et diff vérifiés. `.editorconfig`
  existant conservé. Pas de formateur ajouté ni de formatage global : les autres
  écarts sont traités seulement avec les domaines réellement modifiés.
- Lot 4 : onglet validé avant les chargements ; recherche de joueurs uniquement
  dans Relations commandants ; membres et historiques des guildes chargés pour
  les fiches affichées ; grades/attributions seulement sur leurs onglets. Les
  compteurs de propositions restent communs, sans enrichir tous les joueurs.
  La liste des projets ne résout plus les identités des autres contributeurs ;
  la fiche garde le bilan complet. Permissions et écritures inchangées.
- Vérifications du lot 4 : 80 tests / 328 assertions, puis 44 / 211 après le
  ciblage des commandants ; régression du compteur sur cinq onglets : 1 / 15.
  Aucun style ni interaction modifié ; les listes diplomatiques affichées et les
  détails des projets gardent encore des lectures par élément, à regrouper ensuite.
- Lot 5 : hydratation des rapports regroupée par type après les droits et la
  pagination (50 maximum), aucun enrichissement des lignes joueurs sur l’onglet
  Guildes, compteur SQL des membres pour le profil public, identités des contacts
  chargées ensemble avec filtre d’univers. Classements quotidiens, protections
  en temps réel et écritures de messagerie conservés.
- Rapports/profils/classement : 31 tests / 192 assertions réussis. Les rounds
  des rapports sont encore hydratés dans la liste et les profils passent encore
  par le classement complet : exceptions restantes, pas d’amélioration revendiquée.
- Messagerie et classements : 31 tests / 176 assertions réussis après regroupement
  des contacts ; aucune recherche paginée ni aucun état de lecture modifié.
- Lot 6 : lecture groupée des planètes découvertes dans la galaxie sélectionnée,
  avec filtre d’univers serveur ; suggestions limitées aux mondes découverts
  avant préparation ; stationnement limité aux colonies propres. La liste libre
  inutilisée de Planètes est retirée après recherche de tous ses usages.
  EmpireDetails regroupe les compositions après filtrage des flottes autorisées.
  Les snapshots économiques, trajets et décisions de mission sont inchangés.
- Validation : carte/destinations 16 tests / 309 assertions ; caractérisation
  et accès 9 cas / 40 assertions (mesure optionnelle ignorée) ; stationnement
  20 / 98, puis repli renforcé 1 / 5. Correction de fixture distincte `5d0417a`.
  Le regroupement des combats et les snapshots par colonie restent à approfondir.
- Lot 7 : AdminReadRepository sépare compteurs, incidents et aperçu des dernières
  actions. Les détails de modération, joueurs, univers et exploitation ne sont
  chargés que sur leurs écrans. L’accueil lit cinq actions ; Sécurité lit les
  cinquante incidents affichés et conserve un compteur/curseur sur l’ensemble
  des incidents. Aucune preuve ou action d’administration modifiée.
- Validation : 7 tests HTTP/métier / 53 assertions ; régression sur 75 incidents
  et un examen : 1 test / 7 assertions, avec conservation des 76 traces.
  Les pages Joueurs et Journal et certaines listes spécialisées restent non
  paginées : chantier ouvert, filtres et export complet à préserver.
- Lot 8 : les actualisations JSON des colonies rendent les régions partagées
  (sélecteur, ressources, files, vols, journal ou catalogue), sans document HTML
  complet. Les lectures/synchronisations de classement et GameShell sont
  conservées ; le bandeau utilise son template inchangé et garde son remplacement
  habituel. Les vues initiales réutilisent exactement les fragments extraits.
  Une génération de requête et le contexte d’URL empêchent les réponses tardives
  de remplacer un état récent ; abort, nettoyage des écouteurs et formulaires
  modifiés/focus conservés. Flottes garde encore la réponse HTML complète.
- Validation : 7 tests HTTP / 124 assertions, puis extension journal non vide
  1 / 42 ; deux scénarios navigateur réussis (focus puis réponse inversée).
  Le premier essai du nouveau scénario envoyait son événement avant connexion
  de Stimulus ; attente d’un état DOM observable ajoutée, sans délai fixe.
- Lot 9 : extraction comparée à la source avant changement, contenu des trois
  fragments strictement conservé. Aucun CSS, image, police, logo, métadonnée ou
  réglage de compilation changé. Nettoyage des listeners/minuteries vérifié pour
  colony-live, building-catalog et catalog-sticky. Focus vérifié au lot 8.
  Compilation réussie : runtime 1,41 Kio, chunk partagé 93,6 Kio, CSS 251 Kio,
  JS applicatif 148 Kio (tailles brutes, pas le transfert compressé).
  Pas d’optimisation supplémentaire sans coût démontré. Clavier, responsive,
  contrastes automatiques et métadonnées validés dans la suite finale ; cela
  ne constitue pas un audit RGAA/WCAG complet ni une revue de tous les styles.
- Lot 10 : sept handlers et contrats de messages, routage/retries, outbox,
  garde des boosts, suppression de compte et accès du simulateur examinés de
  manière ciblée. Compositions du simulateur regroupées après contrôle de la
  colonie. Commentaire du relais traduit et précisé sans promesse d’exactement
  une livraison. Guide asynchrone corrigé pour les sept natures et guild_project.
  Aucun verrou, ordre de verrouillage, contrat persistant ou retry modifié.
- Validation : 57 tests / 539 assertions (outils stratégiques, boosts, suppression
  de compte, reconstruction et capture). Les rejoués séquentiels ne démontrent
  pas la concurrence réelle. Pas d’audit exhaustif de comptes/inventaire,
  d’infrastructure ou de dépendances ; pas d’installation ni de service externe.
- Lot 11 : index réparé, architecture/README/feuille de route reliés au suivi.
  Contrôleur JS remis en forme dans un commit distinct, AST vérifié identique
  avant l’ajout séparé du refus d’erreurs réseau devenues obsolètes. Pas de
  suppression de fichiers, formatage global, migration ou dépendance ajoutée.
- Validation déjà terminée : Compose, lint PHP/Twig/YAML/conteneur et schéma sur
  la base synthétique ; `make assets` réussi sous Node WSL 20.19.2, complété par
  une compilation de production réussie sous Node Windows 24.17.0, conforme à la
  version majeure documentée. Aucun téléchargement ni installation nécessaire.

## Exceptions et limites conservées

Les cinq compléments de performance précédemment ouverts sont traités :
contexte des bâtiments, résumés/profil individuel, identités/relations/combats,
pagination des historiques et fragments Flottes avec mesures HTTP à trois
volumes. Les exceptions restantes sont délimitées :

- Snapshots économiques par planète : conserver le moteur complet partagé avec
  le bandeau exclu. Une extraction plus large nécessiterait une étude séparée,
  sans supprimer l’historique d’événements ou de vacances pour accélérer la lecture.
- Ancien snapshot quotidien sans rangs bâtiments/recherche : conserver le
  recalcul de présentation historique jusqu’au prochain snapshot. Le chemin
  normal du profil ne prépare plus le classement entier.
- Listes de configuration éditées dans leur ensemble (modèles et catalogues) :
  conserver leurs vues complètes et liens directs. La modération conserve la
  portée actuelle de ses consultations journalisées. Les membres/historiques
  de guilde ne sont chargés que si leurs détails sont effectivement affichés.
- Conventions : appliquer les changements utiles au périmètre modifié ; ne pas
  réécrire tous les anciens tris et contrôleurs pour obtenir un diff cosmétique.
  L’inventaire n’est pas une relecture ligne par ligne de tous les fichiers.
- Accessibilité : contrôles ciblés, recette interactive, captures et suites
  automatiques ; aucune revendication de conformité RGAA exhaustive. Les essais
  avec les technologies d’assistance réelles de l’utilisateur restent à effectuer.
- Concurrence : cinq courses serveur avec deux connexions réellement bloquées
  sont maintenant vérifiées, en plus des abandons et compositions simultanés au
  navigateur. La fin de commande relit son état après les verrous. Cela ne prouve
  pas toutes les courses possibles de tous les handlers ni l’exploitation avec
  plusieurs relais/notifiers. Aucun verrou ni ordre de verrou supprimé ou changé.
- Sources externes/Work inaccessibles et CI GitHub : non vérifiées dans cette
  session ; aucune publication distante. CI complète obligatoire avant une future fusion.

Pas de cache partagé improvisé ni de projection partielle d’entité. La barre
garde son endpoint, ses calculs, ses états de vacances et sa fréquence. Une
comparaison de sources vérifie 18 méthodes/fichiers protégés identiques à
`48c9a71` (`var/rework-boundaries.json`), dont le corps de l’endpoint, snapshot,
simulation, écritures, FactoryEconomy, GameShell et les deux fichiers du bandeau.

## Mesures transversales de clôture

Mesures isolées du 15 novembre, après validation PHP : PHP 8.4.26, MariaDB
11.8, Symfony test/debug identique avant/après. Trois fixtures synthétiques
de 10, 100 et 500 joueurs/participations, autant de rapports et actions admin.
Une colonie, aucun ordre en cours, un snapshot quotidien déjà à jour, aucun
traité de guilde ; un contrat personnel pour chaque cinquième autre joueur
(accepté, ennemi ou proposition). Chaque rapport contient 80 lignes de preuve
avec 512 caractères par ligne. Ce volume de preuve sert uniquement à la mesure.

Pour chaque couple volume/domaine/version : processus PHP distinct, un premier
appel séparé puis dix passages avec caches Symfony/DQL chauds. EntityManager
vidé entre passages, collecteur SQL réinitialisé et pic mémoire réinitialisé.
Les services sont initialisés avant la mesure. Les médianes et étendues ci-dessous
incluent la préparation et la normalisation des champs visibles, pas Twig, HTTP
ni navigateur. Le collecteur de débogage est inclus dans la mémoire mesurée.

Référence : classes ReportArchiveService et SocialProfileView de 48c9a71
chargées sous un autre nom dans le seul processus de mesure ; autres dépendances
courantes identiques. Pour la diplomatie et l’administration, les séquences
de lectures du contrôleur initial sont reconstituées, avec tri du journal
départagé par identifiant pour comparer les mêmes lignes en cas d’égalité.
Comparaison des préparations concernées, sans prétendre mesurer l’ancienne
page complète. Le checkout applicatif n’est jamais remplacé.

Avant/après : métadonnées des rapports, scores/rangs du profil, relations
affichées, identifiants des 50 premières lignes et totaux admin identiques
sur les 264 appels. La suite de diplomatie couvre en plus les statuts de
guilde, absents de cette fixture. L’export admin complet est testé séparément.

| Préparation | Volume | SQL avant → après | Serveur avant [min–max], ms | Serveur après [min–max], ms | Surcroît de pic avant → après, Mio |
| --- | --- | --- | --- | --- | --- |
| Relations commandants | 10 | 67 → 5 | 24,55 [22,11–33,12] | 2,93 [2,63–4,04] | 0,33 [0,33–0,34] → 0,14 [0,14–0,14] |
| Profil public | 10 | 32 → 5 | 13,13 [12,83–14,64] | 1,86 [1,68–2,02] | 0,26 [0,26–0,27] → 0,08 [0,08–0,08] |
| Liste des rapports | 10 | 24 → 5 | 12,20 [11,02–13,36] | 3,31 [2,91–4,72] | 1,12 [1,12–1,12] → 0,10 [0,10–0,10] |
| Joueurs + journal admin | 10 | 3 → 5 | 1,78 [1,57–2,01] | 2,73 [2,37–4,12] | 0,11 [0,11–0,11] → 0,15 [0,15–0,15] |
| Relations commandants | 100 | 715 → 5 | 249,17 [242,72–315,99] | 8,28 [7,06–10,73] | 3,49 [3,49–3,55] → 0,47 [0,47–0,47] |
| Profil public | 100 | 212 → 5 | 88,92 [84,07–101,43] | 2,33 [2,21–2,82] | 1,99 [1,99–2,02] → 0,27 [0,27–0,27] |
| Liste des rapports | 100 | 104 → 5 | 47,45 [44,54–50,63] | 4,38 [3,88–5,11] | 4,98 [4,98–4,99] → 0,14 [0,14–0,14] |
| Joueurs + journal admin | 100 | 3 → 5 | 4,36 [3,78–4,97] | 4,37 [3,81–4,94] | 0,85 [0,85–0,85] → 0,49 [0,49–0,49] |
| Relations commandants | 500 | 3595 → 5 | 1357,79 [1299,63–1782,74] | 25,87 [24,53–36,89] | 17,58 [17,49–17,99] → 2,06 [2,06–2,06] |
| Profil public | 500 | 1012 → 5 | 463,98 [452,48–571,28] | 3,42 [2,61–4,47] | 9,59 [9,57–9,69] → 1,15 [1,15–1,15] |
| Liste des rapports | 500 | 104 → 5 | 50,70 [48,32–53,85] | 7,12 [6,01–8,09] | 4,98 [4,98–4,99] → 0,14 [0,14–0,14] |
| Joueurs + journal admin | 500 | 3 → 5 | 14,49 [13,44–15,74] | 5,23 [4,11–6,73] | 4,03 [4,03–4,03] → 0,49 [0,49–0,49] |

Temps SQL médians [min–max], ms :

- Relations commandants, 10 : 17,83 [16,37–24,39] → 1,62 [1,48–2,44].
- Profil public, 10 : 9,10 [8,91–10,26] → 1,32 [1,20–1,46].
- Liste des rapports, 10 : 8,60 [7,67–8,99] → 2,60 [2,28–3,75].
- Joueurs + journal admin, 10 : 1,02 [0,92–1,20] → 1,60 [1,46–2,59].
- Relations commandants, 100 : 180,89 [177,35–220,70] → 2,71 [2,45–3,22].
- Profil public, 100 : 60,73 [57,65–66,74] → 1,48 [1,42–1,70].
- Liste des rapports, 100 : 31,37 [29,86–33,59] → 3,59 [3,13–3,88].
- Joueurs + journal admin, 100 : 1,90 [1,23–2,32] → 2,07 [1,91–2,37].
- Relations commandants, 500 : 973,90 [934,42–1291,04] → 4,87 [4,22–5,55].
- Profil public, 500 : 291,48 [283,84–349,75] → 1,71 [1,37–2,53].
- Liste des rapports, 500 : 34,57 [32,94–35,83] → 6,05 [5,21–6,96].
- Joueurs + journal admin, 500 : 4,84 [4,03–6,44] → 2,42 [2,03–3,32].

Les nouvelles lectures admin font davantage de requêtes pour compter et paginer.
Le bénéfice porte sur les grandes listes et la mémoire ; aucun gain de temps
n’est garanti sur les petites fixtures. Les rapports restent bornés à 50 par
page. La lecture du snapshot de profil reste proportionnelle à sa taille JSON :
aucune projection persistée ou modification du recalcul quotidien ajoutée.

La mémoire du tableau est le surcroît de pic du chemin mesuré, pas la mémoire
totale du processus ni celle d’une requête HTTP. Les allocations totales et
premiers passages sont dans var/rework-transverse-measures.json ; un allocateur
peut réserver plus de pages malgré moins de données retenues (admin à petit
volume). Lanceur : var/measure-rework-transverse.py. Ces chiffres ne sont pas
des seuils CI et ne permettent aucun pourcentage global d’économie.

## Mesures du remplacement navigateur — clôture

Chromium réel, application isolée APP_DEBUG=0, même fixture et backend courant
pour les deux modes. Avant chaque série : navigation complète puis événement
genesis:refresh. En mode HTML, le seul en-tête de fragments est retiré de la
requête de mesure pour conserver le retour HTML complet déjà supporté ; en mode
JSON, le contrôleur courant demande ses fragments normalement. Aucune modification
du contrôleur, du bandeau ou des délais applicatifs. Comparaison du transport et
du décodage, pas de deux versions complètes du serveur.

Bâtiments et Flottes, 0/5/25 flottes actives : premier passage séparé puis dix
passages par mode, 132 actualisations au total. Le temps événement → DOM comprend
le délai de regroupement de 200 ms, le réseau et la préparation serveur. Un
observateur constate le remplacement du catalogue/comptage ; DOMParser et
replaceWith sont chronométrés séparément. Deux requestAnimationFrame donnent
aussi une mesure après opportunité de rendu, conservée dans les données brutes ;
ce n’est pas une mesure LCP ni une preuve de stabilisation de toutes les images.

| Page | Flottes | Événement → DOM HTML [min–max], ms | Événement → DOM JSON [min–max], ms | Décodage HTML → JSON, ms | Remplacements HTML → JSON, ms |
| --- | --- | --- | --- | --- | --- |
| Bâtiments | 0 | 421,05 [398,40–455,00] | 412,15 [367,90–443,90] | 16,00 [10,70–21,00] → 11,90 [7,10–18,30] | 6,90 [5,30–10,20] → 7,45 [5,20–10,80] |
| Bâtiments | 5 | 414,60 [389,60–445,50] | 425,15 [395,30–473,50] | 11,95 [8,90–18,30] → 11,40 [8,70–15,30] | 6,15 [5,10–8,90] → 6,65 [5,20–9,40] |
| Bâtiments | 25 | 468,00 [412,20–559,90] | 427,30 [399,10–471,00] | 14,35 [11,00–22,10] → 11,30 [9,20–14,00] | 6,95 [5,80–13,70] → 7,25 [5,80–15,80] |
| Flottes | 0 | 322,25 [313,20–337,50] | 330,00 [317,70–341,50] | 1,50 [1,20–2,20] → 1,20 [0,70–5,70] | 0,70 [0,40–0,90] → 0,75 [0,40–1,50] |
| Flottes | 5 | 333,90 [318,70–348,90] | 330,35 [319,50–407,10] | 1,75 [1,30–2,50] → 1,50 [1,10–2,20] | 1,05 [0,50–1,60] → 0,95 [0,60–3,00] |
| Flottes | 25 | 358,05 [331,70–443,40] | 340,90 [329,20–354,20] | 2,25 [2,10–4,80] → 1,90 [1,60–3,40] | 2,10 [1,60–5,80] → 1,80 [1,70–2,70] |

Les écarts courts varient avec les requêtes et le rendu ; aucun gain global
de réactivité n’est déduit. Les gains de poids transféré sont ceux des mesures
HTTP séparées, consignées plus haut. Les temps SQL et la mémoire HTTP de cette
mesure navigateur ne sont pas instrumentés. Données brutes :
var/rework-dom-measures.json ; scénario : var/rework-dom-measure.spec.mjs.

Le premier essai de l’instrumentation avait laissé plusieurs callbacks de rendu
terminer après le début de la mesure suivante. L’observateur est désormais
déconnecté dès le remplacement constaté. Cet incident concernait le lanceur
temporaire ; aucun correctif produit ni délai de test existant modifié.

Contrôle visuel ciblé : captures réelles Bâtiments et Flottes à 1440 et 390 pixels,
avec 25 flottes actives. Images visibles chargées, textes et commandes lisibles,
aucun débordement horizontal. Les quatre captures var/rework-{buildings,fleets}-
{1440,390}.png ont été examinées. Le contrôle visuel du bandeau ne vaut aucune
modification de son périmètre. Clavier, thèmes, formulaires et réponses tardives
restent couverts par les parcours navigateur ; la recette complète ci-dessous
reste celle de l’utilisateur, notamment avec ses technologies d’assistance.

## Fichiers du lot cumulé

77 fichiers suivis modifiés ou ajoutés par rapport à `48c9a71`.

| Périmètre | Fichiers modifiés ou ajoutés |
| --- | --- |
| Contrôleurs | `app/src/Controller/AdminController.php`, `app/src/Controller/AdminGameEventController.php`, `app/src/Controller/ColonyController.php`, `app/src/Controller/DiplomacyController.php`, `app/src/Controller/FeedbackController.php`, `app/src/Controller/FleetController.php`, `app/src/Controller/GuildProjectController.php`, `app/src/Controller/LeaderboardController.php`, `app/src/Controller/PatchNoteController.php` |
| Lectures | `app/src/Repository/AdminReadRepository.php`, `app/src/Repository/GameRepository.php`, `app/src/Repository/PatchNoteRepository.php`, `app/src/Repository/ReportSummaryRepository.php` |
| Orchestration | `app/src/Manager/GameManager.php` |
| Services et préparation | `app/src/Service/Admin/AdminAuditExport.php`, `app/src/Service/Colony/ActionGuard.php`, `app/src/Service/Colony/CatalogueDetails.php`, `app/src/Service/Colony/CatalogueInsights.php`, `app/src/Service/Colony/CatalogueProductionContext.php`, `app/src/Service/Colony/ColonyView.php`, `app/src/Service/Colony/EmpireDetails.php`, `app/src/Service/Colony/LiveRegionResponse.php`, `app/src/Service/Colony/ResourceService.php`, `app/src/Service/Combat/PlayerCombatSimulation.php`, `app/src/Service/Combat/ReportArchiveService.php`, `app/src/Service/Fleet/DestinationCatalogue.php`, `app/src/Service/Notification/OutboxRelay.php`, `app/src/Service/Player/DailyLeaderboard.php`, `app/src/Service/Player/RelationResolver.php`, `app/src/Service/Player/SocialProfileView.php`, `app/src/Service/Queue/QueueService.php` |
| Contrôleurs navigateur | `app/assets/controllers/admin_list_controller.js`, `app/assets/controllers/colony_live_controller.js` |
| Templates | `app/templates/admin/events.html.twig`, `app/templates/admin/feedbacks.html.twig`, `app/templates/admin/patchnotes.html.twig`, `app/templates/admin/sections/_audit_results.html.twig`, `app/templates/admin/sections/_list_pagination.html.twig`, `app/templates/admin/sections/_players_results.html.twig`, `app/templates/admin/sections/audit.html.twig`, `app/templates/admin/sections/players.html.twig`, `app/templates/base.html.twig`, `app/templates/colony/_catalog.html.twig`, `app/templates/colony/_journal.html.twig`, `app/templates/colony/show.html.twig`, `app/templates/combat/reports.html.twig`, `app/templates/fleet/_available.html.twig`, `app/templates/fleet/_counts.html.twig`, `app/templates/fleet/index.html.twig`, `app/templates/player/diplomacy.html.twig`, `app/templates/ui/colony_selector.html.twig` |
| Tests HTTP | `app/tests/Controller/AbsenceJournalTest.php`, `app/tests/Controller/AdminPaginationTest.php`, `app/tests/Controller/FleetDestinationTest.php`, `app/tests/Controller/GuildTagPlayerReportTest.php`, `app/tests/Controller/ReportArchiveTest.php` |
| Tests intégrés | `app/tests/Integration/AdminReadRepositoryTest.php`, `app/tests/Integration/CatalogAccessTest.php`, `app/tests/Integration/ColonyPreparationTest.php`, `app/tests/Integration/ConcurrentQueueTest.php`, `app/tests/Integration/DailyLeaderboardTest.php`, `app/tests/Integration/FleetStationingTest.php`, `app/tests/Integration/GamePersistenceTest.php`, `app/tests/Integration/PersonalDiplomacyTest.php` |
| Tests unitaires | `app/tests/Unit/LiveRegionResponseTest.php` |
| Tests de services | `app/tests/Service/CatalogueDetailsTest.php` |
| Supports de tests | `app/tests/Support/concurrent_queue.php` |
| Tests navigateur | `app/tests/browser/admin-accounts-flights.spec.mjs`, `app/tests/browser/live-refresh-focus.spec.mjs`, `app/tests/browser/support/isolated-test.mjs` |
| Environnement de test | `docker/compose.browser-test.yaml` |
| Documentation | `README.md`, `docs/ARCHITECTURE.md`, `docs/FEUILLE_DE_ROUTE.md`, `docs/README.md`, `docs/REWORK_V1.md`, `docs/WORKER_NOTIFIER.md` |

## Recette utilisateur complémentaire

Les parcours représentatifs réalisés pendant la recette autonome figurent en tête
de ce document. La liste ci-dessous reste un support de validation par l’utilisateur,
sur ses navigateurs et technologies d’assistance ; elle ne signifie pas que tous
ces parcours restent non vérifiés. Les limites externes sont signalées séparément.
Utiliser un environnement de recette et des comptes synthétiques pour les actions
modifiant l’état ; aucun essai du chantier ne porte sur la base habituelle.

- Comptes : inscription, confirmation et connexion ; préférences, changement
  d’univers et sélection de colonie. Les emails externes demandent un environnement autorisé.
- Quatre catalogues : rangs, coûts/durées, prérequis, détails, filtres mémorisés,
  liens directs et Max ; constructions et recherche commune depuis deux colonies.
- Actualisations : garder une saisie modifiée et le focus, terminer une commande,
  changer rapidement de colonie, couper/rétablir le réseau ; vérifier le journal
  non marqué lu et la barre de ressources avec ses détails, pénuries et vacances.
- Flottes : compositions, aperçu/envoi, orbites, rappel, stationnement et repli ;
  carte classique/V2, galaxies, destinations connues et masquage des inconnues.
- Rapports/messages : filtres, dates, pagination, archives/restauration, anciens
  rapports, canaux privé/guilde/Global, Ctrl + Entrée et Markdown sécurisé.
- Guildes/projets : cinq onglets, propositions reçues, grades et refus de droits,
  contribution personnelle, fiche des dons et récompense unique ; inventaire/boosts.
- Administration : accueil, comptes, univers, modération, sécurité/examen,
  exploitation et historique complet ; confirmations et refus des non-administrateurs.
- Mobile, clavier et deux thèmes : focus visible, libellés, erreurs, détails,
  mouvements réduits et parcours sans JavaScript disponibles. Les tests automatisés
  ne remplacent pas cette recette et ne prouvent pas une conformité RGAA complète.
