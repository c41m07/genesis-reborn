# Genesis Reborn — état des tests

Dernière exécution : 31/10/2026, dépôt /home/caim/Projets/Genesis_Reborn.

| Commande | Résultat |
|---|---|
| make validate | Réussi — configuration Docker Compose valide. |
| make test | Réussi — PHPUnit 13.4.3, PHP 8.4.26, 67 tests / 335 assertions. |
| make lint | Réussi — PHP; 62 YAML; 17 Twig; conteneur Symfony et schéma Doctrine valides. |
| make verify-install | Réussi — installation isolée; 11 migrations appliquées, import de 146 planètes, ré-import idempotent, services prêts après redémarrage; environnement temporaire nettoyé. |
| Playwright | Suite complète 6/6 avant les changements récents. Après correction du champ de durée d’orbite résiduel, scénario navigateur complet de flotte (chantier, estimation, colonisation, transport et exploration) réussi 1/1 en 2,9 min; parcours responsive jusqu’à Rapports de combat réussi 1/1 après ajout du butin. La conquête JcE est couverte par deux tests d’intégration serveur (capture et persistance des pertes PNJ). Les parcours navigateur n’ont pas été relancés après l’ajout de l’espionnage. |

Les tests PHP utilisent la base database-test isolée (profil test, base en tmpfs). Les parcours sécurité, concurrence, accessibilité et performance restent à vérifier. Les résultats datés du 21/10 dans docs/LIVRAISON-DOMAINES-UI.md sont historiques et ne remplacent pas la validation des changements actuels.


## Mise à jour — espionnage, 31/10/2026

- Migration 11 créée et appliquée sur la base locale.
- make lint relancé après les changements : PHP valide; 62 YAML; 17 Twig; conteneur Symfony et schéma Doctrine valides.
- Les tests PHPUnit incluent maintenant trois parcours d’espionnage : réussite et confidentialité, détection et alerte, initialisation d’une garnison PNJ; le test de pertes PNJ vérifie désormais les unités par slug.


Résultats post-espionnage : `make test` réussi — 67 tests / 335 assertions; `make lint` réussi — 62 YAML, 17 Twig, PHP, conteneur et schéma; `make validate` réussi; `make verify-install` réussi — 11 migrations, 146 planètes, ré-import idempotent et redémarrage sain.
