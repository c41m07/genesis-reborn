# Bâtiments — cadence A et centrale ×2

> Historique : la répartition des ressources et les délais de progression de ce bilan sont remplacés, lorsqu’ils diffèrent, par la [grille commune avec hydrogène T3](COMMON_COSTS_2026-11-05.md). Les moteurs, courbes et statistiques restent conservés.

La décision utilisateur valide une cadence de remboursement d’environ 2–3 semaines pour une amélioration de mine de métal T1 vers le niveau 80 en univers normal, puis la centrale ×2 avec intérêt à développer ses rangs T2/T3. Les paramètres ci-dessous sont intégrés pour les tests Alpha.

## Paramètres appliqués

| Paramètre | T1 | T2 | T3 |
|---|---:|---:|---:|
| Production des producteurs | ×1 | ×1,5 | ×2,25 |
| Consommation électrique et intrants industriels | ×1 | ×1,5 | ×2,25 |
| Durée de construction, facteur de rang | ×1 | ×1,25 | ×1,5625 |
| Stockage ajouté par niveau | ×1 | ×1,25 | ×1,5625 |

La centrale produit **0,2 électricité/s/niveau basique**, soit 720/h, contre 360/h auparavant. Ses niveaux T2 et T3 apportent respectivement 1 080 et 1 620/h en univers normal à la position de référence. Le facteur propre à la production est configuré dans `building_production_tier_factors` ; il ne remplace pas le facteur partagé des coûts, durées, recherches, vitesses ou stockages.

Les productions de base métal/nano/hydrogène et antimatière restent conservées. Le réacteur consomme toujours 8 H par A produite. Les bonus et plafonds des laboratoires, chantiers et bunkers restent conservés.

## Courbe des coûts des producteurs

| Niveau atteint | Multiplicateur par rapport au niveau précédent |
|---|---:|
| 2 à 20 | ×1,12 |
| 21 à 40 | ×1,09 |
| 41 à 80 | ×1,035 |
| 81 à 120 | ×1,015 |
| Après 120 | ×1,01 |

Cette courbe, `production_building_cost`, concerne uniquement les bâtiments ayant une ressource de production : métal, nano, hydrogène, antimatière et électricité. Les profils de coûts par rang et les arrondis supérieurs de chaque ressource restent utilisés. Les prix du premier niveau et ceux jusqu’au niveau 40 ne sont pas changés par la nouvelle courbe. Les réductions de recherche s’appliquent normalement.

Les stockages, laboratoires, chantiers et bunkers conservent la courbe `building_cost`. Les recherches et leurs courbes propres restent conservées. Les temps de construction ne sont pas abaissés avec les prix.

## Usines et stockage plein

Une usine de métal, nano ou antimatière dont la sortie ne peut plus être stockée suspend sa production et ses consommations. Le calcul s’arrête au moment d’un remplissage ou d’un épuisement, puis recalcule les activités pour le temps restant.

Un stockage d’hydrogène plein ne coupe pas l’approvisionnement immédiat du réacteur : la raffinerie fournit le débit utile restant après la production gratuite de base. Sans besoin en aval, elle cesse sa consommation électrique. La centrale continue à alimenter les usines quand le stockage électrique est plein. Les débits affichés n’ajoutent pas les excédents qui ne peuvent pas être stockés.

Les petits résidus numériques proches de zéro ou d’un plafond sont normalisés à une tolérance de 10⁻⁷ unité pour éviter d’abandonner une période de production. Si le calcul ne progresse pas ou ne peut pas intégrer toute la période dans sa borne de segments, il signale explicitement l’échec au lieu de retourner un résultat incomplet. Le rendement de secours électrique reste de 10 % et ne permet pas de fabriquer de l’antimatière sans hydrogène.

## Résultats de référence

Mine de métal T1 vers le niveau 80, position 5, univers normal, sans réduction de coût par recherche :

- Prix proposé de la mine : **152 890 métal**, soit 305 780 unités économiques.
- Gain nominal : **360 métal/h**, soit 720 unités économiques/h.
- Remboursement nominal de la mine seule après livraison : **17,70 jours**.
- En allouant la capacité électrique ajoutée dans le plan de référence utilisant les centrales T1/T2/T3 : **18,22 jours**.
- Pour la même amélioration en T3, la méthode avec allocation électrique donne **14,80 jours**.

L’allocation électrique est un outil de comparaison : le joueur achète des niveaux entiers de centrale. Les prérequis, les stockages et les délais de financement ne sont pas tous imputés au seul dernier niveau. Une colonie conservant uniquement des centrales T1 n’a pas le même rendement : la progression des rangs énergétiques est utile. Les positions, les pénuries, le stockage saturé et les usages de ressources peuvent changer les résultats. Ces délais ne sont pas des garanties pour toutes les situations.

