# Lot 2 — Outils stratégiques et recherche avancée

## État et périmètre

Cadrage commencé le 13 novembre 2026 sur `feature/outils-strategiques-et-recherche`, depuis `dev` au commit `f3b9623`, après fusion autorisée de la [PR #54](https://github.com/c41m07/genesis-reborn/pull/54). Sa CI complète a réussi sur `e473255` : 883 tests PHP, 13 tests Python et 184 scénarios navigateur. Cela valide le lot 1, pas les fonctionnalités du lot 2.

La PR [#55](https://github.com/c41m07/genesis-reborn/pull/55), distincte vers `dev`, contient maintenant les trois fonctionnalités. Les vérifications sont en cours ; aucune fusion ni aucun déploiement du lot 2. La migration des compositions a été appliquée uniquement à la base locale, avec accord explicite après sauvegarde et vérification d’intégrité. Aucune application sur Alpha.

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

## Implémentation et utilisation

Le découpage ci-dessus conserve les risques et critères du cadrage. Les parcours
implémentés sont les suivants :

- `/simulateur`, accessible depuis Opérations et Flottes : deux camps, rangs,
  recherches, défenses, forteresse, arrivée des vaisseaux au round et graine.
  « Charger mes forces » reprend les vaisseaux disponibles d’une colonie propre.
  Depuis un rapport d’espionnage personnel, « Simuler avec ces renseignements »
  reprend uniquement son instantané daté. Une catégorie non révélée reste
  inconnue ; les modifications deviennent des hypothèses identifiées.
- `/flottes/compositions` : créer, modifier, renommer et supprimer avec CSRF,
  propriétaire/univers, verrou sur l’empire, version optimiste et jeton de création.
  L’écran de chargement compare demandé/disponible/manquant pour la colonie.
  Accepter explicitement les disponibilités ouvre seulement la préparation.
  L’empreinte refuse un chargement périmé ; aperçu et envoi refusent ensuite
  toute réduction automatique de cette composition. Les contrôles réels de
  mission, ressources, verrous et jetons d’envoi sont conservés.
- `/messages/recherche` : contenu littéral, canal, interlocuteur/conversation,
  période et pages de 50. Le résultat et son texte complet ne marquent rien lu.
  Ouvrir la conversation normale peut la marquer lue, comme auparavant.
- `/rapports` : type, archives, planète, participant et période avant comptage et
  pagination. Planète = lieu du combat, cible espionnée, origine/destination
  actuellement liées au transport. Participant = membres enregistrés du combat,
  expéditeur du rapport d’espionnage, expéditeur/destinataire du transport.
  Aucun nom de cible non révélé n’est déduit. Les noms conservés sont utilisés,
  sans prétendre reconstituer leur historique. Le retour et l’archivage gardent
  les filtres. Les bornes de dates suivent le fuseau des préférences.

Le simulateur réutilise `CombatSimulator` et la fabrique de statistiques commune
`CombatUnitFactory` avec le résolveur réel. Le calcul ne passe pas par la
résolution qui paie les pertes et récompenses. Les informations ennemies sont
soit celles du rapport autorisé, soit des hypothèses manuelles.

### Bornes techniques et mesures locales

Limites de l’interface joueur : **500 unités au total**, budget
`unités × tirs pondérés × rounds ≤ 5 000 000`, **6 calculs/minute par compte**.
Les tirs pondérés additionnent maximums des armes principales et secondaires,
avec une majoration conservative pour les chances de tirs supplémentaires.
Une requête excessive est refusée, jamais tronquée. Les limites des flottes
réelles restent inchangées. Ces limites techniques ne sont pas un équilibrage.

Mesures locales du moteur, PHP, plafond mémoire 256 Mio, unités très résistantes
sans destruction, même graine ; elles ne constituent pas une garantie serveur :

| Unités | Rounds | Configuration | Durée | Pic mémoire |
| --- | --- | --- | --- | --- |
| 20 | 10 | 1 tir par unité | 0,002 s | 4 Mio |
| 200 | 10 | 1 tir par unité | 0,115 s | 4 Mio |
| 500 | 10 | 1 tir par unité | 0,780 s | 8 Mio |
| 1 000 | 10 | 1 tir par unité, hors limite joueur | 3,060 s | 12 Mio |
| 500 | 10 | 2 tirs par unité, budget maximum | 1,540 s | 10 Mio |
| 500 | 1 | 4 tirs + chances supplémentaires | 0,279 s | 6 Mio |

Un essai à 5 000 unités et nombreux tirs a atteint la borne de sécurité externe
à 55 secondes, dès un round. Ce scénario n’est pas accepté par l’interface.
Les résultats montrent pourquoi la limite brute de 5 000 du moteur ne suffit
pas pour exposer librement le calcul. Occupations, camps multiples,
interventions non saisies, voyage, butin et reconstruction restent hors
simulation ; le formulaire l’annonce avant confirmation.

La recherche utilise SQL paramétré et `LIKE` avec échappement explicite de `%`,
`_` et `!`. Les résultats/hydratations restent bornés à 50 ; le comptage et la
recherche de sous-chaîne peuvent parcourir l’historique autorisé. Aucun index
plein texte ni dépendance supplémentaire. Le scénario synthétique de régression
porte sur 5 000 messages et 5 000 rapports, jusqu’à la dernière page filtrée.
Un volume bien supérieur en production devra être mesuré avant de promettre
une latence constante.

### Migration et vérifications

`Version20261113220000` crée `fleet_composition`, son index propriétaire et le
jeton unique par propriétaire ; aucun historique de jeu n’est réécrit. Elle a
été appliquée localement après accord, sauvegarde gzip et intégrité vérifiée
sous `var/backups/lot2-pre-migration`. Toute application sur une autre base
existante exige un nouvel accord et une sauvegarde vérifiée.

Validation locale : `make validate`, `make lint`, `make assets` réussis ;
`make test` : 895 tests PHP / 211 348 assertions et 13 tests Python réussis.
Après les dernières protections et les scénarios d’historique volumineux,
40 régressions ciblées / 288 assertions réussies. Sur la machine locale, les deux
pages filtrées de fin d’historique (5 000 messages et 5 000 rapports) ont demandé
0,077 s ; le plan représentatif de sous-chaîne pouvait parcourir les lignes,
ce qui confirme la limite annoncée plutôt qu’une garantie de temps constant.
Les quatre nouveaux scénarios navigateur passent : ordinateur 1 440 px,
mobile 390/320 px, thèmes clair/sombre réellement enregistrés dans les
préférences, contrôles axe ciblés, clavier et fonctionnement sans JavaScript.
Les cinq scénarios existants ciblés de flotte, fret et archives passent aussi.
Les noms de composition de 80 caractères sans espace et les messages longs
(dépliage sans marquage) sont couverts. Les liens des compositions reviennent à
la ligne pour éviter un débordement mobile. Les captures ont été examinées ;
ces vérifications ne constituent pas un audit
RGAA complet. La reprise des anciens instantanés sans rang de forteresse a son
test dédié, réussi avec le contrôle de provenance (2 tests, 9 assertions).

Le résultat de la CI complète sur la dernière version est consigné dans la PR #55.
La fusion reste soumise à sa réussite et à l’accord explicite du propriétaire.
