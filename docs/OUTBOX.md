# Relais en attente et reprise

## Définition et données disponibles

Une ligne de `outbox_message` représente une publication à effectuer. Le relais événements prend les natures `queue`, `fleet`, `combat` et `universe` ; le relais notifications prend `notification`. Chaque passe lit au plus 100 lignes, par identifiant croissant. Une échéance future n’empêche pas la publication : le message est envoyé au transport avec le délai restant. Une ligne dans l’outbox ne signifie donc pas que le traitement métier a échoué, et une outbox vide ne signifie pas que le worker a terminé ses messages.

L’admin affiche les totaux par nature, dont les natures non reconnues dans « Autres », le nombre d’échéances atteintes et au plus 20 lignes classées par échéance puis identifiant. La liste fournit identifiant, nature, cible, échéance UTC et secondes écoulées après échéance. Elle ne montre aucun jeton. Elle se relit au chargement ; les publications concurrentes peuvent modifier la file entre les lectures du relevé.

Le retard affiché est `max(0, instant du relevé − échéance)`. Il ne représente ni l’âge du message ni son temps réel dans le transport. La date de création, le nombre de tentatives et la dernière erreur ne sont pas stockés dans l’outbox actuelle : ces informations restent indisponibles, sans reconstruction fictive. Une collecte échouée est affichée comme inconnue, jamais comme zéro. Aucune migration n’est nécessaire pour ces diagnostics.

## Cycle, interruption et doublons

Le relais verrouille la ligne dans une transaction, publie puis retire la ligne si la transaction réussit. Si la publication échoue, la transaction est annulée et la ligne reste disponible. La commande sort en erreur ; en production, la politique de redémarrage Compose relance un nouveau processus. Ne pas réutiliser le gestionnaire Doctrine fermé après exception.

Une interruption après publication mais avant validation de la transaction peut produire un doublon lors de la reprise. Les messages conservent leur cible et leur jeton ; les gestionnaires métier rejettent les événements périmés ou déjà appliqués. Les notifications utilisent l’identifiant stable `notification-<id>`. Ce mécanisme ne promet pas une livraison unique : le test de reprise vérifie une double livraison de construction sans double application, et ne démontre pas tous les types de traitement.

Les événements de combat sont maintenant routés vers `async`, comme les files, les flottes et la fermeture d’univers. Sans cette route, Messenger appelait le gestionnaire immédiatement et le transport ne gérait pas le délai demandé. La correction conserve la cadence et les règles de combat.

Les échecs du worker sont distincts des erreurs de publication du relais. Messenger configure cinq nouvelles tentatives après la première exécution, avec délai initial de 1 seconde et multiplicateur 2, puis le transport `failed`. Ces tentatives ne constituent pas un historique des publications de l’outbox. Le transport AMQP actuel permet le comptage via `messenger:stats failed`, mais pas la consultation des messages via `messenger:failed:show` : cette commande est refusée par le receiver. La lecture détaillée et la reprise des messages morts nécessitent donc un accès RabbitMQ adapté ou une évolution séparée du transport d’échecs.

## Diagnostic sur un serveur autorisé

1. Actualiser l’admin et comparer échéances atteintes, natures et détails. Un type inconnu reste visible mais n’est pas consommé par les relais actuels.
2. Examiner les signaux avec le [guide des processus](OPERATIONS_SIGNALS.md), puis les journaux de `relay`, `notifier` et `worker` : `docker compose --env-file .env.prod -f compose.prod.yaml logs --tail=100 relay notifier worker`.
3. Examiner le transport d’échecs : `docker compose --env-file .env.prod -f compose.prod.yaml exec -T app php bin/console messenger:stats failed`. Si le transport n’est pas accessible, résoudre la configuration ou la panne avant toute reprise.
4. Distinguer panne du transport, indisponibilité Mercure, erreur du gestionnaire et absence de supervision. Après correction, vérifier la baisse du nombre de publications en attente et le résultat métier attendu. Une ligne disparue prouve sa publication validée, pas à elle seule la réussite du traitement asynchrone.

Aucune ligne n’est effacée depuis l’admin. Les commandes de diagnostic serveur ci-dessus sont une procédure, pas la preuve d’une exécution sur l’Alpha. Aucun redémarrage ni accès aux messages de l’Alpha n’a été effectué dans ce chantier.

## Vérifications locales

Les tests reproduisent une collecte échouée et une nature non reconnue. Une vérification MariaDB couvre la limite de 20 lignes, l’ordre stable et les échéances UTC. Une publication suivie d’une exception est rejouée après redémarrage du noyau de test ; les deux livraisons ne construisent qu’un niveau. Un autre test vérifie qu’un événement de combat passe au transport avec son délai. Le navigateur vérifie la consultation au clavier et aux largeurs 1440, 768 et 390 pixels, sans exposition du jeton.
