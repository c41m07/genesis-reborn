# Lot 2 — Outils stratégiques et recherche avancée

## État et périmètre

Cadrage commencé le 13 novembre 2026 sur `feature/outils-strategiques-et-recherche`, depuis `dev` au commit `f3b9623`, après fusion autorisée de la [PR #54](https://github.com/c41m07/genesis-reborn/pull/54). Sa CI complète a réussi sur `e473255` : 883 tests PHP, 13 tests Python et 184 scénarios navigateur. Cela valide le lot 1, pas les fonctionnalités du lot 2.

Ce premier complément contient uniquement le cadrage et les décisions produit. Le simulateur joueur, les compositions enregistrées et les filtres avancés ne sont pas encore livrés. Une PR distincte vers `dev` porte le lot 2. Aucune modification du jeu, application de migration ou fusion du lot 2 n’est effectuée dans ce cadrage.

## Décisions confirmées par le propriétaire

- Simulateur : un résultat illustratif reproductible ; aucun pourcentage de victoire et aucune victoire garantie. Les paramètres et la graine doivent permettre de reproduire ce résultat.
- Compositions : 20 compositions personnelles au maximum par empire et univers, réutilisables entre les colonies de cet empire.
- Renseignements : choix explicite d’un rapport d’espionnage auquel le joueur a encore accès, même ancien. Afficher sa date et un avertissement d’ancienneté. Une catégorie absente reste inconnue ; une modification manuelle devient une hypothèse signalée.

Ces décisions complètent [DECISIONS](DECISIONS.md). Les règles économiques du lot 1 restent inchangées.

## Existant vérifié et réutilisation

| Sujet | Sources examinées | Conséquence pour le lot |
| --- | --- | --- |
| Moteur | [CombatSimulator](../app/src/Service/Combat/CombatSimulator.php), [tests](../app/tests/Service/CombatSimulatorTest.php) | Calcul sans dépendance à la base ; aléatoire reproductible avec `Mt19937` et une graine. Réutiliser ce moteur. |
| Préparation réelle | [CombatEventService](../app/src/Service/Combat/CombatEventService.php), [TechnologyEffectCalculator](../app/src/Service/Combat/TechnologyEffectCalculator.php) | Catalogue par univers/rang, recherches, forteresse, camps et arrivées modifient l’instantané. Isoler sa préparation commune, sans appeler la résolution qui applique pertes, butin et retours. |
| Mission | [FleetService](../app/src/Service/Fleet/FleetService.php), [FleetController](../app/src/Controller/FleetController.php) | Composition déjà normalisée par type/rang. L’aperçu ajuste aux disponibilités et signale `adjusted` ; préserver estimation, confirmations, transactions et jeton d’envoi. |
| Renseignements | [EspionageReportReader](../app/src/Service/Fleet/EspionageReportReader.php), [rapport visible](../app/templates/espionage/report.html.twig) | Rapport réservé à son expéditeur ; reprendre uniquement les catégories révélées de son instantané. Ne pas lire l’état actuel de la cible ni ses statistiques internes d’espionnage. |
| Messages | [SocialMessageController](../app/src/Controller/SocialMessageController.php), [SocialMessageService](../app/src/Service/Player/SocialMessageService.php), [GameRepository](../app/src/Repository/GameRepository.php) | La recherche existante concerne les destinataires. Les conversations montrent les 100 derniers messages par défaut ; leur consultation privée/guilde modifie la lecture. Créer une recherche sans ces effets. |
| Rapports | [ReportArchiveService](../app/src/Service/Combat/ReportArchiveService.php), [CombatReportReader](../app/src/Service/Combat/CombatReportReader.php) | Liste déjà paginée à 50 avec catégories, archives personnelles et périmètres SQL par univers. Étendre cette sélection avant comptage/pagination, plutôt que filtrer en mémoire. |

Aucune interface joueur de simulation ni entité de composition enregistrée n’a été repérée dans les contrôleurs et entités examinés. Le moteur interne nommé `CombatSimulator` est déjà utilisé par les combats réels.

## Découpage et complexité

### 1. Simulateur — 5/5

Parcours prévu : forces des deux camps, rangs, recherches et défenses → hypothèses → calcul illustratif → résultat, pertes et rounds. Chargement de ses vaisseaux disponibles et import explicite de renseignements autorisés ; aucune mission envoyée.

Préparer des données simples validées depuis le catalogue de l’univers actif. Partager le calcul des bonus avec le combat réel, y compris la forteresse et les armes secondaires ; éviter une seconde formule de statistiques. Les arrivées différées, camps multiples et contextes d’occupation existent dans le moteur : leur couverture dans le formulaire devra être explicitée, et toute situation non représentée sera annoncée comme hypothèse avant calcul.

Le moteur actuel accepte au plus **5 000 unités au total** et valide une borne de 100 rounds ; le réglage courant est de 10 rounds. Les univers examinés autorisent des flottes allant jusqu’à 1 000 000 de vaisseaux. La limite du moteur ne doit donc ni être masquée ni devenir une modification de la limite des flottes. Refuser un scénario excessif sans tronquer sa composition.

Avant l’ouverture joueur, mesurer temps, mémoire et volume des journaux sur petits, moyens et grands scénarios, notamment unités résistantes, nombreux tirs et maximum de rounds. Définir ensuite une borne de calcul éventuellement plus restrictive, une limite de fréquence et un traitement des refus. Aucun résultat de performance n’est revendiqué à ce stade ; 5 000 unités n’est pas une garantie de calcul rapide.

Le service de simulation doit rester sans écriture de jeu : pas de résolution de combat réel, initialisation PNJ, production matérialisée, perte, butin, notification, récompense ou mission. Les données ennemies saisies sont des hypothèses, jamais une lecture privilégiée de leurs entités. La graine illustrative ne révèle pas celle d’un combat réel.

### 2. Compositions personnelles — 4/5

Prévoir une entité liée à `PlayerUniverse`, avec nom et lignes type/rang/quantité, sans destination ni ordre d’envoi. Elle appartient à l’empire, pas à une seule colonie. Une migration sera à créer lors de l’implémentation ; son application sur une base existante nécessitera autorisation et sauvegarde vérifiée.

Créer, renommer, modifier et supprimer avec contrôles serveur de propriété/univers et CSRF. Verrouiller le périmètre propriétaire pour garantir le plafond de 20 sous concurrence ; prévoir une version pour refuser les modifications périmées et les suppressions répétées sans double effet.

Charger dans le formulaire de mission affiche les quantités demandées, disponibles et manquantes, y compris les rangs indisponibles. Tout ajustement nécessite une action explicite ; pas de réduction silencieuse. La validation finale reste celle du serveur au moment de l’envoi, car les stocks peuvent changer. Le chargement n’appelle jamais l’envoi de flotte. Ne pas détourner une mise à jour automatique de l’aperçu pour écraser les quantités demandées.

### 3. Recherche avancée — 4/5

Prévoir des filtres GET et des résultats paginés, avec recherche et contexte conservés dans l’adresse et au retour depuis un détail. Limiter les tailles et valider dates, identifiants et paramètres ; utiliser des requêtes paramétrées, avec échappement des caractères spéciaux de recherche.

Messages : contenu, conversation/canal, interlocuteur et période. Respecter les règles existantes : messages privés entre les deux membres du même univers, canal de la guilde actuelle uniquement, Global de l’univers actif. Le contrôle d’accès précède filtre, comptage et extrait, même pour un identifiant forgé. Ne pas faire passer la recherche par les méthodes de consultation qui modifient `readAt` ou les préférences `guild_read_*`. Les résultats sont consultables sans marquage ; ouvrir ensuite la conversation conserve son comportement normal de lecture. Global garde son absence de compteur de non-lus et de notifications.

Rapports : réutiliser types combat/espionnage/transport, archives et pagination existants. Ajouter planète, période et participant selon les données du type sélectionné. Les relations/instantanés accessibles portent l’identité des participants ; aucune recherche globale dans les données de combat ne doit contourner les droits. Définir clairement origine/destination pour les transports et cible pour l’espionnage. Les filtres ne changent pas les droits historiques.

Privilégier une recherche SQL simple et bornée avec les outils existants. Examiner les plans de requêtes sur un historique synthétique volumineux et les index nécessaires ; ne pas promettre qu’un index existant accélère une recherche de sous-chaîne. Toute création d’index reste distincte de son application sur une base existante. Pas de dépendance supplémentaire prévue.

## Vérifications requises pendant la réalisation

- Simulateur : mêmes instantanés/graine/règles → même résultat que le moteur ; bonus, rangs, défenses, arrivées et limites ; refus d’entrées invalides ; comparaison de l’état de jeu avant/après, aucune écriture ; mesures de charge reproductibles.
- Compositions : propriété, univers, accès après suppression de l’empire, plafond concurrent, versions périmées, doubles soumissions, vaisseaux manquants, rangs verrouillés et disponibilité changée après chargement ; aucun envoi automatique.
- Recherche : privé d’un autre joueur refusé, ancienne guilde inaccessible, Global d’un autre univers exclu, rapport étranger/archivé filtré correctement, non-lus inchangés, caractères spéciaux, bornes de dates, pagination stable et historique volumineux. Réutiliser notamment `GlobalMessageTest`, `ReportArchiveTest` et les parcours navigateur existants.
- Interface : ordinateur/mobile, clair/sombre, clavier/focus, libellés, contenus longs, états vide/chargé/refusé ; fonctionnement sans JavaScript lorsque déjà disponible. Contrôles ciblés, sans prétendre à un audit RGAA complet.
- Utiliser les contrôles pertinents du Makefile et `git diff --check`. Avant fusion du lot, attendre la CI complète sur le dernier commit ; la fusion de cette PR distincte reste soumise à autorisation.

## Prochaine étape

Préparer la construction commune des instantanés et les scénarios de mesure du moteur, puis préciser les bornes techniques et les paramètres exposés. Concevoir ensuite les migrations et parcours de composition, puis étendre la sélection paginée des messages et rapports. Actualiser règles, wiki et guides lorsque ces fonctions sont effectivement implémentées et vérifiées.
