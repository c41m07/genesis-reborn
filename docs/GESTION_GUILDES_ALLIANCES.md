# Gestion des guildes et alliances personnelles

## Périmètre validé

- Grades personnalisés avec permissions détaillées pour les guildes.
- Traités officiels avec effets et signatures contrôlés.
- Projets collectifs progressifs, contributions et récompenses équilibrées.
- Mise à jour simultanée des relations personnelles entre joueurs, sans obligation de guilde : alliances et rivalités.

## Premier socle : autorisations

Branche : `feature/gestion-guildes-alliances`, issue de `dev`. Complexité : 4/5.

`DiplomacyAccessPolicy` centralise les autorisations actuelles de commandement et l'éligibilité des relations personnelles. `DiplomacyService` l'appelle dans ses transactions existantes pour transférer le commandement, dissoudre, changer une relation de guilde ou gérer un contrat personnel.

Le commandement exige un joueur contrôlé dans le même univers et une guilde non archivée. Les relations personnelles exigent deux participations persistées distinctes, encore contrôlées, dans le même univers ; aucune appartenance à une guilde n'est requise. Les contrôles d'activité, d'accessibilité, de consentement et de fonctionnalité restent dans le service métier.

Ce premier socle ne livrait pas encore les grades persistants, traités enrichis, rivalités personnelles ou projets. Aucune migration n'est nécessaire pour ce socle. La succession J30 et les effets des alliances actuelles restent inchangés.

## Décisions ouvertes avant les extensions

| Sujet | Arbitrage nécessaire |
| --- | --- |
| Rivalité personnelle | Déclaration orientée ou hostilité réciproque ; priorité face à la même guilde et aux guildes alliées |
| Traités | Types supplémentaires, fin d'hostilité, préavis et flottes déjà engagées |
| Grades | Délégation des permissions sensibles et limites des grades |
| Projets | Coûts, paliers, récompenses, plafonds, remboursements et changement de guilde |

Les recommandations de l'audit ne valent pas validation de ces règles. Les décisions et règles actuelles restent applicables.

## Vérifications et suite

`DiplomacyAccessPolicyTest` couvre transfert d'autorité, archivage, isolation des univers, relations sans guilde et refus des empires détachés, oubliés ou non persistés. Les tests de diplomatie existants dans `GamePersistenceTest` restent nécessaires pour vérifier le raccordement aux mutations réelles.

La suite porte sur les grades persistants, puis sur la formalisation conjointe des relations collectives et personnelles, et enfin sur les projets après arbitrage économique. Décision utilisateur du 11 novembre 2026 : chaque fonctionnalité terminée est committée sur la même branche ; la PR #36 reste en brouillon jusqu’à la fin de tout le périmètre demandé. L'application d'une migration à une base existante et la publication nécessitent les validations prévues dans AGENTS.md.

## Grades personnalisés — implémentation en cours

Le dirigeant crée et modifie les grades : nom de 1 à 60 caractères, priorité de 1 à 1000 (plus petit = supérieur), permissions explicites. Ces bornes sont des validations techniques du formulaire. Des priorités égales ne permettent pas de gérer les autres membres de même priorité. L'absence de grade représente le rôle Membre sans délégation ; les adhésions existantes et nouvelles conservent ce comportement.

Permissions livrées : attribution des grades inférieurs, proposition/acceptation des alliances de guilde, déclaration d'hostilité et retour à la neutralité. Aucun grade ne confère un rôle global Symfony ni l'autorité de commandement. Seul le dirigeant crée ou modifie les permissions ; un délégué ne peut agir sur lui-même, le dirigeant, un grade égal/supérieur, un autre univers ou une autre guilde, ni attribuer des droits qu'il ne possède pas. Le transfert et la dissolution restent réservés au dirigeant. La succession J30 ne dépend pas des grades.

Les contrôles sont refaits sous verrou à chaque mutation. Les formulaires de modification portent la version du grade ; une attribution vérifie le grade attendu du membre. Une révocation de permission s'applique dès la mutation suivante.

Les contrats personnels ne dépendent jamais d'un grade et restent accessibles sans guilde. Les règles d'alliance réciproque, d'hostilité collective prioritaire et de conversion J30 ne changent pas. Les rivalités personnelles et projets ne sont pas ajoutés dans ce lot.

Migration préparée : `Version20261111010000`. Aucune donnée existante n'est reclassée automatiquement. Son application à la base locale existante nécessite une autorisation distincte. Les tests de ce lot utilisent uniquement leurs bases isolées. Ne pas utiliser le nouvel écran contre une base non migrée.

Un délégué autorisé à proposer des alliances ne peut pas effacer une hostilité existante par une proposition sans posséder également la permission de retour à la neutralité.

