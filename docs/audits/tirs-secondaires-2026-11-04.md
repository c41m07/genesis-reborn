# Chances de toucher des armes secondaires — 4 novembre 2026

Calcul exact avec les configurations du dépôt, sans recherches ni surcharge d’univers. Les tireurs et cibles sont au même rang. Le résultat est identique aux trois rangs lorsque leurs rangs sont égaux.

Bonus secondaire : **+30 points**. Chance de base = précision + suivi + bonus secondaire − esquive − 15 + 10 × écart de rang, bornée à 15–95 %. Le coefficient de contre multiplie cette chance, puis les bornes de 15–95 % sont réappliquées. Le même coefficient multiplie aussi les dégâts par impact.

| Type de vaisseau ciblé | Classe de contre | Classe de ciblage | Cuirassé | Capital | Croiseur | Destroyer |
|---|---|---|---:|---:|---:|---:|
| Cuirassé | Cuirassé | heavy | — | — | — | — |
| Bombardier | Bombardier | bomber | — | — | — | — |
| Capital | Capital | capital | — | — | — | — |
| Vaisseau colonie | Vaisseau colonie | support | — | — | — | — |
| Corvette | Corvette | escort | — | — | — | — |
| Croiseur | Croiseur | heavy | — | — | — | — |
| Destroyer | Destroyer | escort | — | — | — | — |
| Chasseur | Chasseur | light | 68 % | 70,15 % | 75 % | 81 % |
| Frégate | Frégate | escort | — | — | — | — |
| Chasseur lourd | Chasseur lourd | heavy | — | — | — | — |
| Intercepteur | Chasseur | light | 53 % | 52,9 % | 60 % | 66 % |
| Sonde | Sonde | support | — | — | — | — |
| Chasseur furtif | Chasseur | light | 38 % | 35,65 % | 45 % | 51 % |
| Transporteur | Transporteur | support | — | — | — | — |

**— : cible non admissible, aucun tir secondaire.** Le filtre actuel des quatre armes secondaires est `light`. Le Chasseur lourd est classé `heavy` et reste exclu tant qu’une décision ne change pas sa classe.

La classe de contre Chasseur regroupe le Chasseur standard, le Chasseur furtif et l’Intercepteur, aux trois rangs. Le Chasseur lourd reste distinct. Un coefficient envers cette classe s’applique à chacun de ses membres ; il ne dépend pas du nom de la variante. Les anciens bonus regroupés sont consolidés à leur valeur la plus forte, sans cumul.

La variante CSV détaille les 504 confrontations : quatre tireurs × quatorze cibles × trois rangs de tireur × trois rangs de cible. Commande : `php scripts/simulate-secondary-hits.php --csv`.

## Regroupement appliqué et contrôles

La décision explicite sur les membres de Chasseur est consignée dans [DECISIONS.md](../DECISIONS.md). Les classes cibles sont déclarées dans `config/game/combat_modifiers.yaml`, puis développées par le catalogue pour le moteur et ses instantanés. Les fiches utilisent les relations regroupées : la Corvette affiche Chasseur ×1,30 et Capital ×1,15. Les anciennes relations vers les trois membres sont consolidées à leur coefficient le plus fort, sans cumul ; les profils offensifs de ces trois coques restent distincts.

Les anciennes valeurs distinctes du capital contre le Chasseur standard et le Chasseur furtif sont remplacées par le coefficient de classe ×1,15 déjà présent contre l’Intercepteur. Les trois probabilités secondaires deviennent donc 70,15 %, 52,9 % et 35,65 % à rang égal, sans recherches. Les probabilités des trois autres armes secondaires restent inchangées.

Vérifications du regroupement :

- `make validate`, `make lint` et vérification de syntaxe du script : réussis.
- Tests ciblés combat, fiches et coûts : **14 tests / 2 825 assertions**, réussis. Ils incluent les trois membres de Chasseur, leurs neuf combinaisons de rangs, les dégâts et chances de toucher des journaux réels, l’exclusion du Chasseur lourd et la présentation d’une seule relation Chasseur dans les fiches.
- Parcours navigateur isolé : ouverture et fermeture des **150 fiches**, contrôles sur mobile et en thème clair, texte et relations de la Corvette intermédiaire : réussis.
- Matrice CSV : 504 cas vérifiés ; les membres de la classe de contre `fighters` sont exactement `fighter`, `stealth_fighter` et `interceptor`.
- Dernière suite générale : **141 tests, 3 erreurs et 15 échecs**. Les 17 erreurs/échecs déjà connus persistent ; un échec supplémentaire concerne la récompense de mission d’exploration. Les tests ciblés de cette modification passent ; la suite générale n’est pas déclarée valide.
