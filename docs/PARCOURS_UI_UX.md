# Parcours UI/UX — suivi du 13 novembre 2026

Branche unique : `feature/amelioration-parcours-ui-ux`, créée depuis `origin/dev` vérifiée à `7189f45`. Aucun push, PR publiée, fusion ou déploiement. Les captures `docs/PGimg` restent locales, intactes et exclues des commits. Les trois documents Work nommés dans AGENTS.md ne sont pas présents dans les fichiers du dépôt examinés ; aucune validation des phases Work n’est revendiquée.

## Périmètre réalisé

- Navigation (complexité 2/5) : raccourcis vers la carte interactive, nom utile pour la liste des systèmes, guide technologique sous Développement, événements accessibles en permanence, distinction Guilde et relations personnelles. Les objectifs technologiques possédaient déjà leurs liens vers les fiches et prérequis : ils sont conservés.
- Pages (3/5) : cartes de planètes conservées et comparaison en tableau avec stocks/capacités, solde net et commandes par colonie ; vue condensée mémorisée séparément pour les quatre catalogues ; quantités manquantes visibles ; rangs du catalogue dans l’adresse et retour arrière. Le tableau défile horizontalement dans une région nommée et accessible au clavier sur mobile.
- Organisation (3/5) : accès par ancres aux missions, demandes d’orbite, boosts, boîtes, récompenses en attente, projets en cours/disponibles/terminés et sections des rapports. Ces accès gardent le contenu visible, suivent l’historique du navigateur et fonctionnent sans JavaScript. Les filtres, formulaires, confirmations et protections existants sont conservés. Les détails d’espionnage sont repliables ; les retours depuis les trois types de rapports conservent filtre, archives et page.
- Parcours (3/5) : bilan d’annulation serveur listant commandes/dépendances et remboursements estimés par colonie ; récapitulatif de mission séparant origine, destination, mission, composition, fret, durée, arrivée et carburant ; causes de blocage et accès chantier/guide depuis la galaxie. Les défis renvoient aux activités correspondantes ; les transports rappellent l’obligation de livrer ses propres colonies. Profil, Préférences et Vacances sont reliés, suppression de compte isolée visuellement.
- Lisibilité (2/5) : points totaux immédiatement après le commandant, détail du calcul repliable ; sources de production et consommation dans le bilan ; incidents présents mis en évidence dans l’administration ; recherche serveur dans le wiki et accès au sommaire. L’accueil public utilise uniquement des captures d’un compte de test isolé, sans données de joueurs réels ni images de Projet Génésis.
- Économie (4/5) : bilans provenant du point de fonctionnement de `FactoryEconomy`, sans second calcul industriel. Stocks pleins, pertes de production non stockée, consommations, soldes et estimations sont distingués. Les détails économiques ne sont demandés que pour la colonie du propriétaire et ne sont pas ajoutés aux instantanés d’espionnage. Aucun historique de production inventé.

## Décisions économiques validées

Revenu de base **total** : 10 métal/h et 10 nano/h à vitesse ×1, position 5 ; remplace les anciens 40/20, sans ajout en double. Modificateurs et vacances conservés. L’application aux gains en attente suit la décision antérieure, sans migration.

Coût ponctuel : `ceil(durée effective en secondes / 60)` pour recherches, vaisseaux et défenses. Paiement à l’ajout, calcul par lot entier, rang et réductions inclus. Accélérations ultérieures sans recalcul. Annulation : 25 % du coût payé, dépendances incluses, stockage disponible ; capture sans remboursement. Reconstructions automatiques également concernées. Les commandes déjà payées ne sont pas taxées rétroactivement. Voir [l’étude et ses limites](ETUDE_COUT_ELECTRIQUE.md).

Le passif permet de financer un premier producteur direct en 48 h depuis zéro dans le scénario ×1/position 5, mais ne produit pas d’électricité ; une centrale à 1 440 M/480 N demande au moins 144 h dans ces mêmes conditions. Aucun déblocage universel promis.

## Vérifications

Docker Desktop était arrêté puis a été démarré avec accord. Le client Docker local nécessite de résoudre les liens Compose WSL : les commandes Make utilisent une surcharge temporaire de `DC`, sans changement de configuration globale ni de fichiers Compose. Les tests navigateur utilisent un adaptateur temporaire hors dépôt pour la même raison.

Résultats examinés :

- `make validate` : réussi avec la surcharge locale des chemins Compose.
- `make lint` : réussi (PHP, Twig, YAML, conteneur et cohérence du schéma, sans migration sur la base du jeu).
- `make assets` : réussi après les derniers ajustements de contraste et de mise en page.
- `make test` : **879 tests PHP, 211 196 assertions**, puis **13 tests Python**, tous réussis. Un complément ciblé d’aperçu/coût réel, ajouté ensuite sans changement métier, réussit avec 1 test et 11 assertions.
- Navigateur : sélection de **23 scénarios distincts** validée après corrections : 20 réussites dans l’exécution élargie, puis reprises réussies de la galaxie, du bilan des parcours et du bandeau. Ce résultat est consolidé ; il ne constitue pas une suite navigateur complète passée en une seule exécution. Les 4 scénarios séparés des boîtes de ressources passent également.
- Le nouveau scénario de parcours contrôle 1 440/390 px dans les deux thèmes sur vue générale, comparaison, flottes, inventaire, événements, profil, préférences et diplomatie, avec contrôles Axe ciblés. Il vérifie aussi l’annulation en cascade, le retour arrière de la comparaison, la recherche wiki sans JavaScript et les métadonnées de l’accueil public.
- Les défauts de contraste détectés ont été corrigés ; le test du bandeau attend les polices et se place instantanément en haut de page avant les mesures, afin de ne pas comparer un contenu déjà défilé avec une barre fixe. Les assertions de géométrie et de pénurie restent actives.
- Les anciens tests de prix gardent leurs budgets et durées figés ; ils attendent désormais, en plus, l’électricité de la règle validée. Les fixtures historiques n’ont pas été réécrites.
- `git diff --check` : réussi avant les commits.

## Limites

Aucune CI GitHub exécutée dans ce lot local ; la CI complète de la dernière PR reste requise avant fusion. Aucun accès externe au wiki documentaire n’a été utilisé : le manuel livré avec le jeu et ses sources locales sont synchronisés. Les vérifications ciblées d’accessibilité ne constituent pas un audit RGAA complet. Aucun simulateur, composition enregistrée ou recherche avancée des rapports n’est ajouté.
