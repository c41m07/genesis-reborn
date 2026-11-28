# Recettes du catalogue RP

Le prix des matériaux suit la fonction de la carte, sans modifier les prérequis ou les caractéristiques des unités. Sauf la progression des défenses précisée ci-dessous, les recettes conservent le budget du même rang avant cette redistribution. M = métal, H = hydrogène, A = antimatière, E = électricité, N = nano. La taxe E éventuelle est calculée au devis, pas ici. Les répartitions ne sont plus imposées uniformément par rang. Le métal et le nano ne sont pas obligatoires : on finance les pièces réellement produites, le prototype ou les consommables de la fiche. La construction d’un prototype n’est pas traitée comme un simple essai de carburant. Aucune combinaison n’est imposée pour remplir un quota de diversité.

Tests mécaniques : profils et poids gelés contre la [sauvegarde](SAUVEGARDE_CATALOGUE_COUTS_AVANT_RP.md), tests du moteur et de la progression. Le budget de matériaux est pondéré M×2, H×1, A×8, N×4 ; les arrondis du devis peuvent légèrement varier selon le mélange. Les compositions restent comparées sous des hypothèses de recherche et de budget explicites.

## Raison de chaque recette

### Bâtiments

- **Bunker de défense** : M : murs et blindage ; N : amortisseurs ; A dès l’Intermédiaire : champ de confinement des impacts lourds.
- **Laboratoire colonial** (budget Basique porté à 20 000 le 28 novembre, soit 4 800 métal et 2 600 nano) : M : enceinte des bancs ; N : microscopes/analyseurs ; A dans les rangs avancés : étalonnage des instruments en chambre de confinement.
- **Piège à antimatière** : M : cryostat ; N : piège et pilotage du champ ; H : débit de mise en service. Jamais d’A pour construire le piège.
- **Raffinerie d’hydrogène** : M : conduites et séparateurs ; N : membranes ; A aux rangs avancés : alimentation de l’étage magnétique, jamais d’H pour débloquer la raffinerie.
- **Mine à ciel ouvert** : M : galeries et rails ; N : têtes de forage ; A aux rangs avancés : essais du plasma qui fracture la roche profonde.
- **Atelier de nanofabrication** : M : enceinte ; N : buses et têtes de dépôt ; A aux rangs avancés : amorçage du faisceau de dépôt.
- **Centrale nucléaire** : M : cuve ; N : régulation ; H pour amorcer le combustible des rangs avancés ; A au Supérieur pour la chambre de conversion.
- **Chantier d’assemblage** : M : portiques et grues ; N : bras automatisés ; A aux rangs avancés : banc de soudure énergétique des grandes coques.
- **Cage magnétique** : M : enveloppe sécurisée ; N : bobines ; A au Supérieur pour les essais sous charge de la cage. Premier dépôt sans A.
- **Batterie de colonie** : M : conducteurs et coffre ; N : cellules ; A aux rangs avancés : étalonnage du convertisseur sous forte charge.
- **Réservoir cryogénique** : M : parois ; N : isolation ; H aux rangs avancés : essai réel de pression et d’étanchéité.
- **Dépôt de minerais** : M seul : le niveau ajoute des hangars, des casiers et des rails de charge ; il ne reconstruit pas la manutention existante. Aucun combustible spécial.
- **Coffre de nanomatériaux** : M : enceinte ; N : filtration ; A aux rangs avancés : confinement des particules dans les grandes cuves.

### Recherches

