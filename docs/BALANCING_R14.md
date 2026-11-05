# Équilibrage R14 — branche Alpha, 5 novembre 2026

R14 est adopté explicitement pour les tests Alpha. Il reprend le candidat final de l’étude, sans recalibrage pendant son intégration. Il couvre les neuf classes ci-dessous. Les statistiques et les prix des furtifs, corvettes, unités de soutien et défenses restent conservés ; les nouvelles mécaniques communes les concernent aussi.

## Statistiques effectives au rang basique, sans recherche

| Classe | Structure | Bouclier | Attaque | Tirs | Précision | Tracking | Évasion | Signature | Secondaire : attaque × tirs |
|---|---:|---:|---:|---|---:|---:|---:|---:|---|
| Chasseur | 150 | 50 | 24 | 2–3 | 55 | 75 | 60 | 20 | — |
| Intercepteur | 215 | 40 | 35 | 1–3 | 30 | 85 | 75 | 18 | — |
| Chasseur lourd | 500 | 200 | 26 | 4–4 | 65 | 75 | 50 | 35 | — |
| Bombardier | 632 | 158 | 146 | 1–2 | 65 | 80 | 45 | 40 | — |
| Frégate | 1048 | 286 | 32 | 18–19 | 75 | 80 | 35 | 35 | — |
| Croiseur | 3509 | 1404 | 160 | 7–9 | 100 | 65 | 14 | 50 | 254 × 2 |
| Destroyer | 1600 | 600 | 467 | 2–4 | 100 | 70 | 8 | 45 | 100 × 6 |
| Cuirassé | 5079 | 2540 | 160 | 17–18 | 100 | 65 | 11 | 60 | 224 × 4 |
| Capital | 6349 | 3175 | 160 | 42–43 | 100 | 70 | 8 | 70 | 240 × 8 |

Les secondaires visent la catégorie `light` (chasseur, intercepteur, furtif), avec +50 points de précision avant le coefficient. Le chasseur lourd appartient à une autre catégorie. Pour le bombardier, la base brute d’attaque est 145,5 : l’arrondi supérieur par rang donne 146 / 219 / 328, avant recherche.

Structure, bouclier et dégâts suivent 1 / 1,5 / 2,25. Les autres valeurs de mobilité et les facteurs de coût/durée de rang restent séparés. Aucun tir rapide intrinsèque au rang n’est ajouté : seuls les effets technologiques existants en accordent.

## Valeurs économiques de construction, sans recherche

Valeur = 2 × métal + 4 × nano + 8 × antimatière + hydrogène, calculée sur les coûts réellement arrondis de chaque ressource. Les profils complets sont dans `config/game/ships/*.yaml`, au format JSON. Les recherches de réduction des coûts s’appliquent ensuite normalement.

| Classe | Basique | Intermédiaire | Supérieur |
|---|---:|---:|---:|
| Chasseur | 6000 | 7504 | 9380 |
| Intercepteur | 7352 | 9198 | 11502 |
| Chasseur lourd | 14800 | 18506 | 23140 |
| Bombardier | 21502 | 26886 | 33608 |
| Frégate | 45560 | 56960 | 71198 |
| Croiseur | 121730 | 152180 | 190210 |
| Destroyer | 84222 | 105284 | 131602 |
| Cuirassé | 220502 | 275630 | 344544 |
| Capital | 377552 | 471944 | 589934 |

Un vaisseau survivant endommagé ne perd pas sa valeur de construction. Le carburant, le fret, le butin et les dommages de structure doivent être suivis séparément. Aucun coût de réparation n’est ajouté.

## Coefficients de spécialisation

Le coefficient multiplie les dégâts et la chance de toucher, avec les bornes décrites plus bas. Toutes les autres relations entre les neuf classes, y compris contre elles-mêmes, valent 1. Il n’y a aucune faiblesse réciproque automatique. Les relations historiques vers les classes extérieures sont conservées.

| Tireur | Cibles et coefficients |
|---|---|
| Chasseur | Intercepteur × 2.04, Bombardier × 2.42 |
| Intercepteur | Chasseur lourd × 2.35, Bombardier × 2.52 |
| Chasseur lourd | Destroyer × 4.03, Frégate × 4.44, Chasseur × 4.15 |
| Bombardier | Capital × 4.38, Destroyer × 4.16, Cuirassé × 4.35 |
| Frégate | Intercepteur × 4.5, Bombardier × 4.6, Chasseur × 8 |
| Croiseur | Chasseur lourd × 6, Frégate × 4.59 |
| Destroyer | Croiseur × 5.31, Capital × 5.06, Cuirassé × 4.53 |
| Cuirassé | Croiseur × 4.5, Frégate × 7.3 |
| Capital | Croiseur × 8, Cuirassé × 4.8 |

Le groupe historique `fighters` garde ses trois membres (chasseur, furtif, intercepteur). Les neuf tireurs R14 utilisent désormais des relations explicites, ce qui permet de différencier les rôles de ces membres. La corvette conserve son groupe historique. Cela n’accorde pas de nouveaux rôles au furtif ou à la corvette.

## Mécaniques appliquées

