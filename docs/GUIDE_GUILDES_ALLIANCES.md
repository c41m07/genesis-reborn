# Guide des guildes, alliances et projets collectifs

## État de livraison

La [PR #36](https://github.com/c41m07/genesis-reborn/pull/36) est fusionnée dans `dev` le 11 novembre 2026 au commit `50e7f33b96fa2e03cfa5e5114e9b6b99f04efbb7`. Elle livre les grades personnalisés, les relations entre joueurs, les trois traités entre guildes et les projets collectifs.

La revue finale n’a relevé aucune anomalie bloquante sur les contrôles d’accès, les consentements, les prélèvements de ressources, les récompenses et la compatibilité des migrations. La [CI de la PR](https://github.com/c41m07/genesis-reborn/actions/runs/43076241165) et celle du push sont vertes sur `00bcb98` : PHP, quatre lots navigateur et contrôle de secrets. Aucun lot n’a nécessité de relance en CI. La publication a été ignorée normalement sur cette branche.

Cette fusion ne constitue pas un déploiement. Aucune nouvelle migration n’a été appliquée à la base habituelle pendant cette livraison. Aucun modèle de projet n’est fourni actif par défaut. Les décisions produit restent dans [DECISIONS](DECISIONS.md), les règles courantes dans [REGLES](REGLES.md), et les étapes historiques dans [le suivi du chantier](GESTION_GUILDES_ALLIANCES.md).

## Trouver les écrans

L’entrée **Alliances** ouvre `/diplomatie` et propose trois onglets :

| Onglet ou écran | Utilisation |
| --- | --- |
| Guildes, `/diplomatie?tab=guilds` | Créer ou rejoindre une guilde, consulter ses membres, gérer ses grades et son commandement. |
| Diplomatie, `/diplomatie?tab=diplomacy` | Rechercher une autre guilde, proposer et signer un traité, gérer l’hostilité ou la paix. |
| Relation, `/diplomatie?tab=relations` | Gérer ses alliances et rivalités personnelles, même sans guilde. |
| Projets collectifs, `/guildes/projets` | Lancer un modèle autorisé, financer les paliers et consulter les contributions. |
| Administration, `/admin/projets-guilde` | Créer et modifier les modèles de projets par univers avec un compte administrateur. |

Les relations restent limitées au même univers. Les projets et les grades nécessitent une guilde et l’activation de la fonctionnalité guildes dans l’univers. Les relations personnelles ne nécessitent pas de guilde.

## Grades et permissions

Le dirigeant crée ou modifie un grade depuis **Guildes → Grades et permissions**. Il choisit son nom, sa priorité et ses permissions. Un nombre de priorité plus petit représente un grade supérieur ; le nom seul ne donne aucun pouvoir. Un membre sans grade personnalisé ne possède aucune permission déléguée.

Le dirigeant conserve le commandement indépendamment de son grade. Le rôle administrateur ne donne pas automatiquement le commandement d’une guilde. La création et la modification des grades restent réservées au dirigeant ; l’attribution de grades inférieurs peut être déléguée.

| Action | Autorisation |
| --- | --- |
| Créer ou modifier les grades | Dirigeant uniquement. |
| Attribuer ou retirer un grade à un autre membre | Dirigeant, ou `members.assign_rank` dans les limites ci-dessous. |
| Proposer, accepter, refuser ou retirer une proposition d’alliance | `diplomacy.alliance`. |
| Gérer une proposition de non-agression | `diplomacy.non_aggression`. |
| Gérer une proposition de défense | `diplomacy.defense`. |
| Déclarer une hostilité | `diplomacy.hostility`. |
| Rompre un traité ou négocier la paix | `diplomacy.neutral`. |
| Lancer un projet collectif | `projects.start`. |
| Contribuer à un projet de sa guilde | Tout membre éligible, sans permission de grade particulière. |
| Transférer le commandement ou dissoudre la guilde | Dirigeant uniquement. |

Le dirigeant dispose des permissions de guilde ci-dessus. Un délégué ne peut pas changer son propre grade, celui du dirigeant ou celui d’un membre de priorité égale ou supérieure. Le grade attribué doit être inférieur au sien et ne contenir aucune permission qu’il ne possède pas. Un grade d’une autre guilde est refusé. Retirer une affectation rend le membre sans permission déléguée ; cela ne supprime pas le modèle de grade.

La révocation d’une permission s’applique à l’action suivante. Les anciens formulaires de grade ou d’affectation sont refusés lorsque la version ou l’affectation attendue a changé. Recharger la page permet de consulter la nouvelle situation avant de confirmer.

## Les trois traités entre guildes

Un seul traité est actif pour une paire de guildes. Une proposition de remplacement conserve les effets du traité actuel jusqu’à son acceptation.

| Effet | Alliance complète | Non-agression | Défense |
| --- | --- | --- | --- |
| Attaque ou conquête directe contre le partenaire | Restrictions existantes entre alliés. | Bloquée au départ et à l’arrivée. | Même blocage que la non-agression. |
| Stationnement défensif en orbite | Accès selon les règles alliées et le consentement applicable. | Aucun droit supplémentaire. | Accès défensif avec accord du propriétaire. |
| Transport si le destinataire n’accepte que ses alliés | Relation reconnue comme alliée ; les autres conditions restent applicables. | Aucun droit supplémentaire. | Aucun droit supplémentaire. |
| Regroupement des attaquants | Règles existantes des alliés. | Aucun regroupement accordé par le pacte. | Aucun regroupement offensif accordé par le pacte. |

Un pacte ne garantit pas de recevoir un transport : les préférences du destinataire, les vacances et les conditions habituelles restent déterminantes. L’alliance personnelle acceptée peut accorder séparément des droits existants ; le pacte ne les crée pas.

L’espionnage reste autorisé sous les pactes de non-agression et de défense, sous réserve des restrictions ordinaires. Ces pactes n’empêchent pas les tirs entre camps opposés dans un combat impliquant plusieurs camps. La défense ponctuelle déjà ouverte aux autres joueurs reste régie par ses règles habituelles. Le pacte de défense concerne la protection et le stationnement ; il ne lance aucune flotte automatiquement et n’oblige personne à envoyer des renforts.

### Signer, remplacer ou rompre

1. Dans **Diplomatie**, rechercher l’autre guilde et sélectionner le traité souhaité.
2. Un membre disposant de la permission correspondante envoie la proposition.
3. Un membre autorisé de l’autre guilde l’accepte. La guilde proposante ne peut pas signer à la place de l’autre.
4. L’une des guildes peut refuser ou retirer une proposition ; la relation active reste applicable.
5. Une guilde ayant `diplomacy.neutral` peut rompre immédiatement le traité. Toute proposition de remplacement est aussi annulée.

Les formulaires contiennent l’identité et la version de la relation : une ancienne page ne peut pas accepter des conditions modifiées entre-temps. Les dix dernières actions sont consultables dans la carte diplomatique. Le journal conservé en base n’est pas supprimé par la dissolution d’une guilde.

### Hostilité et paix entre guildes

Une seule guilde autorisée peut déclarer l’hostilité : elle devient réciproque et remplace immédiatement le traité éventuel. Les contrats personnels alliés entre membres de guildes ennemies sont interrompus selon les règles existantes.

Pour terminer l’hostilité, une guilde propose la paix et l’autre l’accepte avec `diplomacy.neutral`. L’hostilité reste active tant que cet accord n’est pas obtenu. Un refus ou un retrait de l’offre ne suffit pas à rétablir la neutralité. Après la paix, une nouvelle proposition de traité redevient possible.

## Alliances et rivalités entre joueurs

L’onglet **Relation** fonctionne même sans guilde. Une alliance personnelle nécessite une proposition et l’acceptation de l’autre joueur. Elle peut ensuite être rompue unilatéralement.

Une déclaration de rivalité suffit à créer une hostilité réciproque et remplace l’alliance personnelle éventuelle. La paix exige une proposition puis l’acceptation de l’autre joueur. Proposer, refuser ou retirer une paix ne termine pas la rivalité.

Les liens collectifs priment pour les autorisations de combat : être dans la même guilde ou dans des guildes alliées empêche de contourner les protections alliées par une rivalité personnelle. La rivalité reste enregistrée et peut reprendre ses effets si ce lien cesse. Une paix personnelle ne neutralise pas une hostilité entre guildes. Les pactes conservent également leurs propres restrictions de non-agression.

Les grades de guilde ne donnent aucun pouvoir sur les relations personnelles d’un autre joueur. La conversion d’un empire termine ses relations personnelles actives selon les règles de conversion.

## Projets collectifs : participer

Un projet utilise un modèle défini par l’administration pour son univers. Le dirigeant ou un membre ayant `projects.start` peut le lancer depuis **Projets collectifs**. Tous les membres peuvent ensuite contribuer depuis une colonie leur appartenant.

Le lancement fige le nom, la description, les coûts de chaque palier et les minimums de récompense. Une modification administrative ultérieure ne change pas un projet en cours. Un modèle désactivé cesse d’être proposé aux nouveaux lancements ; les projets déjà lancés continuent.

Pour donner, choisir sa colonie et indiquer les ressources du palier actuel. Les quantités sont entières, positives au total et limitées aux besoins restants. Le débit, la progression et la contribution sont enregistrés dans la même transaction. Une ressource inconnue, une quantité invalide, un stock insuffisant, une colonie étrangère ou un formulaire périmé entraîne un refus sans prélèvement partiel.

Un palier se termine quand toutes ses ressources sont financées ; le suivant devient alors accessible. Un don ne finance pas automatiquement les paliers suivants. Le dernier palier termine le projet et fige les membres éligibles à sa récompense.

### Conditions du boost

- Avoir atteint chacun des minimums positifs par ressource, en cumulant ses dons sur tous les paliers.
- Être encore membre de la guilde à la clôture du projet.
- Ne pas avoir déjà reçu le boost de ce même modèle sur son compte, même après changement de guilde ou création d’un nouvel empire.

Exemple pédagogique, sans valeur active par défaut : si un modèle demande au moins **100 métal et 50 hydrogène** par participant, donner 200 métal et 0 hydrogène ne suffit pas. Les deux minimums doivent être atteints ; aucun taux de conversion entre ressources n’est utilisé.

La récompense est un boost standard de l’inventaire existant, pas un bonus permanent ni une récompense proportionnelle au montant donné. Le relais et le worker le distribuent après la clôture. Un inventaire plein place la récompense en attente. Le traitement peut reprendre après une interruption sans attribuer le boost deux fois. Un départ après la clôture ne retire pas une récompense déjà acquise.

### Dons, départ et dissolution

Les dons sont définitifs. Quitter une guilde, dissoudre celle-ci ou abandonner le projet ne rembourse aucune ressource. Un contributeur absent de la guilde à la clôture ne remplit pas la condition de présence. Les projets actifs sont abandonnés lors de la dissolution ou de l’archivage de leur guilde ; leurs contributions restent conservées. Les projets déjà terminés gardent leurs récompenses acquises.

Un même modèle ne peut être lancé qu’une fois par guilde, y compris si son premier projet a été abandonné. Pendant les vacances, les projets restent consultables, mais le lancement et les dons sont interdits.

## Administration des modèles

Depuis `/admin/projets-guilde`, avec `ROLE_ADMIN` :

1. Choisir l’univers, le nom et la description.
2. Définir les paliers successifs et leurs coûts par ressource. L’ajout et le retrait du dernier palier fonctionnent aussi sans JavaScript.
3. Définir les minimums par ressource pour recevoir le boost.
4. Vérifier les coûts totaux et la borne indicative du nombre de récompenses possibles.
5. Enregistrer le modèle et choisir explicitement sa disponibilité avant de permettre de nouveaux lancements.

Les bornes techniques sont de 1 à 10 paliers, avec au plus un milliard d’unités par ressource et palier. Au moins un minimum de récompense doit être positif ; aucun minimum ne peut dépasser le coût total de sa ressource. Ces bornes ne fixent pas les montants d’équilibrage à choisir pour l’univers.

La borne affichée des bénéficiaires est le minimum, sur les ressources exigées, de `partie entière(coût total / minimum individuel)`. C’est un maximum théorique : la répartition réelle des dons et la présence à la clôture peuvent réduire le nombre de bénéficiaires.

L’univers d’un modèle existant ne peut pas changer. Les modifications sont versionnées et journalisées avant/après dans l’audit administrateur. Aucun modèle actif n’est créé automatiquement et aucun écran de suppression de modèle n’est fourni par ce lot. Les coûts et seuils restent à décider avant activation ; ce guide n’en active aucun.

## Repères pour le développement

| Sujet | Fichiers de référence |
| --- | --- |
| Permissions et grades | [DiplomacyAccessPolicy](../app/src/Service/Player/DiplomacyAccessPolicy.php), [GuildRankService](../app/src/Service/Player/GuildRankService.php). |
| Relations et effets | [DiplomacyService](../app/src/Service/Player/DiplomacyService.php), [RelationResolver](../app/src/Service/Player/RelationResolver.php), [CombatEventService](../app/src/Service/Combat/CombatEventService.php), [TransportService](../app/src/Service/Fleet/TransportService.php). |
| Modèles et validation | [GuildProjectDefinitionService](../app/src/Service/Player/GuildProjectDefinitionService.php), [GuildProjectRules](../app/src/Service/Player/GuildProjectRules.php). |
| Dons, paliers et clôture | [GuildProjectService](../app/src/Service/Player/GuildProjectService.php). |
| Distribution des boosts | [GuildProjectRewardService](../app/src/Service/Player/GuildProjectRewardService.php), [BoostGrantService](../app/src/Service/Boost/BoostGrantService.php), [OutboxRelay](../app/src/Service/Notification/OutboxRelay.php), [worker et notifier](WORKER_NOTIFIER.md). |

Les grades utilisent `guild_rank` et l’affectation facultative `guild_member.rank_id`. Les traités utilisent `guild_relation` et `guild_treaty_event`. Les projets utilisent `guild_project_definition`, `guild_project` et `guild_project_contribution` ; la récompense reste dans `player_boost`. Les contraintes uniques protègent le lancement par guilde/modèle, le cumul d’un contributeur par projet et la clé de récompense par compte/modèle.

Les mutations sont protégées par authentification, CSRF, vérifications serveur et transactions. Les versions sont contrôlées pour les grades modifiés, les traités, les modèles et les contributions ; l’affectation d’un grade vérifie l’affectation attendue. L’administration des modèles vérifie aussi `ROLE_ADMIN` dans le contrôleur et le service. Les pages restent privées ; les tests ciblés d’accessibilité ne constituent pas un audit complet de conformité RGAA.

Les routes POST sont `app_guild_project_start` (`/guildes/projets/modeles/{id}/lancer`) et `app_guild_project_contribute` (`/guildes/projets/{id}/contribuer`). Les actions de grade, de traité et de relation personnelle utilisent les POST de `app_diplomacy` (`/diplomatie`). L’administration utilise `app_admin_guild_projects` en GET/POST.

## Migrations et préparation d’une livraison

| Migration | Rôle |
| --- | --- |
| `Version20261111010000` | Fichier original des grades, conservé. Ce numéro a aussi été enregistré pour la reconstruction dans l’autre historique. |
| `Version20261111020000` | Version des relations, proposition explicite et journal des traités. |
| `Version20261111150933` | Modèles, projets et contributions. |
| `Version20261111173000` | Réconcilie les deux historiques en créant uniquement les structures de grades/reconstruction manquantes ; refuse les schémas partiels. |

La réconciliation ne renomme ni ne supprime les entrées historiques de migration. Une base ayant les grades conserve ses affectations ; une base ayant la reconstruction conserve ses références ; une base ayant les deux ne reçoit aucun DDL supplémentaire de cette réconciliation. Son retour est volontairement irréversible : le code ne peut pas distinguer de façon sûre les structures qui existaient avant son passage.

Avant toute application sur une base existante, identifier l’environnement et l’historique réels, obtenir l’autorisation prévue, disposer d’une sauvegarde vérifiée et suivre [la procédure de livraison](DEPLOIEMENT.md) ainsi que [les sauvegardes](SAUVEGARDES.md). Garder l’application et les consommateurs hors trafic pendant l’évolution du schéma, puis démarrer tous les processus avec la même version et contrôler leur santé, la synchronisation du schéma et les traitements de récompenses. Une inspection du numéro de migration seul ne distingue pas les deux historiques.

Ce guide ne réalise aucune de ces opérations. La base habituelle, les migrations en attente, l’activation des modèles et le déploiement restent distincts de la fusion Git.

## Vérifications disponibles

| Risque couvert | Tests principaux |
| --- | --- |
| Élévation de permissions, grade étranger, anciennes versions | [GuildRankTest](../app/tests/Integration/GuildRankTest.php), [DiplomacyAccessPolicyTest](../app/tests/Unit/DiplomacyAccessPolicyTest.php). |
| Consentement, effets des trois traités, paix et pacte signé pendant un vol | [GuildTreatyTest](../app/tests/Integration/GuildTreatyTest.php). |
| Relations personnelles et priorité des guildes | [PersonalDiplomacyTest](../app/tests/Integration/PersonalDiplomacyTest.php). |
| Débits, minimums, présence, reprises, quota par compte et inventaire plein | [GuildProjectTest](../app/tests/Integration/GuildProjectTest.php), [GuildProjectRulesTest](../app/tests/Unit/GuildProjectRulesTest.php). |
| Deux historiques de migration, données préservées et schémas partiels | [MigrationHistoryCompatibilityTest](../app/tests/Integration/MigrationHistoryCompatibilityTest.php). |
| Parcours réels, formulaires, droits, mobile et fonctionnement sans JavaScript | [Parcours navigateur](../app/tests/browser). |

Avant le commit final de la fonctionnalité : `make validate`, `make lint` avec schéma, `make test`, `npm run build` et `git diff --check` réussis ; 701 tests PHP / 209981 assertions et 13 tests Python. Les 158 scénarios navigateur locaux ont été validés par consolidation après reprise isolée du clavier, sans modifier son délai ni ses assertions. La CI du contenu fusionné a ensuite réussi intégralement, sans relance. La revue finale a réutilisé ces preuves sur le même code, sans annoncer un nouveau déploiement ni une nouvelle validation globale du projet.
