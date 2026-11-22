# Campagne pirate : admission et recette

## Disponibilité

- Le dossier est accessible aux joueurs vérifiés possédant une participation active dans leur
  univers, en mode applicatif dev ou prod. Le numéro d’une mission ne sélectionne ni le compte,
  ni la participation, ni l’univers.
- La case **Administration → Univers → Gérer → Règles de jeu → Fonctionnalités → Campagne pirate**
  est activée par défaut, y compris pour un univers historique sans ce réglage. Les réglages explicites
  sont conservés. Le nom d’affichage ou le slug de l’univers ne change pas l’admission.
- Décocher cette case et enregistrer bloque les nouveaux ordres et masque l’accès dans Événements.
  Cela ne purge rien : les opérations déjà engagées continuent avec leur preuve durable et le joueur
  retrouve son historique lors d’une réactivation. Ce réglage ne nécessite pas de reconstruire ni
  redéployer l’application après la mise en service de cette version.
- Les Guildes sont également activées par défaut. Un réglage contraire enregistré pour un univers reste prioritaire.
- L’environnement technique Symfony alpha n’enregistre pas ces routes. Si un environnement de
  préproduction est délibérément déployé en APP_ENV=prod avec cette version, ses univers appliquent
  les mêmes valeurs par défaut dans leur propre base. Ne pas confondre le mode Symfony et la cible
  de déploiement.

## Pour l'exploitation lors d'un déploiement décidé par l'opérateur

1. Lancer le processus habituel de validation et de migrations du dépôt avant l’ouverture.
2. Vérifier app, relay et worker Messenger sur la cible voulue. La case ne peut pas compenser un
   service asynchrone absent. Aucun drapeau ni identifiant d’univers supplémentaire n’est requis.
3. Contrôler les données d’un univers via son administration et la présence de la campagne dans
   Événements pour un joueur vérifié. Les routes de dossier sont privées et non mises en cache.
4. En cas de pause, décocher la case pour le seul univers concerné. Ne pas arrêter les workers :
   ils doivent vider les ordres déjà acceptés. Contrôler l’état durable après l’échéance ; n’utiliser
   ni modification manuelle des reçus ni « rattrapage » par une relance générale.

Le catalogue de développement a employé Omega comme témoin et les recettes accélérées modifient
uniquement leur univers et horloge jetables. Aucun trajet de 300 secondes n’est forcé en jeu.
Le cadrage du déroulement est décrit dans [Campagne et Balise 17](CAMPAGNE_PIRATE_BALISE_PILOTE.md).
