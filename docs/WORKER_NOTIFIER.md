# Fonctionnement du worker et du notifier

Genesis Reborn utilise une chaîne asynchrone pour exécuter les échéances du jeu sans navigateur ouvert, puis informer les joueurs connectés. Elle repose sur trois processus PHP distincts : `relay`, `worker` et `notifier`, avec MariaDB comme stockage métier, RabbitMQ comme transport de tâches et Mercure comme serveur de diffusion SSE.

Ce guide s’adresse à un développeur confirmé. Il décrit l’implémentation de la branche `dev`, au commit `186008a` consulté le 11 novembre 2026, incluant la reconstruction des empires oubliés. Les exemples de commandes visent le développement local, sauf mention explicite de la production. Les règles de jeu restent définies dans [REGLES.md](REGLES.md) et [DECISIONS.md](DECISIONS.md).

## Vue d’ensemble et responsabilités

```text
Requête HTTP ou traitement métier
    |
    +-- transaction MariaDB : modification métier + outbox_message
                                  |
                  +---------------+----------------+
                  |                                |
       kind = queue/fleet/combat/         kind = notification
       universe/occupation/reconstruction          |
                  |                            notifier
                relay                              |
                  |                         publication privée
       Messenger -> RabbitMQ                    Mercure
                  |                                |
                worker                        SSE / EventSource
                  |                                |
        gestionnaire -> service              navigateur connecté
                  |
       transaction métier pouvant
       créer une nouvelle notification
```

| Élément | Responsabilité | Entrée et sortie |
| --- | --- | --- |
| `app` | Requêtes HTTP, validation des actions, rendu et autorisation des abonnements | Navigateur → services métier et MariaDB |
| `relay` | Publier les échéances persistées vers Messenger | Outbox métier → transport `async` |
| `worker` | Consommer `async` et exécuter les gestionnaires métier | RabbitMQ → services métier et MariaDB |
| `notifier` | Publier les notifications persistées vers Mercure | Outbox de notifications → hub Mercure |
| RabbitMQ | Transport AMQP des traitements différés et des messages en échec définitif | Messenger ↔ broker |
| Mercure | Distribuer les mises à jour privées aux abonnés autorisés | Publication HTTP → flux SSE |

Le service Docker `notifier` utilise une commande applicative de relais. Il ne consomme pas RabbitMQ et n’utilise pas ici le composant Symfony Notifier. Les courriels de compte et les alertes d’exploitation, malgré la classe `Operations\AlertNotifier`, suivent des circuits distincts.

La séparation protège le circuit métier d’une indisponibilité du hub : `relay` et `worker` peuvent poursuivre les événements du jeu pendant que les publications de notifications restent en attente. MariaDB reste une dépendance commune.

## Les processus et leur cycle de vie

Les commandes sont définies dans [docker/compose.yaml](../docker/compose.yaml) et [docker/compose.prod.yaml](../docker/compose.prod.yaml).

```bash
# Processus worker
php bin/console messenger:consume async --time-limit=3600 --memory-limit=192M --no-interaction

# Processus relay : --kind=events est implicite
php bin/console app:outbox:relay --loop

# Processus notifier
php bin/console app:outbox:relay --kind=notifications --loop
```

Ces lignes décrivent les processus déjà supervisés par Compose ; les lancer manuellement sur une installation active ajouterait des consommateurs ou des relais.

`OutboxRelayCommand` accepte uniquement `events` et `notifications`. Sans `--loop`, elle effectue une seule passe. Avec `--loop`, elle attend une seconde après chaque passe : la période réelle inclut le temps de traitement, ce n’est pas une cadence garantie d’une passe par seconde.

En mode `events`, la commande appelle d’abord `BoostEventService::tick()`, puis `DailyLeaderboard::tick()`, avant de vider le lot d’outbox. Le relais participe donc aussi au cycle des événements de boosts et à l’actualisation quotidienne des classements. Une exception dans ces appels interrompt la passe avant la publication des échéances. Le mode `notifications` n’appelle aucun de ces deux services.