- **Drones réparateurs** : M : pièces de remplacement ; N : micro-outils et automatisation des drones.
- **Protocoles de colonisation** : M : prototypes de modules d’habitat ; N : systèmes autonomes de la colonie ; garder la recette actuelle tant que le premier parcours n’est pas recalé.
- **Réservoirs cryogéniques** : M : cuves ; N : isolants ; H : essais de remplissage sous pression et à froid.
- **Génie civil colonial** : M : maquettes porteuses et poutres ; N : instruments de contrôle. Le premier prérequis reste inchangé.
- **Manœuvres d’évitement** : M : maquettes et affûts de test ; N : guidage ; H : tirs d’essai des propulseurs de manœuvre.
- **Discipline du secret** : M : blindage des bancs de signal ; N : prototypes de chiffrement et capteurs.
- **Soute compartimentée** : M : casiers et rails de fret ; N : verrous et contrôle de charge.
- **Métallurgie des nanomatériaux** : M : cuves de synthèse ; N : matière des prototypes. Le premier déblocage reste inchangé.
- **Architecture navale** : M : quilles et formes de coque ; N : gabarits de conception. Préserve le premier accès à la colonisation.
- **Séquenceur de salves** : M : banc mécanique des armes ; N : contrôleurs de cadence et synchronisation.
- **Générateurs de bouclier accordés** : N : fabriquer les circuits de phase expérimentaux ; A : éprouver le champ sous forte charge. Les circuits sont montés sur les bancs du laboratoire, sans nouvelle enceinte métallique.
- **Écran de brouillage** : M : boîtiers d’écran électromagnétique ; N : émetteurs et prototypes de brouillage.
- **Revêtement absorbant** : N : fabriquer les couches absorbantes ; H : gaz de traitement durant le dépôt. Tester le revêtement sur des supports déjà présents au labo ne réclame pas de coque neuve.
- **Blindage multicouche** : M pour les plaques et alliages éprouvés. Les versions composites ajoutent N pour les treillis et les couches intégrées ; le premier blindage ne demande que du M.
- **Analyse tactique** : M : cages d’essai des capteurs ; N : matrices de détection et traitement des signaux.
- **Télémétrie de poursuite** : N seul : fabriquer les matrices de suivi et les optiques de précision testées sur les montures du banc de visée existant.
- **Propulseurs vectoriels** : M : fabriquer la tuyère du prototype ; N : fabriquer son dispositif d’orientation ; H : essais de propulsion à plein régime.
- **Viseurs balistiques** : M : canons et cibles d’essai ; N : instrumentation de précision.
- **Canons à énergie focalisée** : M pour fabriquer les affûts et N pour les lentilles et le pilotage. Les batteries avancées et l’artillerie consacrent une part croissante à l’A consommée pour éprouver leurs décharges ; le premier prototype reste M/N.

### Chantier spatial

Pour chaque vaisseau et chaque rang, le coût de référence reste identique en
valeur pondérée. On distribue cette valeur entre **M : coque et volume**,
**N : blindage ou équipement de précision**, **H : réacteur**, **A : armement**.
Ce sont des recettes explicites, pas un calcul sur les points de structure,
la signature ou la seule attaque moyenne : ces statistiques mesurent des
comportements différents. H à la construction équipe le réacteur et ne
remplace ni ne préremplit le carburant de vol.

