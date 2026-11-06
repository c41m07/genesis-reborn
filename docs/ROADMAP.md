# Roadmap Genesis Reborn

Mise à jour : 2 novembre 2026. Pour les règles et arbitrages de gameplay, se reporter à [REGLES.md](REGLES.md) et [DECISIONS.md](DECISIONS.md).

## État général

Le jeu est au stade **Alpha jouable contrôlée**, pas en production. Les quinze domaines de travail ont été audités; le statut strict consigné dans l’audit final est 14 phases validées sur 15, la phase 12 restant partielle pour l’accessibilité manuelle exhaustive.

La priorité de l’Alpha est d’observer les joueurs et de mesurer leur progression réelle. L’orientation confirmée est une stratégie modérée. Les cibles de durée début/milieu/fin, les gains et pertes et les profils PNJ seront comparés aux sessions et ajustés ensuite; aucun objectif de durée n’est déclaré atteint d’avance.

## Phases auditées

| Phase | Domaine | État actuel |
|---|---|---|
| 01–05 | Socle, règles, architecture, comptes, univers | Fonctionnalités principales et audits livrés |
| 06–08 | Économie, bâtiments/recherches/boosts, flottes et missions | Implémentés et testés; valeurs de balance à évaluer pendant l’Alpha |
| 09 | Combat | Implémenté : rounds persistés, un round par minute, jusqu’à dix rounds; équilibre à mesurer |
| 10–11 | Social, stratégie et administration | Diplomatie, messages, classement, réglages et modération livrés |
| 12 | Interface et accessibilité | Refonte et parcours livrés; revue manuelle RGAA/WCAG avec lecteur d’écran reste ouverte |
| 13–14 | Tests, sécurité, performance et exploitation | Contrôles locaux et procédures livrés; CI distante et charge restent non prouvées |
| 15 | Audit final | Réalisé; verdict NON PRÊT production et limites documentées |

## Collecte et itération Alpha

Pendant les sessions Alpha, relever notamment le temps pour atteindre les premiers jalons, la fréquence de connexion nécessaire, les périodes d’attente, les ressources excédentaires ou limitantes, les pertes de flotte, le coût de reconstitution et la capacité à revenir après un revers. Ventiler les observations par nouveaux joueurs et joueurs avancés. Les objectifs chiffrés seront établis à partir de ces résultats avant de modifier les valeurs de production.

Les valeurs en configuration sont l’état initial du test. Leur présence ne signifie pas qu’elles sont validées comme équilibre final.

## Portes avant production

- Compléter une feuille de balance à partir des résultats Alpha et valider les changements associés.
- Exécuter la CI sur l’hébergeur distant retenu.
  La publication GHCR après validation et le déploiement SSH manuel sont configurés ; leur exécution distante reste à vérifier (voir [déploiement](DEPLOIEMENT.md)).
- Mesurer la capacité sous charge et définir supervision/SLA.
- Compléter les sauvegardes distantes, leur protection et le plan de reprise.
- Réaliser la revue manuelle d’accessibilité avec lecteur d’écran et cadrer la conformité RGAA/WCAG.

Les critères, preuves et limites sont suivis dans [BLOCKERS.md](agent/BLOCKERS.md), [REQUIREMENTS_MATRIX.md](agent/REQUIREMENTS_MATRIX.md) et [PHASE_15_FINAL_AUDIT.md](agent/PHASE_15_FINAL_AUDIT.md).

## Suivi des relais

Les échéances et retards des publications en attente sont consultables dans l’admin, avec collecte indisponible distinguée d’une file vide. Les tests locaux de reprise et de double livraison sont décrits dans [le guide des relais](OUTBOX.md). Les tentatives et erreurs de publication ne sont pas historisées ; les constats sur les données réelles de l’Alpha restent à établir sur le serveur.

## Alertes d’exploitation

Le canal courriel et les seuils modérés sont retenus. La surveillance préparée est désactivée par défaut ; son activation, son automatisation et la réception doivent être vérifiées sur le serveur. Voir [le guide des alertes](OPERATIONS_ALERTS.md).

## Sauvegardes vérifiées localement

Les scripts proposent une conservation configurable, un suivi des échecs et un contrôle relié aux alertes. La restauration de comptes et de progression a été testée sur des instances jetables distinctes. Planification, stockage distant et reprise après perte de RabbitMQ ne sont pas déclarés validés sur l’Alpha ; voir [la procédure active](BACKUPS.md).