Après une passe réussie, même vide, la commande écrit le heartbeat correspondant. Une exception est affichée sous la forme `Relais <kind> interrompu : <message>` et termine la commande avec le code 1. Une valeur `--kind` invalide donne le code 2 ; une exécution normale donne 0.

Les trois services utilisent `restart: unless-stopped`. La sortie du worker à ses limites de temps ou de mémoire permet son renouvellement par Docker ; ces options Messenger ne sont pas des délais d’exécution maximum pour chaque tâche. Les relais n’ont pas de limite équivalente dans leur commande.

## Modèle de données et outbox transactionnelle

### Les tables

| Entité | Champ | Sens |
| --- | --- | --- |
| `OutboxMessage` | `id` | Identifiant auto-incrémenté de la publication en attente |
| | `kind` | Nature du message, chaîne limitée à 30 caractères |
| | `targetId` | Identifiant de l’objet métier ou de la notification |
| | `token` | Jeton de génération ou identifiant stable, limité à 36 caractères |
| | `dueAt` | Échéance métier ; convertie en délai pour les événements |
| `Notification` | `id` | Identifiant auto-incrémenté, également utilisé pour la déduplication |
| | `recipient` | Relation obligatoire vers `PlayerUniverse`, la participation du joueur à un univers |
| | `message` | Texte limité à 240 caractères |
| | `createdAt` | Instant de création fourni par `ClockInterface` |

L’outbox stocke une référence vers la cible, pas un JSON métier complet. Elle ne comporte ni statut de livraison, ni compteur de tentatives, ni date de création, ni dernière erreur. Sa suppression marque la validation de la publication, pas la fin du traitement ni la lecture par le joueur.

### Création d’une notification

`NotificationService::enqueue(PlayerUniverse $recipient, string $message, ?string $journalKind = null): void` :

1. Crée et persiste une `Notification` avec l’heure courante.
2. Appelle `flush()` pour obtenir son identifiant.
3. Persiste un `OutboxMessage` de nature `notification`, avec `targetId = notification.id`, `token = notification-<id>` et `dueAt = maintenant`.

Ce service ne démarre pas sa propre transaction et ne fait pas de second `flush()`. Son contrat suppose un appel au sein de la transaction métier englobante : `flush()` envoie les écritures SQL, tandis que le commit de cette transaction valide ensemble l’action et son intention de notification. Un nouvel appel hors transaction doit prendre explicitement en charge cette atomicité et la persistance finale de l’outbox.

`GameManager::transaction()` délègue à `EntityManager::wrapInTransaction()`. Le verrouillage utilise `PESSIMISTIC_WRITE`, suivi d’un `refresh()` de l’entité.

L’outbox évite de coupler directement le commit métier à un appel réseau vers Mercure ou RabbitMQ. Elle ne fournit pas de transaction distribuée entre MariaDB et ces services.

## Publication des événements par relay

`GameRepository::outbox(false)` sélectionne au maximum 100 lignes, par `id` croissant, parmi les six natures métier prises en charge. La sélection ne filtre pas les échéances futures.

| Nature | Message Messenger | Gestionnaire et service appelé |
| --- | --- | --- |
| `queue` | `QueueItemFinished(queueItemId, dispatchToken)` | `QueueItemFinishedHandler` → `QueueService::complete()` |
| `fleet` | `FleetArrived(fleetId, dispatchToken)` | `FleetArrivedHandler` → `FleetService::arrive()` |
| `combat` | `CombatEventDue(combatEventId, dispatchToken)` | `CombatEventDueHandler` → `CombatEventService::resolve()` |
| `occupation` | `OccupationDue(occupationId, dispatchToken)` | `OccupationDueHandler` → `OccupationCaptureService::capture()` ; reprogrammation via `OccupationScheduler` si nécessaire et circuit activé |
| `reconstruction` | `EmpireReconstructionDue(reconstructionId, dispatchToken)` | `EmpireReconstructionDueHandler` → `EmpireReconstructionService::rebuild()` |
| `universe` | `UniverseClosureDue(universeId, scheduleToken)` | `UniverseClosureDueHandler` → `UniverseLifecycleService::closeIfDue()` |

Pour chaque ligne, `OutboxRelay::run(false)` :