- **Cuirassé de ligne** : coque lourde en M, blindage en N, réacteur de croisière en H, accélérateur de tir en A.
- **Bombardier tactique** : M pour la structure et les tubes, N pour les protections et le guidage. H finance le réacteur ; A finance ses torpilles spécialisées, même si le volume de la coque et le nombre de tirs sont moindres que sur un cuirassé.
- **Vaisseau capital** : M et N pour la très grande coque et son blindage, H pour sa propulsion lourde, A pour les batteries et la lance Titan. Le montant absolu dépasse celui d’un bombardier même quand leur part de valeur en A est proche.
- **Vaisseau colonisateur** : premier kit inchangé. Aux rangs avancés, M pour les modules d’habitat, N pour les automates et protections, H pour la propulsion ; aucune arme à financer.
- **Corvette de patrouille** : coque légère et protégée, réacteur de patrouille, et charge en A modérée pour l’armement d’interception.
- **Croiseur de combat** : M et N pour la coque et les protections, H pour le réacteur de croisière, A pour ses batteries de combat.
- **Destroyer d’escorte** : M pour la proue renforcée, N pour les protections, H pour le réacteur, A pour le canon axial dont le gros tir n’exige pas une coque de capital.
- **Chasseur de patrouille** : le premier prix M/N reste accessible. Les versions avancées paient aussi en H pour le réacteur et en A pour renforcer leurs lasers.
- **Frégate d’escorte** : M pour le châssis et les affûts, N pour protéger et coordonner les tourelles, H pour le réacteur, A pour les batteries à fragmentation.
- **Chasseur lourd** : part de N plus forte que sur un chasseur léger, cohérente avec le blindage ; H pour la propulsion de la cellule renforcée. L’A apparaît avec les armes avancées.
- **Récolteur atmosphérique** : premier prix inchangé. M pour le châssis et N pour les pompes ou membranes. Pas d’A. Le premier reste fabricable sans H ; les réacteurs des récolteurs avancés demandent du H dans la grille d’essai.
- **Intercepteur atmosphérique** : part de H élevée pour l’accélération, coque légère et peu de blindage, puis A pour les versions avancées de ses armes.
- **Sonde de reconnaissance** : première sonde inchangée. N domine aux rangs avancés pour les capteurs ; M et H couvrent le boîtier et le petit propulseur. Aucun A.
- **Satellite héliocapteur** : M pour le cadre initial et N pour les cellules. Les panneaux avancés intègrent la structure composite dans les cellules et ne demandent plus de cadre métallique. Ni réacteur de vol ni armement ; E reste la taxe de construction.
- **Chasseur furtif** : M pour la coque légère, N pour le blindage absorbant et les capteurs, H pour le petit réacteur, A pour son armement. Ne pas déduire sa taille de sa faible signature.
- **Cargo de ligne** : M consacré aux ponts et à la grande soute ; N pour les protections et la manutention ; H pour le réacteur à chaque rang, y compris le Basique dans la grille d’essai. Aucun A : il est sans armement.

### Défenses

- **Canon laser anti-léger** : M : affût léger ; N : capteurs rapides et suivi des signatures.
- **Canon laser anti-escorteur** : M : pivot et tourelle ; N : focalisation polyvalente et suivi.
- **Canon laser anti-lourd** : M : base et dissipateurs ; N : guidage thermique ; A : étages avancés de focalisation.
- **Canon laser anti-capital** : M : affût massif ; N : optiques ; A : chambre d’essai du faisceau anti-capital.

Décision : la valeur en matériaux monte de 25 % à chaque rang **par rapport
au précédent**, comme sur les vaisseaux. Le Basique est inchangé et chaque
rang garde les matériaux de sa recette RP. Sans hydrogène, les autres
matériaux imposent une valeur paire ; le devis vise la plus proche, avec au
plus un point d’écart par unité. L’électricité reste liée à la durée.

| Défense | Basique | Intermédiaire | Supérieur |
| --- | ---: | ---: | ---: |
| Anti-léger | 5 000 | 6 250 | 7 812 |
| Anti-escorteur | 18 000 | 22 500 | 28 126 |
| Anti-lourd | 47 000 | 58 750 | 73 438 |
| Anti-capital | 128 000 | 160 000 | 200 000 |

## Comparaison de vaisseaux à budget comparable

Les 16 familles conservent leur budget matériaux par unité aux trois rangs. L’énergie
n’entre pas dans ce budget : on compare séparément la taxe et le temps de chantier.
Les duels ont utilisé les caractéristiques du catalogue, sans recherche, à rang identique,
sans défense statique ni renfort. Les quantités ont été choisies pour rapprocher le coût
des deux formations : différence maximale de 0,327 %. Neuf paires × trois rangs, cinq
graines et les deux orientations attaque/défense : 270 duels avec chaque grille.
Même résultat, mêmes pertes et même nombre de rounds pour chaque essai apparié. Même constat pour 120 duels de compositions mixtes à budget comparable, et pour 45 comparaisons supplémentaires avec défense anti-léger et recherches actives (forces identiques).

