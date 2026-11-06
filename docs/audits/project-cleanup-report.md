# Suivi du nettoyage documentaire

## Audit et mise à jour du README

Audit initial du 6 novembre 2026 : comparaison avec les manifestes Composer/npm, le Makefile, Compose et les documents de référence. Le README distingue insuffisamment les contraintes déclarées et les versions exécutées ; sa refonte complète et la vérification de tous ses liens restent à réaliser au fil de la mission. Le propriétaire confirme que l’Alpha est hébergée ; les détails de cet hébergement restent à vérifier (chantier I).

Chantier A : ajout de la description de la confirmation d’adresse, de ses variables de configuration et d’un lien vers `docs/ACCOUNT_EMAIL.md`. Aucune section supprimée. La règle de maintenance du README est ajoutée à `AGENTS.md`. Les commandes de contrôle et leurs résultats sont suivis dans [le suivi Alpha](alpha-operations-follow-up.md). Il ne s’agit pas encore de la validation finale du README sur `dev`.

Les 12 liens locaux du README et le lien local de ce rapport ont été vérifiés : tous leurs fichiers existent. Les contrôles Makefile, la compilation et les tests ont été exécutés dans un environnement local isolé, avec résultats consolidés pour les navigateurs. Les limites serveur et CI restent distinctes de ces preuves.

Chantier B : ajout de la durée et de l’usage unique du lien de réinitialisation, de l’invalidation lors d’une nouvelle demande et du renvoi vers la documentation partagée des mails. Aucune commande, dépendance ou section existante supprimée. Aucun résultat de réception en production annoncé.

Après cet ajout, les 13 liens locaux du README ont été vérifiés sans fichier manquant. Les résultats du chantier B sont consignés dans le suivi Alpha : 208 tests PHP réussis sur une exécution complète et 88 scénarios navigateur validés avec reprises ciblées.

Chantier C : ajout de l’observation des signaux des processus, du partage du runtime en production et de la fréquence de lecture dans l’admin, avec renvoi au nouveau guide de diagnostic. Aucune section supprimée ; l’accès au serveur et son état effectif restent distingués de la configuration versionnée.

Chantier D : ajout du lien vers le diagnostic des relais et des limites des données. La collecte indisponible n’est plus présentée comme une file vide. Aucun paramètre d’équilibrage, commande d’installation ou dépendance changé. La documentation distingue retard après échéance, âge inconnu et tentatives du worker.

Les 15 liens locaux du README existent. Le guide des relais, l’index et la ROADMAP ont aussi leurs liens vérifiés. Contrôles D : 215 tests PHP sur une suite complète, 89 scénarios navigateur validés après reprises ciblées ; les limites de consultation des échecs AMQP et l’absence de vérification des messages réels de l’Alpha sont documentées.

Chantier E : README complété avec les alertes par courriel, leurs seuils, leur état désactivé et le guide central de configuration. Ajout de la commande `make ops-check` et des variables `OPS_ALERT_*`, sans destination personnelle ni secret versionné. La procédure serveur et la construction de production restant à vérifier sont distinguées des preuves locales.

Vérification E : 16 liens locaux du README, 34 de l’index, 8 de la ROADMAP et 2 du guide d’alertes existent. Commande du Makefile et syntaxe du script vérifiées ; simulation et arrêt réel d’un worker de test validés. La suite PHP finale et les deux lots navigateur complets réussissent, sans validation de réception ou d’activation sur le serveur.

Chantier F : README complété avec les garanties et limites des sauvegardes, la rétention par défaut, les prérequis des scripts et les commandes backup-status/test-backup. Le test Python est maintenant inclus dans make test et une restauration isolée est prévue dans le lot PHP de la CI. Le guide actif remplace la référence de déploiement à un runbook historique sans réécrire ce dernier.

Vérification F : 17 liens du README, 35 de l’index, 1 du guide de sauvegarde, 3 du guide d’alertes, 2 du déploiement et 9 de la ROADMAP existent. Les nouveaux caches Python et les archives générées sont exclus de Git et du contexte Docker. Les scripts sont validés par tests unitaires et une restauration effective sur deux projets isolés ; ce résultat n’est pas présenté comme un PRA de production.

Chantier G : ajout au README d’un lien vers la matrice active des scénarios serveur. Les preuves locales de progression sans client et de reprise des processus sont séparées des procédures préparées et des validations de l’Alpha restant à exécuter. Aucune commande, dépendance ou règle de jeu modifiée.

Vérification G : 18 liens du README, 36 de l’index, 10 de la ROADMAP et 1 du guide des scénarios existent. Le mode de test à volumes persistants est exécuté localement, avec nettoyage de ses propres volumes vérifié. Les 92 scénarios réussissent en deux lots complets sans reprise ciblée ; les résultats hébergés restent distincts.