1. Ouvre une transaction et verrouille puis recharge la ligne.
2. Construit le message à partir de `kind`, `targetId` et `token`.
3. Calcule `delayMs = max(0, (dueAt.timestamp - now.timestamp) * 1000)`.
4. Appelle `MessageBusInterface::dispatch()` avec un `DelayStamp`.
5. Supprime la ligne d’outbox, puis valide la transaction.

Les six messages sont explicitement routés vers `async` dans [messenger.yaml](../app/config/packages/messenger.yaml). Avec le DSN AMQP de l’installation, le transport RabbitMQ porte le délai. Le calcul utilise des timestamps à la seconde ; une échéance passée produit un délai nul.

Exemple : une échéance située 60 secondes après l’horloge du relais est publiée immédiatement au transport avec `DelayStamp(60000)`. Elle n’attend pas 60 secondes dans MariaDB. Une outbox vide peut donc coexister avec de nombreux messages encore différés dans RabbitMQ.

Dans le bridge AMQP installé, ces délais passent par des queues durables de délai, avec `x-message-ttl`, puis un routage par dead-letter exchange vers la destination finale. Ce mécanisme n’exige pas de plugin RabbitMQ de messages différés. Pour les reprises, le bridge utilise un routage adapté à la queue d’origine. Les détails d’implémentation sont dans `Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection`, fourni par `symfony/amqp-messenger`.

Après le lot, `EntityManager::clear()` détache les entités. Il ne constitue ni une remise à zéro complète du conteneur de services, ni une garantie de mémoire constante pour un processus illimité.

## Exécution par worker et cohérence métier

Le worker consomme uniquement `async`. Les gestionnaires sont de petits adaptateurs marqués `#[AsMessageHandler]` ; ils délèguent les contrôles et mutations aux services métier.

L’identifiant cible ne suffit pas à autoriser une exécution. Les services vérifient aussi l’état courant et le jeton de génération. Par exemple, `QueueService::complete()` recherche la commande, verrouille le contexte métier, vérifie la tête de file, le démarrage et `hash_equals(dispatchToken, token)`, puis réconcilie les travaux réellement arrivés à échéance. Une commande déjà supprimée ou un ancien jeton entraîne un retour sans nouvelle application.

Les flottes et les combats contrôlent également leur état, leur jeton et leur échéance ; la fermeture d’univers vérifie son jeton de programmation. Le détail dépend du service : les gestionnaires ne partagent pas une table générique des messages déjà consommés.

La reconstruction autonome suit la même chaîne `reconstruction` → `async` → worker. `EmpireReconstructionScheduler::scheduleLocked()` renouvelle le jeton, conserve l’échéance et crée la ligne d’outbox. `EmpireReconstructionService::rebuild()` vérifie l’activation de `FORGOTTEN_EMPIRES_ENABLED`, verrouille la participation, l’univers, la planète et l’état de reconstruction, puis contrôle jeton, échéance, propriété, statut d’empire oublié et absence de combat en attente. Selon l’état, il reprend ou crée une commande de chantier, reprogramme un contrôle, ou termine sans mutation utile. La fabrication effective utilise ensuite le circuit `queue` existant. Une ancienne génération reste identifiable par son jeton périmé.

Cette distinction compte lorsqu’un boost reprogramme une action : l’ancien message peut rester dans RabbitMQ, mais son ancien jeton ne doit plus déclencher l’opération. Les verrous et validations métier sont la protection contre les doubles effets ; une livraison unique du broker n’est pas supposée.

Messenger configure cinq nouvelles tentatives après la première exécution, avec délai initial de 1 000 ms et multiplicateur 2. Pour une exception ordinaire, les délais de base sont 1, 2, 4, 8 et 16 secondes, avec le jitter par défaut de 10 % du framework installé. La charge du système peut ajouter de l’attente ; ce ne sont pas des garanties de temps réel. Après épuisement, Messenger utilise le transport `failed`, configuré avec l’exchange et la queue `genesis_v2_failed`.

Ces reprises concernent les erreurs d’exécution des messages consommés par le worker. Elles ne concernent pas les publications directes du notifier vers Mercure.

