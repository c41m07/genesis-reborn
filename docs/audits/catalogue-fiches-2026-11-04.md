# Revue des fiches du catalogue — 4 novembre 2026

## Périmètre

Revue des 150 variantes : 39 bâtiments, 57 recherches, 42 vaisseaux et 12 défenses. Les points 1 à 9 proposés dans la conversation sont intégrés. Le changement de présentation des pourcentages relatifs (point 10) reste explicitement différé. Aucune formule de jeu, valeur d’équilibrage ou illustration n’est modifiée.

## Répartition des informations

Les cadres verts présentent les gains ou caractéristiques synthétiques. Les explications sont dans les fiches détaillées : portée, fonctionnement, limites, conditions d’accès et coûts. Les fiches distinguent les niveaux et les réserves de chaque rang.

| Famille | Informations de la fiche |
|---|---|
| Production | Débits nominaux actuels et après amélioration, entretien électrique et hydrogène, incidence de la position et de l’univers, pénuries et renvoi au débit net de la barre de ressources. |
| Stockage | Capacité actuelle et future, seuil protégé actuel et futur, protection supplémentaire et pertes en cas de livraison excédentaire. |
| Laboratoire, chantier, bunker | Portée locale, contribution des rangs, opérations concernées, conservation des durées déjà enregistrées, combinaison du bunker avec les recherches de structure. |
| Recherches militaires | Unités concernées, effet concret de chaque statistique, distinction entre ciblage et chance de toucher, attaques principale et secondaire, tirs supplémentaires. |
| Logistique | Réservoir réservé au carburant, soute pour fret ou carburant supplémentaire, réservation aller-retour, flotte limitée par son unité la plus lente. |
| Espionnage | Détection, effet des recherches et du nombre de sondes, limites du risque, catégories du rapport, conditions des missions et consommation des sondes. |
| Colonisation | Limite totale actuelle et après le niveau visé, plafond d’univers, prochain niveau du rang ouvrant un emplacement, contribution des niveaux à gain nul. |
| Reconstruction | Défenses détruites après combat, calcul par type et rang, arrondi inférieur et exemple. |
| Vaisseaux et défenses | Statistiques du rang comparées aux valeurs avec les recherches et le bunker local pour les défenses, tirs et armement secondaire, coefficients de dégâts configurés, boucliers, mobilité et réserves. |

Les valeurs militaires « Avec vos bonus » utilisent `TechnologyEffectCalculator`, comme les services de combat et de flotte. Le coût et la durée proviennent ensemble d’`OperationCalculator`, réductions comprises. Les déblocages présentés sont associés au rang exact, avec lien vers la carte de ce rang et rappel des autres prérequis. Au niveau maximum, aucune nouvelle opération fictive n’est proposée dans la fiche.

## Contrôles

- `make validate` et `make lint` : réussis.
- `make assets` (`npm run build`) : réussi.
- Tests ciblés `CatalogueDetailsTest`, `CatalogueSheetsUseDiscountedCosts`, `ResourceStripMetadata`, `FactoryEconomyTest` : 12 tests, 1 342 assertions, réussis.
- Parcours Playwright isolé `catalogue-details.spec.mjs` : ouverture et fermeture des 150 fiches, absence d’erreur JavaScript, restitution du focus, réserves des défenses par rang, attaque secondaire avec recherches, colonisation, sondes, fiches à 768 et 390 px et thème clair. Vérification supplémentaire de l’état de niveau maximum.
- Suite générale `make test` : 133 tests, 3 erreurs et 14 échecs. Les mêmes tests échouaient avant cette refonte (équilibrage, boosts, colonisation, combat, classement et remboursement). Ces échecs ne sont pas déclarés corrigés par ce lot.

Le rapport d’illustrations du 3 novembre reste distinct : cette revue concerne le contenu et le fonctionnement des fiches, sans nouvelle validation des images.

## Prérequis actuellement inatteignables

Dix variantes exigent un niveau supérieur au maximum de la recherche demandée. Les fiches signalent ce fait, sans modifier les seuils ni les niveaux maximum. Les surcharges de l’univers sont prises en compte dans ce diagnostic.

| Variante | Prérequis demandé | Maximum actuel |
|---|---|---|
| `intelligence_countermeasures` Supérieur | `tactical_intelligence` Intermédiaire 10 | 5 |
| `modular_cargo_bays` Intermédiaire | `colonial_expansion` Basique 5 | 4 |
| `modular_cargo_bays` Supérieur | `colonial_expansion` Intermédiaire 10 | 4 |
| `signature_masking` Supérieur | `tactical_intelligence` Intermédiaire 10 | 5 |
| `signature_reduction` Supérieur | `signature_masking` Intermédiaire 10 | 5 |
| `tactical_intelligence` Supérieur | `signature_masking` Intermédiaire 10 | 5 |
| `colony_ship` Intermédiaire | `colonial_expansion` Basique 5 | 4 |
| `colony_ship` Supérieur | `colonial_expansion` Intermédiaire 10 | 4 |
| `probe` Supérieur | `tactical_intelligence` Intermédiaire 10 | 5 |
| `stealth_fighter` Supérieur | `signature_masking` Intermédiaire 10 | 5 |

Leur déblocage nécessite une décision distincte sur les prérequis ou les plafonds de recherche ; cette revue ne fait aucun arbitrage d’équilibrage.