- Intercepteurs et frégates battent les bombardiers dans cette série ; le bombardier
  bat le destroyer, le cuirassé et le capital. Le chasseur basique standard contre
  l’intercepteur ; le chasseur lourd punit la frégate ; le destroyer bat le croiseur.
- Le furtif perd contre le capital malgré son modificateur positif : ce bonus n’est
  pas une garantie de victoire à budget égal. Ce résultat préexistait au nouveau catalogue.
- Ce sont des duels de formations homogènes. Un rôle de soutien, un écran, des
  recherches, l’énergie, la distance ou un budget de production contraint peuvent
  changer les choix. Ne pas lire ces résultats comme une mesure d’équilibre PvP complet.

Sur le même moteur et le même plafond de durée, vingt-trois scénarios économiques
en univers ×1 ont été comparés avant et après (46 simulations). Le premier départ colonial jusqu’à l’arrivée reste à
29 h 57 sans attente entre connexions, ou 73 h 15 à trois connexions par jour.
Les premiers vaisseaux et défenses passent de 14 h 15 à 14 h 43.
Le lot de flotte lourde passe de 87 h 45 à 95 h 31 (première passe RP : 116 h 39).
La défense de masse passe de 159 h 09 à 159 h 54. Ces durées sont des scénarios
synthétiques, pas des délais promis en prod. Une colonie pauvre en métal ou
en nano ralentit encore par rapport à l’ancien catalogue ; la variante par
composants réduit ce surcoût par rapport à la première passe. Les attentes de
ressources peuvent se chevaucher et ne s’additionnent pas.

Avec un stock H nul et une industrie très déséquilibrée, le piège à antimatière
consomme presque toute la production H. Dans ce stress-test, un lot intermédiaire
de bombardiers ou cargos peut alors attendre plusieurs semaines plutôt que
quelques heures. Ce n’est pas un délai normal de jeu. Par décision produit, le
coût H du cargo basique et des récolteurs avancés reste dans cet essai, afin
d’observer le cas en jeu avant d’ajuster. Le premier récolteur est une voie de
reprise sans H ; même ainsi l’apport d’un seul récolteur ne suffit pas forcément
à relancer une flotte lourde si l’industrie d’antimatière consomme presque tout
le gaz produit. Observer les dépenses et attentes par matériau
avant toute promotion ; tester à budget égal n’établit pas l’accessibilité réelle.

Le moteur de points s’appuie toujours sur les quantités brutes de matériaux, pas leur
valeur pondérée. Le nouveau mélange peut donc modifier le classement ou les conditions
de protection au recalcul : le bombardier intermédiaire passe de 7 999 à 12 939 unités
brutes pour un budget économique identique. Aucun seuil de protection n’est changé
dans ce lot.

**Effet vérifié en base isolée :** avec exactement la même flotte, deux comptes témoins
passent de 4 799 à 7 763 points en direct. Avant minuit de Paris, le classement
affiche encore 4 799 points. Le compte récent perd néanmoins sa protection débutant ;
sur le compte plus ancien, un attaquant à 33 744 points passe du refus à l’autorisation
par la règle 5:1. Après minuit, le classement affiche 7 763 et un gain de 2 964 points
sans nouvel achat ni construction. Le scénario utilise 60 bombardiers intermédiaires par compte témoin et force les valeurs proches des seuils ;
ce n’est pas une estimation du nombre de joueurs concernés ni d’un parcours de débutant.
Avant d’envisager une mise en production, vérifier si cet effet est voulu et distinguer
les variations dues aux recettes de la progression réelle.

Les primes pirates et les coûts dérivés du catalogue reprennent la nouvelle
composition seulement lorsqu’un nouvel instantané est créé ; les instantanés
et paiements déjà sauvegardés restent inchangés.
