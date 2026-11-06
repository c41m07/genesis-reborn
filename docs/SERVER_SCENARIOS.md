# Validation des parcours et de la reprise

## Périmètre

Le propriétaire dispose de l’Alpha hébergée et du local, sans préproduction séparée.
Les essais perturbateurs de cette mission utilisent uniquement les projets Docker
jetables `genesis-browser-test-<timestamp>-<pid>`. Ils ne valident ni la configuration
ni les traitements de l’Alpha. Aucun redémarrage de production n’est autorisé ici.

Les fixtures préparent des comptes et des stocks de test, puis accélèrent les dates
des commandes et événements. Les actions passent par les écrans et les traitements
réels ; ces essais ne mesurent pas la durée normale des opérations ni l’équilibrage.
Les courriels sont neutralisés (`MAILER_DSN=null://null`) et les alertes externes
désactivées. La notification de jeu est distincte du courriel.

## Scénarios automatisés

| Cas | Scénario et preuve attendue |
| --- | --- |
| Construction pendant la déconnexion | `offline-progression.spec.mjs` : laboratoire lancé, tous les contextes navigateur fermés, niveau 1 et file vide vérifiés directement en base, puis contrôle après nouvelle connexion. |
| Recherche pendant la déconnexion | Même scénario : Génie civil colonial lancé après le laboratoire, fermeture de tous les contextes, niveau 1 et file vide en base avant reconnexion. |
| Départ et retour de flotte | `colony.spec.mjs`, « Chantier, estimation, colonisation, transport et exploration réels » : unités construites, missions réelles avec dates accélérées, arrivée, état returning puis completed, colonie et découverte contrôlés. Ce cas conserve un client connecté ; il ne compare pas intégralement les stocks avant/après. |
| Combat | `conquest-report.spec.mjs` : flotte envoyée sur une planète PNJ, arrivée, résolution du combat accélérée, rapport réel et contrôle de confidentialité. Ce cas conserve un client connecté. |
| Notification | `colony.spec.mjs`, « Construction réelle, notification privée et actualisation sans rechargement » : réception par le client ; `offline-progression.spec.mjs` : notification persistée sans client connecté. |
| Reprise des processus | `offline-progression.spec.mjs` : worker, relais et notificateur arrêtés dans le seul projet généré ; construction reprise depuis l’outbox. Recherche publiée avec le worker arrêté, relais/notificateur redémarrés, puis worker relancé : progression et notification attendues une seule fois au niveau 1. |

Commandes depuis une installation locale préparée :

```sh
make test-browser BROWSER_ARGS="offline-progression"
BROWSER_PERSISTENCE=1 make test-browser BROWSER_ARGS="offline-progression"
make test-browser BROWSER_ARGS="colony conquest-report"
```

Le lanceur refuse un nom de projet ayant déjà des conteneurs actifs, prépare une base isolée et nettoie uniquement
son projet à la sortie. Les helpers et le scénario de redémarrage contrôlent le
préfixe du projet avant toute écriture ou action Docker. Ne pas lancer directement
ces tests contre l’Alpha. La preuve JSON du scénario hors connexion est jointe aux
résultats Playwright sous `preuves-hors-connexion.json`.

## Limites de la reprise

Le mode habituel couvre l’arrêt et la relance des processus applicatifs avec MariaDB
et RabbitMQ maintenus actifs. Il utilise des systèmes de fichiers temporaires pour
ces deux services.

Le mode `BROWSER_PERSISTENCE=1` ajoute `compose.browser-persistence.yaml` : seuls les
volumes MariaDB et RabbitMQ du projet généré sont persistants et restent supprimés
à la fin du test. Le scénario hors connexion arrête également l’application, la
base, RabbitMQ et Mercure avant chaque reprise. Il vérifie la conservation de la
commande avant de relancer les traitements. La recherche a déjà quitté l’outbox
avant cet arrêt : sa reprise contrôle donc aussi la conservation du message dans
le transport. La CI navigateur sélectionne ce mode.

La coupure brutale du serveur, la restauration du transport et une reprise après
restauration SQL restent à tester séparément. Un arrêt ordonné des conteneurs
locaux ne simule pas ces situations ni le redémarrage de l’hôte. Une
[restauration SQL vérifiée](BACKUPS.md) ne restaure pas les messages RabbitMQ déjà
retirés de l’outbox. Aucune commande générale de reconstruction de ces messages
n’est attestée par cette mission.

Pour un futur essai sur serveur de test : relever la version exécutée, isoler les
comptes et les transports, neutraliser mails/alertes, vérifier les volumes et les
files avant arrêt, puis comparer les identifiants d’opérations, niveaux, stocks,
rapports et notifications après relance. Conserver les journaux et heures UTC.
Cette procédure est préparée, pas exécutée sur l’Alpha.

## Journal de validation G

Premier essai ciblé local : scénario hors connexion réussi (construction,
recherche, notifications et reprise depuis l’outbox, 46,1 secondes).
Deuxième essai ciblé réussi avec message déjà publié (55,2 secondes).
Troisième essai réussi avec volumes persistants et arrêt ordonné complet des
services (2 minutes environ) : commande conservée en base, message conservé dans
le transport, niveaux 1, files vides et exactement une notification par commande.
Validation locale finale : les 92 scénarios navigateur réussissent en deux lots
complets (48 en 22,5 minutes et 44 en 23,8 minutes), sans reprise ciblée. Les six
cas de la matrice sont exécutés. Configuration, lint, compilation, 225 tests PHP
(205 599 assertions), 13 tests Python et contrôle du diff réussissent également.
Aucun résultat de CI ou de production n’est déduit de ces vérifications locales.