Les services de jeu disposent aussi de chemins de réconciliation, notamment `QueueService::synchronize()`. Une action peut donc être finalisée lors d’un accès métier avant la consommation de son message ; les contrôles du gestionnaire doivent accepter cette situation sans répéter l’effet.

## Publication par notifier

`GameRepository::outbox(true)` prend les 100 premières lignes `kind = notification`, par identifiant croissant. Contrairement au relais métier, cette branche n’utilise pas `dueAt` pour différer l’envoi : elle publie les lignes sélectionnées immédiatement.

Pour chaque ligne, dans sa propre transaction :

1. Verrouiller et recharger l’`OutboxMessage`.
2. Charger la `Notification` référencée par `targetId`.
3. Si elle existe, publier sur le topic de son destinataire avec le payload `{"message": "…", "id": 123, "journal": true}` et l’identifiant SSE `notification-123`.
4. Supprimer la ligne d’outbox et valider la transaction.

Une cible `Notification` absente entraîne la suppression de la ligne d’outbox sans publication. Une notification publiée reste en base ; seul son ordre de publication est supprimé. Ce passage ne définit aucune politique de rétention des notifications.

`MercurePublisher` encode le payload avec `JSON_THROW_ON_ERROR` et construit :

```php
new Update($topic, json_encode($data, JSON_THROW_ON_ERROR), true, $id)
```

Le troisième argument indique une mise à jour privée. L’identifiant SSE est construit depuis la notification, indépendamment du champ `token` de l’outbox dans cette branche.

Le notifier traite les lignes séquentiellement et garde le verrou SQL pendant la publication réseau. Un hub lent augmente donc le temps de transaction et le retard des notifications suivantes. Une exception arrête la passe : une notification qui échoue systématiquement peut bloquer celles qui suivent à chaque redémarrage. Il n’existe ici ni quarantaine, ni backoff applicatif, ni nombre maximal de tentatives par notification.

## Topics privés et autorisation Mercure

`NotificationService::topic()` renvoie actuellement :

```text
https://genesis-reborn.localhost/players/<playerUniverseId>/events
```

L’identifiant est celui de `PlayerUniverse`, pas celui du compte `Player`. Le nom d’hôte est codé en dur dans cette méthode : c’est un identifiant de topic, pas l’adresse réseau utilisée pour contacter le hub. Publication et abonnement construisent la même chaîne, y compris en production. Changer cette convention exige de coordonner producteurs, abonnements et autorisations.

Le navigateur appelle `GET /notifications/abonnement`. Cette route est protégée par `ROLE_USER`. `UniverseContext::membership()` vérifie la participation courante, son appartenance au compte connecté, son contrôle par un joueur et l’accessibilité de l’univers. Aucun identifiant arbitraire de destinataire n’est accepté par la route.

Le contrôleur :

- Crée le cookie d’autorisation Mercure pour ce seul topic, avec expiration à 15 minutes.
- Renvoie une URL relative `/.well-known/mercure?topic=...`, construite par `http_build_query()`.
- Renvoie `Cache-Control: private, no-store`.

Traefik route `/.well-known/mercure` vers le hub sur le même domaine public. Les credentials sont transmis par `EventSource` via `withCredentials: true`. La configuration du hub utilise la clé `MERCURE_JWT_SECRET` pour la publication et l’abonnement ; la configuration Symfony autorise le producteur à publier sur `*`, tandis que le cookie du joueur limite son abonnement au topic calculé.

L’autorisation est émise pour 15 minutes ; elle n’équivaut pas à une révocation immédiate des cookies déjà émis lors d’un changement d’état du compte. Le cycle décrit ne comporte pas de mécanisme spécifique de révocation instantanée côté hub.

## Réception et actualisation côté navigateur

Le template instancie le contrôleur Stimulus `events` uniquement si une participation est disponible et si la préférence `realtimeNotifications` est active. Désactiver cette préférence retire l’abonnement temps réel de l’interface ; cela n’arrête ni les processus métier, ni la création des notifications.

Dans [events_controller.js](../app/assets/controllers/events_controller.js) :

