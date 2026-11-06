# Documentation Genesis Reborn

Cette page oriente vers les documents de référence. Les notes d’audit de phase conservent les preuves détaillées; pour l’état courant, commencer par l’état Alpha et l’audit final, pas par un ancien compte rendu daté.

## Règles et décisions de jeu

- [Règles de base](REGLES.md) : fonctionnement retenu et règles confirmées.
- [Décisions](DECISIONS.md) : arbitrages utilisateur et adaptations aux spécifications.
- [Rééquilibrage progression et combat](agent/BALANCING_IMPLEMENTATION_2026-11-02.md) : décision de balance intégrée; le [QCM](agent/BALANCING_QCM_2026-11-02.md) distingue les objectifs encore à mesurer.
- [Audit des recherches maximisées](audits/audit-equilibrage-recherches-max-2026-11-02.md) : simulation historique, sans statut de décision.
- [Spécifications textuelles](specifications/) et [PDF techniques](technic/).

Les objectifs de durée début/milieu/fin, les gains et pertes et les profils PNJ sont à mesurer pendant l’Alpha. La cadence de combat confirmée pour cette phase est un round par minute, jusqu’à dix rounds.

## État et exploitation

- [Déploiement GitHub Actions / GHCR](DEPLOIEMENT.md) : image autonome et déploiement serveur par SSH, à valider sur l'infrastructure cible.
- [État Alpha](agent/ALPHA_READINESS.md) : critères, preuves récentes et limites des tests.
- [Audit final](agent/PHASE_15_FINAL_AUDIT.md) : verdict production et critères encore ouverts.
- [Roadmap](ROADMAP.md) et [plan maître](agent/MASTER_PLAN.md).
- [Matrice des exigences](agent/REQUIREMENTS_MATRIX.md) et [couverture des écrans](agent/UI_SCREEN_MATRIX.md).
- [Runbook d’exploitation](agent/RUNBOOK_OPERATIONS.md).

## Détails techniques et historique utile

- [Vérification finale des contres du 4 novembre](audits/contres-verification-finale-2026-11-04.md) : contrôle exhaustif des types, rangs, recherches, tirs et relations affichées.

- [Calcul des tirs secondaires du 4 novembre](audits/tirs-secondaires-2026-11-04.md) et [matrice CSV des 504 confrontations](audits/tirs-secondaires-2026-11-04.csv) : bonus +30, contres sur les dégâts et les chances de toucher, admissibilité des cibles et écarts de rang.

- [Revue des fiches du catalogue du 4 novembre](audits/catalogue-fiches-2026-11-04.md) : contenu des 150 variantes, calcul des valeurs affichées et contrôles de la refonte.

- [Résultats de validation](agent/TEST_STATUS.md) : dernier état des suites et commandes.
- [Audits des phases](agent/) : architecture, comptes, économie, missions, combat, administration, interface et exploitation.
- [Passes UI consolidées](agent/PHASE_12_PASS_HISTORY.md) : relevé des ajustements et preuves UI du 1er novembre.
- [Inventaire des assets](agent/ASSET_MANIFEST.md) et [références UI](agent/UI_REFERENCE_GAP_REVIEW.md).
- [Recherche UI](UI-RECHERCHE.md), [navigation multi-colonies](UI-MULTI-COLONIES.md) et [GalaxieV2](agent/GALAXY_V2.md) : descriptions de fonctionnalités et vérifications historiques.
- [Prompts d’illustration](ARTWORK_PROMPTS.md) et [prompts de génération d’assets](agent/ASSET_GENERATION_PROMPTS.md) : conservés pour une recréation ultérieure.
- Les captures raster de validation ont été retirées le 02/11/2026; les rapports historiques indiquent quand leurs preuves visuelles ne sont plus disponibles.

Les rapports conservés dans ce dépôt sont des preuves ou des spécifications de référence. Les notes de session et bilans remplacés ne constituent pas une source de règles.

- [Déblocages initiaux permanents — 6 novembre 2026](INITIAL_UNLOCKS_2026-11-06.md) : 50 éléments, prérequis par rang, raisons et compatibilité.
