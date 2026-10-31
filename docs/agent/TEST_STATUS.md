# Genesis Reborn — état des tests

Dernière exécution : après l'ajout du simulateur, 31/10/2026, dépôt /home/caim/Projets/Genesis_Reborn.

| Commande | Résultat |
|---|---|
| make validate | Réussi; configuration Docker Compose valide |
| make test | Réussi — PHPUnit 13.4.3, PHP 8.4.26, 57 tests / 200 assertions |
| make lint | Réussi — lint PHP; 58 YAML; 14 Twig; conteneur Symfony valide; validation du schéma Doctrine sans erreur |

Les tests utilisent database-test (profil test, base en tmpfs), conformément à la protection du projet.

Tests navigateur, installation vierge, migration, sécurité, concurrence, accessibilité et performance ne sont pas revendiqués comme exécutés pendant cette reprise. Des résultats datés du 21/10 sont décrits dans docs/LIVRAISON-DOMAINES-UI.md; les relancer si le code concerné change.