1. `connect()` initialise un `Set` de déduplication et demande l’URL d’abonnement.
2. Le contrôleur vérifie la réponse HTTP et son type JSON, puis ouvre `EventSource`.
3. Toutes les 600 000 ms, soit 10 minutes, il redemande l’autorisation et recrée le flux, avant l’expiration nominale du cookie.
4. À réception, il décode le JSON et ignore un `data.id` déjà présent dans le `Set`.
5. Il insère le texte avec `textContent`, affiche le bandeau et émet `genesis:refresh` avec `{notificationId: data.id}`.
6. `disconnect()` ferme la connexion et supprime le timer. Le bouton « Actualiser » recharge la page.

Le flux est du SSE, unidirectionnel du serveur vers le navigateur. Les actions du joueur continuent à passer par HTTP. Le client ne consomme pas RabbitMQ.

L’événement `genesis:refresh` déclenche les contrôleurs présents sur la page : actualisation de régions HTML via `colony_live`, témoins du menu, compteurs de messagerie ou d’administration. `colony_live` regroupe les demandes avec un délai de 200 ms, évite les formulaires marqués modifiés et diffère le remplacement d’une région qui contient le focus. Le payload SSE sert ainsi surtout de signal d’invalidation ; les vues métier sont relues auprès de l’application.

Les compteurs de messagerie et d’administration disposent également d’un polling toutes les 15 secondes. Leur mise à jour ne constitue donc pas, à elle seule, une preuve du bon fonctionnement de Mercure.

Le bandeau utilise `role="status"` et `aria-live="polite"`. Le texte reçu n’est pas interprété comme du HTML.

Les erreurs de connexion initiale sont absorbées par le `catch` de `open()` sans message visible. Le client s’appuie sur la reconnexion native d’`EventSource` pour les ruptures du flux et sur son renouvellement périodique. Il ne fournit pas de protocole applicatif de rattrapage exhaustif : la nouvelle instance ne reçoit pas explicitement de curseur `Last-Event-ID` depuis l’ancienne.

Le `Set` de déduplication est local à l’instance du contrôleur : il disparaît lors d’une reconnexion Stimulus ou d’un rechargement, ne se partage pas entre onglets et grandit pendant la vie de cette instance. L’identifiant SSE stable ne garantit pas à lui seul l’absence de réaffichage.

`GameShell` relit les cinq dernières notifications de la participation pour le rendu. La requête actuelle charge les notifications correspondantes, puis applique `array_slice()` en PHP ; ce n’est pas une pagination SQL. Le témoin de lecture du journal repose sur un identifiant conservé en `localStorage` par participation, sans accusé de lecture serveur dans l’entité `Notification`.

## Exemple complet de construction

Les identifiants ci-dessous sont illustratifs ; aucune durée d’équilibrage n’est définie par cet exemple.

1. Le joueur démarre une construction. `QueueService` persiste la commande, son `dispatchToken` et une ligne d’outbox `queue` visant cette commande, avec son échéance.
2. `relay` publie `QueueItemFinished(id, token)` vers `async` avec le délai restant, puis retire la ligne d’outbox après validation de la transaction.
3. À disponibilité du message, `worker` appelle `QueueItemFinishedHandler`, puis `QueueService::complete()`.
4. Le service vérifie les verrous, le jeton et les échéances, applique la progression et programme la suite éventuelle. Il appelle `NotificationService::enqueue()` dans la transaction de finalisation.
5. Cette transaction valide le résultat et la notification, par exemple `Notification.id = 123`, avec sa ligne d’outbox `notification`.
6. `notifier` publie `{"message":"Terminé : … sur ….","id":123}` vers le topic du joueur, sous l’identifiant `notification-123`, puis retire cette ligne d’outbox.
7. Mercure transmet la mise à jour aux navigateurs abonnés et autorisés. Le contrôleur affiche le texte et déclenche la relecture des régions concernées.

La construction n’attend ni navigateur, ni accusé de réception SSE. La finalisation métier et l’affichage sont deux étapes distinctes.

## Garanties et fenêtres de panne

