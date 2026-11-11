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

La suite porte sur les grades persistants, puis sur la formalisation conjointe des relations collectives et personnelles, et enfin sur les projets après arbitrage économique. Chaque lot validé doit être fusionné vers `dev` avant le suivant. L'application d'une migration à une base existante et la publication nécessitent les validations prévues dans AGENTS.md.

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