Une trajectoire financée depuis les stocks initiaux, sans bâtiment ni recherche, atteint les trois mines métal/nano/hydrogène au niveau 80 en **25,11 jours en normal**, ou **15,07 h en Alpha ×40**, avec développement énergétique T2/T3. Elle utilise une planète en position 5, un réacteur niveau 1, aucun raid ni échange, et lance immédiatement chaque action possible, une par une. Ce n’est pas une stratégie optimale ni une promesse de temps de progression pour un joueur réel. Le délai d’accès au niveau est distinct du remboursement de sa dernière amélioration.

## Vérifications de l’intégration

- Le fichier `tests/Fixtures/building-balance-a.json` fige les références calculées hors dépôt avant modification : **450 mesures nominales, 64 simulations et 18 contrôles historiques de coûts**, avec empreintes de provenance.
- Les tests comparent les vrais coûts, durées, gains, consommations, stocks et débits du code intégré à ces références. Ils contrôlent aussi l’égalité entre calcul continu et pas horaires, les surcharges d’univers, les stockages pleins et les consommations utiles en aval.
- Les **quatre trajectoires centrale ×2** ont été rejouées avec le code réellement intégré, en pas horaires : **1 260 achats** retrouvent les coûts, prérequis, délais et stocks du candidat, avec un écart maximal de stock inférieur à 2 × 10⁻¹⁰ unité.
- Le bloc des gains et la fiche détaillée utilisent les nouveaux facteurs et affichent leurs consommations nominales. Un test contrôle notamment les 1 620 électricité/h du niveau T3 de centrale et les 6 480 H/h d’un niveau T3 de réacteur.
- `make validate` réussi. Syntaxe PHP, JSON/YAML, Twig, injection et mapping Doctrine réussis dans `make lint`. La dernière étape reste en échec sur le schéma de base désynchronisé ; aucune migration exécutée.
- `make test` : **180 tests, 14 échecs et 6 erreurs restantes**, dont des problèmes déjà observés sur les boosts, prérequis, recherches et espionnage. La branche globale n’est pas entièrement verte. Les tests ciblés de ce changement passent.
- Le test d’intégration du réservoir sans fret utilise désormais une Sonde, dont la soute reste nulle, plutôt qu’un Chasseur doté de la soute validée précédemment.

## Données existantes et limites

Les prix et durées des constructions déjà payées restent enregistrés ; il n’y a ni remboursement automatique ni réécriture des files. Les surcharges de catalogue par univers restent prioritaires et peuvent masquer les nouvelles valeurs de base. Aucune migration ni remise à zéro de sauvegarde n’est effectuée.

Les productions sont recalculées avec les règles chargées au moment de la lecture ou de la matérialisation. Il n’existe pas de version historique des règles de production : lors d’un déploiement sur un univers actif, la période écoulée depuis sa dernière matérialisation peut être recalculée avec les nouveaux paramètres. La transition d’un univers actif doit être organisée séparément ; elle n’est pas automatisée ici.

Les simulations ne valident pas encore le métajeu complet : plusieurs planètes, rythmes de connexion humains, files prépayées, pillage, coûts militaires, équilibre des pénuries et durée de vie réelle des univers restent à mesurer en Alpha.

## Fichiers de ce changement

- Paramètres : `config/game/rules.yaml`, `config/game/buildings/power_plant.yaml` (format JSON conservé).
- Calculs et présentation : `src/Service/Catalog/GameCatalog.php`, `src/Service/Colony/ResourceService.php`, `src/Service/Colony/FactoryEconomy.php`, `src/Service/Colony/CatalogueDetails.php`.
- Tests : `tests/Service/BuildingBalanceTest.php`, `tests/Fixtures/building-balance-a.json`, `tests/Service/FactoryEconomyTest.php`, `tests/Service/CatalogueDetailsTest.php`, `tests/Integration/CatalogBalanceTest.php`, `tests/Integration/GamePersistenceTest.php`.
- Documentation : `docs/DECISIONS.md`, `docs/REGLES.md`, le présent document.

Les profils de ressources des vaisseaux ont ensuite été modifiés par décision distincte, à budget économique avant recherche conservé : voir [SHIP_COSTS_2026-11-05.md](SHIP_COSTS_2026-11-05.md). Les références historiques des tests restent figées ; leurs assertions de prix naval utilisent le nouveau fichier de référence.