| Situation | Comportement et limite |
| --- | --- |
| Rollback de l’action métier englobante | Les écritures métier, la notification et son intention de publication sont annulées ensemble si le contrat transactionnel est respecté. |
| RabbitMQ ou Mercure refuse la publication | La transaction de cette ligne est annulée ; elle reste en outbox. La commande sort en erreur et Compose peut relancer le processus. Les lignes validées auparavant restent traitées. |
| Interruption après acceptation réseau, avant commit SQL | Le message peut être publié une seconde fois à la reprise. Il n’existe pas d’atomicité distribuée ni de garantie « exactly once ». |
| Échec du gestionnaire après publication dans RabbitMQ | Les reprises Messenger s’appliquent ; l’outbox d’origine est déjà supprimée. Les effets SQL dépendent des transactions du service appelé. |
| Hub accepte la publication, joueur déconnecté | La publication est validée côté application ; cela ne prouve ni réception navigateur, ni lecture. Le rendu peut relire les notifications conservées, sans garantie de rejouer tous les bandeaux. |
| Nature d’outbox inconnue | Les sélections des relais l’ignorent ; l’administration l’affiche parmi les autres natures. |
| Processus vivant mais traitement bloqué | Le heartbeat peut vieillir ; son âge doit être corrélé aux journaux et à l’état Docker. |

Le modèle accepte la possibilité de livraisons répétées et prévoit des protections métier ou une déduplication côté client. Il ne promet pas une livraison garantie à chaque navigateur. Une publication retirée de l’outbox n’est plus suivie individuellement par l’application.

La sélection du lot précède le verrou de chaque ligne ; il n’y a ni réservation atomique du lot, ni `SKIP LOCKED`, ni répartition dédiée entre instances. L’exploitation actuelle suit une instance de chaque service. Augmenter le nombre de relais demande une analyse et des tests de concurrence : le verrou seul ne constitue pas une preuve de fonctionnement multi-instance.

## Configuration et déploiement

| Variable ou stockage | Rôle |
| --- | --- |
| `DATABASE_URL` | Base métier, notifications et outbox |
| `MESSENGER_TRANSPORT_DSN` | Transport `async` et `failed` ; le modèle local utilise AMQP, vhost `/` encodé `%2f`, exchange `genesis_v2` pour `async` ; `failed` surcharge sa topologie |
| `RABBITMQ_PASSWORD` | Initialisation du mot de passe RabbitMQ côté Compose ; doit être cohérent avec le DSN |
| `MERCURE_URL` | Endpoint de publication interne ; exemple local `http://mercure/.well-known/mercure` |
| `MERCURE_PUBLIC_URL` | URL publique déclarée au bundle ; la route d’abonnement actuelle construit toutefois sa propre URL relative |
| `MERCURE_JWT_SECRET` | Secret de signature à garder hors des documents partagés et des logs |
| `MERCURE_JWT_ISSUER` | Claim `iss` du producteur dans la configuration Symfony |
| `APP_DOMAIN` | Routage public et origine CORS du hub en production |
| `process_runtime` | Volume de heartbeats partagé en production, en lecture seule pour `app` |
| `database_data`, `rabbitmq_data` | Volumes persistants de la base et du broker |
| `mercure_data`, `mercure_config` | Volumes du hub ; leur présence ne prouve pas un rattrapage applicatif exhaustif |

En local, les services partagent le code monté depuis `app/` et utilisent l’UID/GID configuré. En production, ils utilisent la même image applicative immuable et les processus PHP tournent sous `www-data`. `worker` et `relay` attendent une base et un RabbitMQ sains. `notifier` attend une base saine et Mercure démarré (`service_started`), sans attendre que le healthcheck du hub réussisse.

Après un changement de code chargé par ces processus persistants locaux, le README prescrit leur redémarrage. Une modification d’image, de variables injectées ou de montages exige une recréation adaptée : `restart` ne recharge pas une nouvelle définition de conteneur. Voir [DEPLOIEMENT.md](DEPLOIEMENT.md) pour la procédure serveur.

## Diagnostic et commandes

Depuis la racine du dépôt, commencer par les cibles Make disponibles :

