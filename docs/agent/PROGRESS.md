# Genesis Reborn — avancement

Dernière mise à jour : 31/10/2026 (Europe/Paris)
Branche : feat/genesis-reborn-production-completion
HEAD observé : 80668fa — chore: snapshot Genesis Reborn before completion work

## Position actuelle
- Phase active : 02/15 — consolidation des règles de combat et des missions avancées.
- Phase 01 : PARTIELLE. Les livrables du 21/10 couvrent le socle; la revalidation courante confirme la branche, l'architecture, les tests et les changements préexistants. La revue complète de tous les documents reste à reconfirmer.
- Phase 02 : PARTIELLE/EN COURS. Les critères extraits sont consignés dans docs/agent/PHASE_02_COMBAT_CRITERIA.md; algorithmes non spécifiés et équilibrage restent à décider/documenter.
- Phase suivante : terminer les décisions techniques de la phase 02, puis commencer l'extension des missions de flotte.

## État vérifié aujourd'hui
- Dépôt accessible; branche demandée déjà active.
- Dépôt non propre au départ : changements préexistants dans bin/console, bin/phpunit, scripts/verify-install.sh, huit PDF de docs/technic et dossier .idea/ non suivi. Rien de cela n'a été modifié pendant cette prise en charge. Les modes exécutables de trois scripts sont marqués modifiés; contenus des PDF à préserver.
- Stack observée : Symfony 7.4, Doctrine ORM 3, PHP 8.4 en conteneur, Twig, Stimulus/Encore, MariaDB, RabbitMQ et Mercure.
- Contrôles : make validate réussi; make test réussi — 51 tests / 189 assertions; make lint réussi — PHP, 58 YAML, 14 Twig, conteneur et schéma.
- Aucun déploiement ou publication.

## Fonctionnalités présentes
Comptes et profil; univers Omega/Turbo; carte et découvertes; colonies, ressources, bâtiments, recherches et trois files; reprise chronologique des échéances; missions exploration, colonisation et transport; carburant et estimation serveur; guildes/alliances/relations; notifications; UI refondue avec multi-colonies. Les états et limites détaillés figurent dans docs/LIVRAISON-DOMAINES-UI.md.

## Reste majeur
Combat et rapports, missions d'orbite/défense/espionnage, renforts et butin; conquête et fermeture asynchrone d'univers; classements/statistiques; interface admin; audit sécurité/accessibilité/performance et CI; sauvegarde/restauration vérifiées; validation d'équilibrage.

## État des changements
Ne pas faire de reset, checkout global, clean ou restauration des fichiers listés. Inspecter les diffs avant toute modification de ces chemins. Garder les nouveaux changements sur la branche dédiée et faire des commits cohérents après vérification.
