# Alertes d’exploitation par courriel

## Fonctionnement et seuils retenus

Le canal courriel et le profil modéré ont été choisis par le propriétaire. La destination réelle est une configuration privée, jamais une valeur par défaut publiée. Aucun envoi n’est activé par défaut. La commande `app:operations:check` collecte les données de l’application et reçoit le relevé Docker de l’hôte ; aucun accès au socket Docker n’est ajouté à l’application.

| Condition | Déclenchement |
|---|---|
| Processus arrêté ou en pause | Dès le relevé Docker `exited`, `dead` ou `paused`. |
| Signal ancien | Dès que le dernier signal lisible atteint 120 secondes. |
| Signal absent ou redémarrage en cours | Après 120 secondes d’observation continue dans l’état persistant. |
| Relais en retard | Plus ancienne échéance dépassée d’au moins 300 secondes. |
| Échec définitif | Compteur du transport `failed` supérieur à zéro, au prochain relevé. |
| Collecte indisponible | Alerte de diagnostic distincte ; un état inconnu ne devient pas un rétablissement. |

Un signal récent ne prouve pas que tous les traitements réussissent. Une entrée absente du relevé Docker ne prouve pas un arrêt : elle reste inconnue. La commande suit worker, relay et notifier, chacun à une seule instance ; un relevé avec plusieurs instances du même service est refusé plutôt que résumé à tort comme sain.

Le transport d’échecs actuel expose un compteur agrégé, pas la liste des identifiants : les alertes ne sont pas individuelles par message. Elles ne comptent pas les cinq nouvelles tentatives du worker comme des échecs définitifs. Le retard de l’outbox mesure une publication en attente, pas le temps restant dans RabbitMQ ; voir [le guide des relais](OUTBOX.md).

## Anti-répétition et rétablissement

Un incident connu déclenche un courriel, puis au plus un rappel toutes les 3 600 secondes pour ce même incident. La hausse d’un compteur dans cet intervalle ne produit pas une rafale de mails. Le passage à un état connu et normal déclenche un courriel de rétablissement, puis aucun autre tant que l’état reste normal. Un état inconnu conserve l’incident précédent sans annoncer de rétablissement.

L’état est conservé dans `var/operations/alerts.json`, avec verrou exclusif et remplacement atomique. Le volume Compose `operations_state` le conserve après recréation de l’application. Un mail refusé par le transport n’est pas marqué livré ; les mails précédents déjà acceptés restent mémorisés. Une interruption entre acceptation du mail et écriture de l’état peut néanmoins occasionner un doublon. Un état corrompu provoque une erreur explicite, sans remise à zéro automatique ni rafale d’envois. La simulation n’écrit pas cet état.

## Configuration privée

| Variable | Valeur par défaut | Rôle |
|---|---|---|
| `OPS_ALERT_ENABLED` | `0` | Autorise les envois uniquement si `--send` est également fourni. |
| `OPS_ALERT_TO` | vide | Adresse destinataire choisie par le propriétaire. |
| `OPS_ALERT_SIGNAL_SECONDS` | `120` | Seuil du signal ancien et observation minimale d’un signal absent. |
| `OPS_ALERT_RELAY_SECONDS` | `300` | Retard de publication à signaler. |
| `OPS_ALERT_REMINDER_SECONDS` | `3600` | Intervalle minimal entre rappels d’un même incident. |

Les seuils doivent être strictement positifs. Les mails réutilisent `MAILER_DSN`, `MAILER_FROM` et `DEFAULT_URI`. Configurer ces valeurs dans `.env.prod`, non versionné. Avant activation, vérifier le transport et l’expéditeur sur l’environnement cible ; aucun fournisseur ni résultat de réception n’est supposé ici. Ne pas utiliser le transport `null://null` pour une surveillance réellement activée : il accepte les mails sans les livrer.

## Exécution depuis l’hôte et automatisation

Depuis le dossier du déploiement, `make ops-check` lance une simulation. Le script `scripts/operations-check.sh` appelle `docker compose ps --all --format json`, puis transmet ce relevé à la commande dans app, en tant que `www-data`. Il ne redémarre aucun processus et ne modifie ni l’outbox ni les messages du transport. Le format JSON en tableau et le format JSON par ligne sont acceptés.

`make ops-check OPS_ARGS=--send` n’envoie que si `OPS_ALERT_ENABLED=1` et `OPS_ALERT_TO` est renseigné. Le compte exécutant le script doit pouvoir interroger Docker et exécuter la commande dans app. Les chemins du fichier d’environnement et du Compose peuvent être changés avec `OPS_ENV_FILE` et `OPS_COMPOSE_FILE` ; le projet Compose doit être celui du déploiement, éventuellement fixé par `COMPOSE_PROJECT_NAME`.

Lors d’un déploiement autorisé, installer le script côté hôte et recréer app pour ajouter le volume d’état ; un simple redémarrage ne change pas les montages. L’image prépare le dossier avec les droits de `www-data`. Les changements des variables du fichier env_file, y compris l’activation, nécessitent aussi la recréation du conteneur pour être pris en compte. Ne pas supprimer le volume d’état lors des mises à jour. Le job n’est pas installé automatiquement sur le serveur dans ce chantier.

Exemple de cron à adapter aux chemins réels et aux permissions du serveur, une fois la simulation vérifiée :

```cron
* * * * * cd /chemin/du/projet && bash scripts/operations-check.sh --send >> /chemin/prive/operations.log 2>&1
```

Le relevé est donc périodique, pas instantané. Les retours JSON indiquent le mode simulation/envoi, le nombre de mails acceptés et les conditions. Code de sortie : 0 sans incident, 1 si une condition d’alerte est présente (y compris en délai d’observation), 2 si la surveillance ne peut pas fonctionner. Les erreurs d’amorçage de l’application ou de Docker peuvent également renvoyer un autre code non nul sans relevé JSON. Superviser aussi le job et consulter son journal : si Docker, app, le serveur ou le transport mail est indisponible, ce circuit peut ne pas envoyer. Une surveillance extérieure indépendante reste nécessaire pour ces indisponibilités globales.

## Diagnostic et preuves

Comparer les [signaux des processus](OPERATIONS_SIGNALS.md), les échéances de l’outbox, les journaux du worker et `messenger:stats failed`. Une erreur de lecture ou de configuration n’est jamais assimilée à une file vide. En cas de fichier d’état invalide ou de droits insuffisants, préserver le fichier et diagnostiquer avant toute réinitialisation. En cas de panne mail, corriger le transport ; la prochaine exécution retentera les incidents non acceptés.

Les tests couvrent les frontières de seuil, le délai d’observation, les rappels, la persistance après nouvelle instance, les rétablissements, les états inconnus, la simulation, une panne mail, un état corrompu et le verrou de concurrence. Un test navigateur arrête et redémarre uniquement le worker de son projet Compose éphémère, avec des signaux préparés côté collecteur car les tmpfs de test sont distincts. Les envois y sont forcés à désactivé et le mail à `null://null`.

Une image de vérification dérivée du runtime déjà installé valide le dossier d’état sous `www-data`, sa persistance entre conteneurs, la déduplication puis le rétablissement (1, 0, 1 mails acceptés par le transport null). Ce test n’est pas une livraison de courriels. La construction complète du Dockerfile de production hors réseau n’a pas abouti, faute de paquets APT en cache ; cette construction complète reste à vérifier dans un environnement autorisé disposant des dépendances. Aucun test ni activation de ces alertes sur l’Alpha n’a été réalisé.