```bash
make validate
make status
make console CMD='debug:messenger'
make console CMD='messenger:stats async failed'
```

`debug:messenger` vérifie l’enregistrement des messages et gestionnaires. Les statistiques des transports ne remplacent pas l’inspection de la topologie RabbitMQ, notamment pour les messages différés. Le transport AMQP d’échecs permet le comptage, mais n’implémente pas la consultation nécessaire à `messenger:failed:show` ; ne pas supposer que les commandes standard de consultation et de reprise sont utilisables telles quelles. Le diagnostic détaillé et une éventuelle reprise nécessitent l’accès adapté au broker ou une évolution du transport.

Pour des journaux bornés, sans suivre indéfiniment toute la stack :

```bash
docker compose --env-file .env.local logs --tail=100 relay worker notifier mercure
```

En production autorisée, préciser les fichiers du déploiement :

```bash
docker compose --env-file .env.prod -f compose.prod.yaml ps
docker compose --env-file .env.prod -f compose.prod.yaml logs --tail=100 relay worker notifier mercure
```

Les healthchecks recherchent `var/run/{worker,relay,notifier}.heartbeat` et une date de modification de moins de 45 secondes. Ils sont évalués toutes les 10 secondes, avec timeout de 5 secondes, période de démarrage de 10 secondes et 6 échecs consécutifs avant l’état unhealthy. Le worker écrit son heartbeat sur `WorkerRunningEvent` ; les relais l’écrivent après une passe réussie. Un heartbeat récent indique une activité récente, pas la réussite de toutes les tâches. Un état unhealthy ne provoque pas à lui seul un redémarrage avec la politique Compose utilisée ; la sortie du processus déclenche le mécanisme de reprise.

L’administration expose les signaux, les compteurs d’outbox et les 20 premières échéances, avec retard `max(0, maintenant - dueAt)`. Les lignes `occupation` et `reconstruction` sont consommées par `relay`, mais le collecteur d’administration les regroupe actuellement dans « Autres ». Ce classement visuel ne signifie pas qu’elles sont inconnues du relais.

Pour une investigation SQL en lecture seule, sur la base explicitement visée :

```sql
SELECT kind, COUNT(*) AS pending, MIN(due_at) AS first_deadline
FROM outbox_message
GROUP BY kind;

SELECT id, kind, target_id, due_at
FROM outbox_message
ORDER BY due_at, id
LIMIT 20;
```

Ces requêtes ne mesurent pas les messages déjà dans RabbitMQ, ni l’âge de création d’une ligne. Ne pas supprimer l’outbox pour faire disparaître une alerte : cela supprime une intention de publication.

| Symptôme | Points à vérifier |
| --- | --- |
| Constructions ou flottes en retard | Heartbeats `relay` et `worker`, erreurs des ticks du relais, RabbitMQ, routes Messenger, transport `failed`, puis état et jeton de la cible |
| Jeu à jour mais aucun bandeau | Préférence temps réel, `notifier`, Mercure, cookie d’autorisation, topic et route proxy |
| Outbox `notification` qui grossit | Première publication en erreur, connectivité interne vers le hub, clé JWT et droits de publication |
| Outbox vide mais traitement attendu absent | Messages différés ou en attente dans RabbitMQ, échecs définitifs, message périmé ou action déjà réconciliée |
| SSE refusé ou interrompu | Réponse de `/notifications/abonnement`, cookie, expiration, origine CORS, routage `/.well-known/mercure` et journaux du hub |
| Heartbeat sain dans le processus mais absent dans l’admin | Partage et permissions du volume `process_runtime`, puis horloge des conteneurs |

Dans les outils réseau du navigateur, vérifier l’appel JSON d’abonnement puis la réponse `text/event-stream` du hub. Ne pas transmettre le cookie JWT ou les secrets pour partager un diagnostic.

Les seuils d’alerte, l’anti-répétition et le canal courriel sont détaillés dans [EXPLOITATION.md](EXPLOITATION.md). Ils sont distincts du seuil de santé de 45 secondes : le profil d’alerte prévoit notamment 120 secondes pour le signal ancien et 300 secondes pour le retard d’outbox.