## Vérifications du 11 novembre 2026

- `make validate` : réussite.
- `make test` : suite complète de 598 tests PHP (209508 assertions), puis 13 tests Python réussis. Après la dernière correction de permission, reprise ciblée de 29 tests (111 assertions) réussie ; ne pas présenter cela comme une nouvelle exécution complète du contenu final.
- Navigateur : quatre scénarios ciblés consolidés par reprises : grades, onglets des alliances, état vide et orbite alliée. Le test de grades final réussit avec création, affectation, refus d'un POST non autorisé, clavier, focus visible, analyse axe ciblée et absence de débordement à 1440/768/390 px. Aucun audit RGAA complet ni exécution globale de la suite navigateur.
- `make lint` : PHP, YAML, Twig, conteneur et mappings réussis ; commande globalement en échec à la synchronisation du schéma local, puisque la migration n'y a pas été appliquée. `doctrine:schema:validate --skip-sync` réussit.
- Migration appliquée avec succès uniquement dans les bases navigateur jetables.
- `git diff --check` : réussite.
- Compilation frontend non lancée : aucun asset modifié. Les six contrôles obligatoires restent à compléter avant commit/push.
- Aucun commit, push ou déploiement. La base locale existante attend une sauvegarde vérifiée et une autorisation de migration avant utilisation du code.

Les premières erreurs (type explicite de version Doctrine, résolution du domaine par le client HTTP du test et contexte axe) ont été corrigées sans retirer les assertions.

## Fichiers du lot

- Entités : `app/src/Entity/GuildRank.php`, `app/src/Entity/GuildMember.php`.
- Services : `app/src/Service/Player/DiplomacyAccessPolicy.php`, `app/src/Service/Player/GuildRankService.php`, `app/src/Service/Player/DiplomacyService.php`.
- Contrôleur : `app/src/Controller/DiplomacyController.php`.
- Interface : `app/templates/player/_guild_hub.html.twig`, `app/templates/player/_guild_diplomacy_card.html.twig`, `app/templates/player/_guild_ranks.html.twig`, `app/templates/player/_guild_rank_form.html.twig`.
- Migration : `app/migrations/Version20261111010000.php`.
- Tests : `app/tests/Unit/DiplomacyAccessPolicyTest.php`, `app/tests/Integration/GuildRankTest.php`, `app/tests/browser/guild-ranks.spec.mjs`.
- Documentation : `README.md`, `docs/GESTION_GUILDES_ALLIANCES.md`.

## Application locale autorisée — 11 novembre 2026

L'utilisateur a autorisé la sauvegarde vérifiée puis la migration de la base locale. Une seule migration était en attente : `Version20261111010000`.

Sauvegarde créée dans `var/backups/avant-grades-20261111/genesis-genesis_reborn_v2-20261110T231553Z-oE9FfBG4Sen4.sql.gz`. Le script a vérifié l'intégrité gzip et le manifeste SHA-256 ; aucune ancienne sauvegarde n'a été supprimée. La restauration de cette archive n'a pas été exécutée.

`make migrate` a appliqué cette migration avec succès : cinq requêtes SQL, sans reclassement des membres existants. `make lint` réussit désormais intégralement, y compris la synchronisation du schéma local. `git diff --check` réussit.

Cette étape lève le blocage local indiqué dans le bilan précédent. Les grades sont utilisables sur la base locale migrée. Aucun commit, push ou déploiement effectué ; les validations restantes avant publication ne sont pas déclarées réalisées.

## Vérifications avant publication — 11 novembre 2026

- `make validate` et `make lint` : réussites déjà obtenues sur le même contenu, schéma local compris.
- `make test` relancé sur le contenu final : 599 tests PHP, 209509 assertions, puis 13 tests Python réussis.
- `make assets` (qui exécute `npm run build`) : compilation de production réussie.
- `make test-browser` complet : 143 réussites et neuf interruptions dans la préparation, avant les scénarios, car Docker déclarait RabbitMQ indisponible. Les neuf scénarios concernés ont tous réussi lors d'une reprise ciblée dans un nouvel environnement jetable, sans modifier ni affaiblir les tests. Bilan : 152 scénarios validés par consolidation, et non une exécution complète de 152 réussites d'un seul passage.
- Reprise : `make test-browser BROWSER_ARGS="universe-closing.spec.mjs universe-colonies-ui.spec.mjs universe-join-sequential.spec.mjs universe-settings-ui.spec.mjs"` : neuf réussites.
- `git diff --check` et contrôle du diff préparé : réussites.

