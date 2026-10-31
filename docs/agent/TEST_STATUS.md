# Genesis Reborn — état des tests

Dernière exécution : après l'ajout du simulateur, 31/10/2026, dépôt /home/caim/Projets/Genesis_Reborn.

| Commande | Résultat |
|---|---|
| make validate | Réussi; configuration Docker Compose valide |
| make test | Réussi — PHPUnit 13.4.3, PHP 8.4.26, 58 tests / 215 assertions |
| make lint | Réussi — lint PHP; 58 YAML; 14 Twig; conteneur Symfony valide; validation du schéma Doctrine sans erreur |

Les tests utilisent database-test (profil test, base en tmpfs), conformément à la protection du projet.

| make verify-install | Réussi — installation isolée; 6 migrations appliquées, import de 146 planètes, ré-import idempotent; environnement temporaire nettoyé. |
| Playwright ciblé | Premier lancement a révélé le contrôleur des flottes lisant `$mission` avant son affectation; corrigé. La relance a révélé un second envoi de sonde sans sonde disponible; le test a été ajusté pour réutiliser le système exploré. Suite complète : 6/6, 2,8 min avant ajout des rapports; test mobile de navigation vers la nouvelle page Rapports : 1/1 après ajustement. |

Les parcours sécurité, concurrence, accessibilité et performance restent à vérifier. Des résultats datés du 21/10 sont décrits dans docs/LIVRAISON-DOMAINES-UI.md; ils ne remplacent pas la validation des changements actuels.