## Tests existants et limites de leur portée

| Fichier | Scénario couvert |
| --- | --- |
| [GamePersistenceTest.php](../app/tests/Integration/GamePersistenceTest.php) | Panne Mercure qui conserve l’outbox de notification sans bloquer la publication métier |
| Même fichier | Événement de combat publié avec `DelayStamp(60000)` plutôt qu’exécuté immédiatement |
| Même fichier | Interruption après publication, redémarrage du contexte Doctrine, nouvelle livraison et application unique d’une construction |
| Même fichier | Ancien jeton ignoré après reprogrammation d’une tête de file par boost |
| [GameplayAccessTest.php](../app/tests/Controller/GameplayAccessTest.php) | Cookie d’abonnement limité à la participation courante |
| [PreferencesTest.php](../app/tests/Controller/PreferencesTest.php) | Persistance de la préférence des alertes temps réel |
| [AdminOperationsServiceTest.php](../app/tests/Unit/AdminOperationsServiceTest.php) | Lecture des signaux et diagnostics d’exploitation |
| [OperationsAlertsTest.php](../app/tests/Unit/OperationsAlertsTest.php) | Évaluation des incidents et comportement des alertes |

En environnement `test`, `async` et `failed` sont des transports `in-memory://`. Ces tests prouvent les contrats applicatifs concernés ; ils ne prouvent pas à eux seuls les délais réels RabbitMQ, une livraison SSE jusqu’à un navigateur distant, la reprise multi-instance ou la configuration de production. Le scénario de double livraison d’une construction ne démontre pas l’idempotence de tous les traitements.

## Parcours de lecture du code

1. [OutboxRelayCommand.php](../app/src/Command/OutboxRelayCommand.php) : boucle, modes, ticks, erreurs et heartbeats.
2. [OutboxRelay.php](../app/src/Service/Notification/OutboxRelay.php) et [GameRepository.php](../app/src/Repository/GameRepository.php), méthode `outbox()` : sélection, transactions et publication.
3. [GameManager.php](../app/src/Manager/GameManager.php) : transaction, verrouillage et détachement.
4. [messenger.yaml](../app/config/packages/messenger.yaml), [Message](../app/src/Message) et [MessageHandler](../app/src/MessageHandler) : contrats et routage.
5. [QueueService.php](../app/src/Service/Queue/QueueService.php) : exemple métier de programmation, contrôle du jeton, réconciliation et notification.
6. [NotificationService.php](../app/src/Service/Notification/NotificationService.php), [Notification.php](../app/src/Entity/Notification.php) et [OutboxMessage.php](../app/src/Entity/OutboxMessage.php) : stockage et topic.
7. [MercurePublisher.php](../app/src/Integration/Mercure/MercurePublisher.php), [mercure.yaml](../app/config/packages/mercure.yaml) et [NotificationController.php](../app/src/Controller/NotificationController.php) : publication privée et abonnement autorisé.
8. [events_controller.js](../app/assets/controllers/events_controller.js), [colony_live_controller.js](../app/assets/controllers/colony_live_controller.js) et [base.html.twig](../app/templates/base.html.twig) : réception, affichage et invalidation des vues.
9. [ProcessHeartbeat.php](../app/src/Service/ProcessHeartbeat.php), [WorkerHeartbeatListener.php](../app/src/EventListener/WorkerHeartbeatListener.php) et [AdminOperationsService.php](../app/src/Service/Admin/AdminOperationsService.php) : observation des processus.

### Indication du journal d’absence

Le champ booléen journal du payload Mercure indique si la notification appartient
au suivi d’absence déjà commencé par son destinataire. Le toast instantané reste
affiché pour toute notification ; ce booléen sert seulement au témoin du journal.
Une publication n’ouvre pas la vue générale et ne marque aucun événement comme
consulté. Les garanties de transaction, outbox et déduplication restent inchangées.
Après migration et mise à jour du code, redémarrer worker, relay et notifier pour
recharger les modèles et le service de notification. Le lot C a été migré seulement
sur la base locale autorisée, après sauvegarde vérifiée ; l’Alpha n’est pas déployée.