Ces résultats sont locaux et ne constituent pas une validation de la CI GitHub. Le lot publié concerne les grades et permissions de guilde, avec conservation et tests des contrats personnels ; les traités enrichis, rivalités personnelles et projets restent hors de ce lot.

## Complément personnel en cours — 11 novembre 2026

Décisions utilisateur : rivalité réciproque sur déclaration d'un joueur, priorité des liens de guilde et fin par paix bilatérale. La source métier est consignée dans DECISIONS.md. Ce complément est développé sur la même branche, à la demande de l'utilisateur ; aucun nouveau commit ni push n'est implicite.

Le stockage existant AllianceContract porte les états `enemies` et `peace_pending`, sans nouvelle migration. Le proposant identifie l'auteur de l'offre en attente. Une paix acceptée passe à `ended` ; une nouvelle proposition d'alliance réutilise ensuite la paire unique. Ce stockage ne constitue pas un journal détaillé des anciens accords.

Les grades ne donnent aucun droit sur les relations personnelles des autres membres. Les calculs effectifs sont partagés par la diplomatie, la carte et le classement. La conversion termine également ces états ; une hostilité de guilde invalide les alliances incompatibles sans effacer une rivalité personnelle.

Le contrat terminé n'est plus présenté comme une invitation et ne propose pas d'acceptation/refus erroné. La paix en attente ne propose jamais de rupture unilatérale de la rivalité. Les refus de paix et retraits de proposition maintiennent l'hostilité.

La PR du premier commit n'a pas été créée automatiquement : le connecteur a affiché un formulaire de soumission ; une vérification ultérieure n'a trouvé aucune PR pour cette branche.

### Vérifications du complément personnel

- `make validate` : réussite.
- Tests ciblés initiaux : 18 réussites, 54 assertions.
- `make test` : 617 tests PHP, 209563 assertions, puis 13 tests Python réussis. La redirection des nouvelles actions a ensuite été corrigée et vérifiée par le parcours navigateur final ; la suite PHP complète n'a pas été relancée pour cette seule correction de navigation.
- `make lint` final et `git diff --check` : réussites, schéma local synchronisé.
- Navigateur ciblé : huit parcours existants de colonie et de grades réussis, puis le nouveau parcours personnel réussi après reprises. Résultat consolidé de neuf scénarios, pas une nouvelle exécution globale de la suite navigateur.
- Le nouveau parcours vérifie l'alliance initiale, la déclaration au clavier, la rivalité réciproque, la classe ennemi du classement, le refus d'une rupture unilatérale, le refus d'une paix, la paix acceptée par l'autre joueur, une nouvelle alliance, le responsive et une analyse axe ciblée.
- Les reprises ont corrigé l'attente après validation au clavier, la redirection vers Relation et la préparation de l'instantané quotidien dans la seule base de test. Aucune règle de classement ni assertion de rivalité n'a été affaiblie.
- Aucune nouvelle migration, installation, modification d'équilibrage, création de commit ou publication de ce complément. La compilation frontend et la suite navigateur globale restent à compléter avant un futur commit/push suivant les consignes du dépôt.

### Fichiers du complément personnel

- Calcul et transitions : `app/src/Entity/AllianceContract.php`, `app/src/Service/Player/RelationResolver.php`, `app/src/Service/Player/DiplomacyService.php`.
- Affichage : `app/src/Controller/DiplomacyController.php`, `app/src/Controller/ColonyController.php`, `app/src/Controller/LeaderboardController.php`, `app/templates/player/_relation_card.html.twig`.
- Conversion et compteurs : `app/src/Service/Player/EmpireConversionService.php`, `app/src/Repository/GameRepository.php`.
- Tests : `app/tests/Integration/PersonalDiplomacyTest.php`, `app/tests/Unit/PersonalRelationCardTest.php`, `app/tests/browser/personal-rivalries.spec.mjs`.
- Documentation : `README.md`, `docs/DECISIONS.md`, `docs/REGLES.md`, `docs/GESTION_GUILDES_ALLIANCES.md`.

### Validation finale du complément avant publication — 11 novembre 2026

- `make validate` et `make lint` : réussites conservées sur le même contenu final ; aucun changement de configuration ni de schéma supplémentaire.
- `make test` final : 617 tests PHP, 209563 assertions et 13 tests Python réussis, après la correction de redirection.
- `make assets` / `npm run build` : compilation de production réussie.
- Suite navigateur intégrale répartie par `--shard=1/4` à `--shard=4/4` : 39, 48, 29 et 37 réussites, soit 153 scénarios, sans échec ni reprise dans cette campagne finale.
- Chaque partition a utilisé son propre projet Docker, sa base jetable, ses caches et son dossier de résultats, avec un seul worker par partition. Aucun test ni délai n'a été modifié pour cette répartition.
- `git diff --check` et `git diff --cached --check` : réussites.