- Chance de base = borne 15–95(précision + tracking + correction de l’arme − évasion − 15). Puis borne 15–95(base × coefficient), puis ajout de 30 points par écart de rang et nouvelle borne 15–95.
- Lors d’un choix de cible, les unités dont la structure ou le bouclier est entamé sont prioritaires. La signature pondère ensuite les cibles admissibles. La cible principale est conservée pendant la salve tant qu’elle survit.
- Puissance de toute la salve = ((structure + bouclier restants)/(structure + bouclier initiaux))², mesurée au début du tour du tireur.
- Bouclier positif : recharge de 5 % du maximum en fin de round. Bouclier épuisé : aucune recharge pendant cette bataille. La restauration après combat reste inchangée.
- Sous 25 % de structure, le prochain tir qui touche teste avant ses dégâts une explosion : probabilité = max(0, 1 − structure/(0,25 × structure initiale)). Exemples : 0 % à 25 % de structure ; 20 % à 20 % ; 60 % à 10 %. Le tir qui fait entrer dans la zone critique ne déclenche pas lui-même cette explosion.
- Les tirs supplémentaires de recherche s’appliquent aux tirs principaux et secondaires, sans récursion. Les excédents de dégâts restent perdus.
- Dix rounds maximum ; élimination possible avant la limite. Si les deux camps subsistent à la limite, le défenseur gagne officiellement. Les états individuels ne sont pas persistés entre batailles.

Une escorte est une partie du budget remplacée par d’autres vaisseaux adaptés. Il n’existe pas de protection automatique des alliés, d’interposition, d’interception de missiles ou de bonus de commandement ajouté par R14.

## Vérification et limites

Le fichier `tests/Fixtures/combat-r14.json` conserve les entrées figées et les empreintes des résultats complets de 192 combats calculés avec le simulateur de référence de l’étude : six compositions, quatre graines distinctes, deux positions, deux profils de recherche et deux couples de rangs. Ces 192 combats ne représentent donc pas 192 graines indépendantes. Les tests vérifient aussi les coûts réels et les statistiques de neuf classes × trois rangs × trois profils de recherche, ainsi que les 81 coefficients. Les graines de cette vérification servent à contrôler la transposition du moteur, pas à certifier un nouvel équilibre.

L’étude économique préalable portait notamment sur 439 configurations à demi-budget ; son minimum de rendement moyen observé était de 1,4153 valeur adverse détruite par valeur propre détruite, pour un filtre proposé de 1,25. Ce résultat moyen ne garantit pas chaque bataille, notamment dans les petits combats. Le métajeu, les configurations non échantillonnées, les furtifs, corvettes et défenses restent à mesurer en Alpha.

Les surcharges de catalogue propres à chaque univers sont conservées : elles peuvent remplacer les prix et statistiques R14. Les constructions déjà payées conservent leurs coûts enregistrés. Les combats déjà commencés gardent leurs instantanés de statistiques et coefficients, mais ne possèdent pas de version du moteur : terminer les batailles actives avant de déployer cette version pour éviter de recalculer leurs anciens rounds avec les nouvelles mécaniques.

L’intégration n’effectue aucune migration, réinitialisation d’univers ni modification de sauvegarde. Les temps, prérequis, carburants, cargaisons, vitesses, noms et assets sont conservés.

Candidat final SHA-256 : `0fd323710f8a7fbe5b5b12d99e52524988d390fc55b96e85fe576720f0e066c4`.

### Contrôles lors de l’intégration

- `make validate` : réussi.
- Tests ciblés R14, simulateur, contres, progression de rang et isolation des surcharges : **23 tests réussis, 7 407 assertions**, dont la concordance des 192 journaux complets.
- `make test` : **158 tests, 5 erreurs et 14 échecs**. Une copie témoin externe du catalogue et du moteur précédents reproduit les mêmes tests en échec, avec 5 erreurs et 18 échecs avant actualisation des attentes de combat. Aucun nouveau test en échec n’est introduit par R14. Les problèmes restants concernent notamment les boosts/files, les prérequis, des attentes de recherche et l’entrée dans un combat pour le test de butin ; ils ne sont pas corrigés dans cette intégration.
- `make lint` : syntaxe PHP, YAML/JSON, Twig, injection des services et mapping Doctrine réussis. La dernière étape échoue car le schéma de la base n’est pas synchronisé. Aucune migration n’a été exécutée.
- `git diff --check` : réussi. Aucune modification d’interface apportée par R14 ; compilation front et tests navigateur non relancés pour ce changement.

La transposition de R14 est contrôlée ; la branche complète ne peut pas être présentée comme entièrement validée tant que ces vérifications générales restent rouges.

### Fichiers du changement R14

- Moteur : `src/Service/Combat/CombatSimulator.php`, `src/Service/Catalog/GameCatalog.php`.
- Catalogue : `config/game/combat_modifiers.yaml` et, dans `config/game/ships/`, `fighter.yaml`, `interceptor.yaml`, `heavy_fighter.yaml`, `bomber.yaml`, `frigate.yaml`, `cruiser.yaml`, `destroyer.yaml`, `battleship.yaml`, `capital.yaml`.
- Tests : `tests/Service/CombatSimulatorTest.php`, `tests/Service/CombatCountersTest.php`, `tests/Service/BalanceMechanicsTest.php`, `tests/Integration/CatalogBalanceTest.php`, `tests/Service/R14RegressionTest.php`, `tests/Fixtures/combat-r14.json`.
- Documentation : `docs/DECISIONS.md`, `docs/REGLES.md`, `docs/BALANCING_R14.md`.
