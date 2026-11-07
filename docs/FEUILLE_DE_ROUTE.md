# Roadmap Genesis Reborn

Mise à jour documentaire : 7 novembre 2026. [REGLES](REGLES.md) et
[DECISIONS](DECISIONS.md) restent les sources des arbitrages de jeu.

## État connu

L’Alpha est hébergée selon le propriétaire ; son domaine, sa version, son digest
et sa configuration ne sont pas vérifiés par cette consolidation documentaire.
Le jeu comprend comptes, univers/colonies, ressources, recherches, files, flottes,
combat, diplomatie, administration, wiki et compteur de fermeture.

Les anciens audits comptaient 14 phases validées sur 15, la phase d’accessibilité
manuelle restant partielle. Il s’agit de leur verdict historique, conservé avec
[la matrice d’exigences](archives/EXIGENCES_WORK.md), pas d’une validation nouvelle
des 15 phases ni d’une certification de production.

## Priorités restantes

| Priorité | Travail et preuve attendue |
| --- | --- |
| État serveur | Relever domaine, version/digest exécutés, HTTPS et configuration : [déploiement](DEPLOIEMENT.md). |
| Mails de compte | Vérifier SMTP, expéditeur, liens HTTPS et réception réelle. Les tests utilisent un transport nul : [architecture](ARCHITECTURE.md). |
| Processus et alertes | Vérifier les volumes partagés, signaux, relais, destinataire privé et réception ; installer/superviser le job autorisé : [exploitation](EXPLOITATION.md). |
| Sauvegarde et reprise | Planifier, externaliser et protéger les archives ; tester la restauration distante et la reprise du transport RabbitMQ : [sauvegardes](SAUVEGARDES.md). |
| Référencement | Vérifier le vrai domaine, les réponses publiques et Search Console : [SEO](REFERENCEMENT.md). |
| Charge | Mesurer la capacité, la contention des verrous et définir supervision/SLA. Aucun résultat local ne fixe une capacité de production. |
| Accessibilité | Effectuer la revue manuelle avec lecteur d’écran et contrôles RGAA complets ; les tests axe et les contrastes échantillonnés ne la remplacent pas. |
| Équilibrage | Collecter les durées réelles, attentes, gains/pertes et reconstitution des forces. Soumettre toute évolution des paramètres à décision explicite. |

## Limites de la dernière revue manuelle UI

Les notes UI historiques signalaient encore des variantes à compléter : chaînes
de prérequis et lecteur d’écran ; gestes canvas, vues avec flottes et contrastes
complets ; combats/défense/orbite et fin réelle des trajets ; clavier/thème clair
de l’inventaire ; transitions temporelles des événements ; suppression d’univers
en administration et erreur 500 réelle. Les tests ultérieurs ne suffisent pas à
clore automatiquement chacune de ces grilles manuelles. Leur trace datée est dans
[la validation UI](archives/VALIDATION_INTERFACE_2026-11-05.md) et l’historique Git.

## Mesures Alpha

Distinguer nouveaux joueurs et joueurs avancés. Relever jalons de progression,
fréquence de connexion, périodes d’attente, ressources limitantes, pertes et coût
de reconstitution. L’administration expose l’état actuel ; elle ne reconstitue
ni le temps de jeu actif, ni des dates de jalons ou d’anciennes files supprimées.
La couverture et les limites sont dans [EXPLOITATION](EXPLOITATION.md).

## Vérifications de référence

Le lot applicatif 453c414 a validé Compose, lint, 232 tests PHP, 13 tests Python
et compilation Node 24. La suite navigateur a donné 97/98, puis une reprise 3/3
après adaptation du retour vers le nouvel accueil : 98 cas couverts par résultats
consolidés, sans unique exécution complète verte. Ce relevé ne vaut ni validation
de la CI du lot documentaire ni preuve de déploiement de l’Alpha.

La consolidation courante modifie les documents et leurs liens, sans changer
les paramètres de jeu, les sources PHP/JS, les secrets ou la base.
