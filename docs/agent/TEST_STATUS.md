# Genesis Reborn — état des tests

Dernière exécution : 31/10/2026, dépôt /home/caim/Projets/Genesis_Reborn.

| Commande | Résultat |
|---|---|
| make validate | Réussi — configuration Docker Compose valide. |
| make test | Réussi — PHPUnit 13.4.3, PHP 8.4.26, 59 tests / 226 assertions. |
| make lint | Réussi — PHP; 58 YAML; 16 Twig; conteneur Symfony et schéma Doctrine valides. |
| make verify-install | Réussi — installation isolée; 7 migrations appliquées, import de 146 planètes, ré-import idempotent, services prêts; environnement temporaire nettoyé. |
| Playwright | Suite complète 6/6 avant l’ajout de Défense. Après ajout des rapports, test de navigation mobile vers Rapports réussi 1/1. Après ces changements, le scénario navigateur de flotte incluant la sélection Défense a passé 1/1 (3,0 min). |

Les tests PHP utilisent la base database-test isolée (profil test, base en tmpfs). Les parcours sécurité, concurrence, accessibilité et performance restent à vérifier. Les résultats datés du 21/10 dans docs/LIVRAISON-DOMAINES-UI.md sont historiques et ne remplacent pas la validation des changements actuels.
