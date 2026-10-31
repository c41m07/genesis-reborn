# Genesis Reborn — avancement

Dernière mise à jour : 31/10/2026 (UTC)
Branche : feat/genesis-reborn-production-completion
HEAD de départ : 80668fa — chore: snapshot Genesis Reborn before completion work; un commit de suivi documentaire a été créé depuis, vérifier git log

## Position actuelle
- Phase active : 02/15 — première tranche opérationnelle du combat persistant; les missions avancées restent incomplètes.
- Phase 01 : PARTIELLE. Les livrables du 21/10 couvrent le socle; la revalidation courante confirme la branche, l'architecture, les tests et les changements préexistants. La revue complète de tous les documents reste à reconfirmer.
- Phase 02 : PARTIELLE. Simulation déterministe, événements persistés, journal de combat, résolution différée et mission Attaque implémentés. Orbite, défense en mission, renforts, butin, rapports joueurs, espionnage et conquête restent à traiter. Décisions : docs/agent/PHASE_02_COMBAT_CRITERIA.md et PHASE_02_DECISIONS.md.
- Phase 09 : première version de résolution persistante livrée; couverture fonctionnelle incomplète, voir la liste ci-dessus.
- Phase suivante : renforcer les parcours de combat (rapports, tests multi-joueurs, défense et renforts), puis reprendre les autres phases du plan.

## État vérifié aujourd'hui
- Dépôt accessible; branche demandée déjà active.
- Dépôt initial : branche de travail déjà active et dossier .idea/ non suivi; les changements faits pendant cette reprise sont limités aux chemins listés dans le statut Git courant. Les captures docs/validation sont produites par les tests navigateur et restent des artefacts locaux.
- Stack observée : Symfony 7.4, Doctrine ORM 3, PHP 8.4 en conteneur, Twig, Stimulus/Encore, MariaDB, RabbitMQ et Mercure.
- Contrôles : make validate réussi; make test réussi — 58 tests / 212 assertions; make lint réussi — PHP, 58 YAML, 14 Twig, conteneur et schéma. make verify-install réussi sur installation isolée. Suite navigateur complète réussie : 6/6 parcours, voir docs/agent/TEST_STATUS.md.
- Aucun déploiement ou publication.

## Fonctionnalités présentes
Comptes et profil; univers Omega/Turbo; carte et découvertes; colonies, ressources, bâtiments, recherches et trois files; reprise chronologique des échéances; missions exploration, colonisation et transport; carburant et estimation serveur; guildes/alliances/relations; notifications; UI refondue avec multi-colonies. Les états et limites détaillés figurent dans docs/LIVRAISON-DOMAINES-UI.md.

## Reste majeur
Combat et rapports, missions d'orbite/défense/espionnage, renforts et butin; conquête et fermeture asynchrone d'univers; classements/statistiques; interface admin; audit sécurité/accessibilité/performance et CI; sauvegarde/restauration vérifiées; validation d'équilibrage.

## État des changements
Ne pas faire de reset, checkout global, clean ou restauration des fichiers listés. Inspecter les diffs avant toute modification de ces chemins. Garder les nouveaux changements sur la branche dédiée et faire des commits cohérents après vérification.
