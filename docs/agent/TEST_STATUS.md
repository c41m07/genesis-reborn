# Genesis Reborn — état des tests

Dernière exécution : 31/10/2026, dépôt /home/caim/Projets/Genesis_Reborn.

| Commande | Résultat |
|---|---|
| make validate | Réussi — configuration Docker Compose valide. |
| make test | Réussi — PHPUnit 13.4.3, PHP 8.4.26, 63 tests / 299 assertions. |
| make lint | Réussi — PHP; 59 YAML; 16 Twig; conteneur Symfony et schéma Doctrine valides. |
| make verify-install | Réussi — installation isolée; 10 migrations appliquées, import de 146 planètes, ré-import idempotent, services prêts après redémarrage; environnement temporaire nettoyé. |
| Playwright | Suite complète 6/6 avant les changements récents. Après correction du champ de durée d’orbite résiduel, scénario navigateur complet de flotte (chantier, estimation, colonisation, transport et exploration) réussi 1/1 en 2,9 min; parcours responsive jusqu’à Rapports de combat réussi 1/1 après ajout du butin. La conquête JcE est couverte par deux tests d’intégration serveur (capture et persistance des pertes PNJ); pas encore de parcours navigateur dédié. |

Les tests PHP utilisent la base database-test isolée (profil test, base en tmpfs). Les parcours sécurité, concurrence, accessibilité et performance restent à vérifier. Les résultats datés du 21/10 dans docs/LIVRAISON-DOMAINES-UI.md sont historiques et ne remplacent pas la validation des changements actuels.