Ces résultats remplacent les vérifications partielles précédentes pour la publication de ce complément. Ils restent locaux et ne constituent pas une validation de la CI GitHub. Aucune nouvelle migration ni aucun déploiement n'est inclus.

## Traités officiels — développement du 11 novembre 2026

Les trois types et leurs effets sont validés dans DECISIONS.md et REGLES.md. Complexité : 5/5 pour les droits diplomatiques et leurs contrôles de flotte. Les propositions restent distinctes de la relation effective ; une signature reçue ne peut être acceptée par son auteur. Les versions et identifiants de formulaire empêchent d’appliquer une ancienne confirmation à une autre relation.

`GuildRelation.proposal` stocke l’offre en cours ; `version` protège la concurrence. `GuildTreatyEvent` conserve chaque proposition, signature, refus/retrait, rupture et hostilité avec date UTC, identifiants des guildes et de l’acteur, états avant/après. Les identifiants sont des instantanés sans lien bloquant la dissolution ; aucune histoire antérieure n’est inventée. L’interface limite l’historique aux dix dernières actions de la paire et de l’univers du joueur. La conversion/archivage neutralise aussi les propositions.

Route existante : `app_diplomacy`, GET/POST `/diplomatie`, action `relation`, sous-actions explicites `propose`, `accept`, `decline`, `break`, `hostile`. Chaque POST vérifie CSRF, univers, activité, appartenance, permission et version dans la transaction. L’adaptateur interne à trois arguments reste compatible avec les appels métier existants.

Migration préparée : `Version20261111020000`. Ne pas appliquer à une base existante sans autorisation distincte. Les vérifications de développement utilisent uniquement le projet Docker jetable `genesis-alliances-work-20261111` et les projets navigateur isolés. Les validations complètes restent à exécuter ; aucun commit ni push de cette fonctionnalité à ce stade. La PR #36 reste en brouillon jusqu’à la fin des traités et projets collectifs.

### Validation du lot des trois traités — 11 novembre 2026

- `make validate`, `make lint`, schéma compris, et `make assets` / `npm run build` : réussites sur le contenu final.
- `make test` final : 634 tests PHP, 209638 assertions et 13 tests Python réussis. Dans le projet jetable, l’URL publique Mercure est passée à l’exécution PHP pour correspondre au domaine attendu par les tests HTTP ; aucun changement de configuration du produit. La première exécution utilisait le domaine du navigateur isolé et faisait échouer le seul test de cookie Mercure. Celui-ci a ensuite réussi isolément puis dans la suite complète.
- Suite navigateur globale : partitions de 39, 48, 30 et 37 scénarios. Les partitions 1, 2 et 4 sont intégralement vertes ; la partition 3 compte 29 réussites et un dépassement de son délai de 60 secondes dans le parcours clavier général. Ce scénario a réussi dans un projet neuf en 46,5 secondes, sans changement d’assertion ni de délai. Bilan : 154 scénarios validés par consolidation, pas une campagne complète sans échec d’un seul passage.
- Le parcours des traités couvre les trois accords, les signatures, les termes inchangés pendant une offre, le refus de formulaire périmé, l’hostilité, le refus de paix, la paix bilatérale, l’historique limité, le clavier, trois largeurs et une analyse axe ciblée. Ces contrôles ne constituent pas un audit RGAA complet.
- Les tests PHP vérifient notamment les droits séparés, la conservation des effets avant signature, la non-agression au départ et à l’arrivée, l’absence d’acceptation automatique du stationnement, les propositions concurrentes, la paix, les rivalités personnelles et la conservation de l’historique après dissolution.
- Migration appliquée uniquement aux bases jetables, y compris depuis une installation vierge. Aucune application à la base existante ni aucun déploiement.

Point d’intégration à résoudre avant fusion : le chantier local parallèle `feature/reconstruction-empires-oublies` utilise aussi `Version20261111010000`, pour `empire_reconstruction`, alors que ce numéro appartient déjà à la migration des grades de cette branche, appliquée antérieurement à la base locale sur autorisation. Ne pas remplacer ni renuméroter silencieusement cette migration déjà exécutée. Aucun fichier du chantier parallèle n’a été modifié ici.

Les projets collectifs restent le lot suivant. Décisions utilisateur reçues : dons de ressources définitifs, paliers communs, minimums de contribution par ressource, présence dans la guilde à la fin, un seul achèvement par guilde et un boost maximum par joueur et modèle de projet malgré un changement de guilde. Coûts et minimums administrables ; aucun modèle actif par défaut.
