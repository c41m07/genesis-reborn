# Parcours UI/UX — suivi du 13 novembre 2026

Branche unique : `feature/amelioration-parcours-ui-ux`, créée depuis `origin/dev` vérifiée à `7189f45`. Branche publiée et [PR #54 ouverte vers dev](https://github.com/c41m07/genesis-reborn/pull/54). Aucune fusion ni aucun déploiement. Les captures `docs/PGimg` restent locales, intactes et exclues des commits. Les trois documents Work nommés dans AGENTS.md ne sont pas présents dans les fichiers du dépôt examinés ; aucune validation des phases Work n’est revendiquée.

## Périmètre réalisé

- Navigation (complexité 2/5) : raccourcis vers la carte interactive, nom utile pour la liste des systèmes, guide technologique sous Développement, événements accessibles en permanence, distinction Guilde et relations personnelles. Les objectifs technologiques possédaient déjà leurs liens vers les fiches et prérequis : ils sont conservés.
- Pages (3/5) : cartes de planètes conservées et comparaison en tableau avec stocks/capacités, solde net et commandes par colonie ; vue condensée mémorisée séparément pour les quatre catalogues ; quantités manquantes visibles ; rangs du catalogue dans l’adresse et retour arrière. Le tableau défile horizontalement dans une région nommée et accessible au clavier sur mobile.
- Organisation (3/5) : accès par ancres aux missions, demandes d’orbite, boosts, boîtes, récompenses en attente, projets en cours/disponibles/terminés et sections des rapports. Ces accès gardent le contenu visible, suivent l’historique du navigateur et fonctionnent sans JavaScript. Les filtres, formulaires, confirmations et protections existants sont conservés. Les détails d’espionnage sont repliables ; les retours depuis les trois types de rapports conservent filtre, archives et page.
- Parcours (3/5) : bilan d’annulation serveur listant commandes/dépendances et remboursements estimés par colonie ; récapitulatif de mission séparant origine, destination, mission, composition, fret, durée, arrivée et carburant ; causes de blocage et accès chantier/guide depuis la galaxie. Les défis renvoient aux activités correspondantes ; les transports rappellent l’obligation de livrer ses propres colonies. Profil, Préférences et Vacances sont reliés, suppression de compte isolée visuellement.
- Lisibilité (2/5) : points totaux immédiatement après le commandant, détail du calcul repliable ; sources de production et consommation dans le bilan ; incidents présents mis en évidence dans l’administration ; recherche serveur dans le wiki et accès au sommaire. L’accueil public utilise uniquement des captures d’un compte de test isolé, sans données de joueurs réels ni images de Projet Génésis.
- Économie (4/5) : bilans provenant du point de fonctionnement de `FactoryEconomy`, sans second calcul industriel. Stocks pleins, pertes de production non stockée, consommations, soldes et estimations sont distingués. Les détails économiques ne sont demandés que pour la colonie du propriétaire et ne sont pas ajoutés aux instantanés d’espionnage. Aucun historique de production inventé.

## Décisions économiques validées

Revenu de base **total** : 10 métal/h et 10 nano/h à vitesse ×1, position 5 ; remplace les anciens 40/20, sans ajout en double. Modificateurs et vacances conservés. L’application aux gains en attente suit la décision antérieure, sans migration.

Coût ponctuel : `100 × ceil(durée effective en secondes / 60)` pour recherches, vaisseaux et défenses. Paiement à l’ajout, calcul par lot entier, rang et réductions inclus. Accélérations ultérieures sans recalcul. Annulation : 25 % du coût payé, dépendances incluses, stockage disponible ; capture sans remboursement. Reconstructions automatiques également concernées. Les commandes déjà payées ne sont pas taxées rétroactivement. Voir [l’étude et ses limites](ETUDE_COUT_ELECTRIQUE.md).

Le passif permet de financer un premier producteur direct en 48 h depuis zéro dans le scénario ×1/position 5, mais ne produit pas d’électricité ; une centrale à 1 440 M/480 N demande au moins 144 h dans ces mêmes conditions. Aucun déblocage universel promis.

## Vérifications

Docker Desktop était arrêté puis a été démarré avec accord. Le client Docker local nécessite de résoudre les liens Compose WSL : les commandes Make utilisent une surcharge temporaire de `DC`, sans changement de configuration globale ni de fichiers Compose. Les tests navigateur utilisent un adaptateur temporaire hors dépôt pour la même raison.

Résultats examinés :

- `make validate` : réussi avec la surcharge locale des chemins Compose.
- `make lint` : réussi (PHP, Twig, YAML, conteneur et cohérence du schéma, sans migration sur la base du jeu).
- `make assets` : réussi après les derniers ajustements de contraste et de mise en page.
- Avant les précisions et le coût ×100, `make test` : **879 tests PHP, 211 196 assertions**, puis **13 tests Python**, tous réussis. Un complément ciblé d’aperçu/coût réel, ajouté ensuite sans changement métier, réussit avec 1 test et 11 assertions.
- Navigateur : sélection de **23 scénarios distincts** validée après corrections : 20 réussites dans l’exécution élargie, puis reprises réussies de la galaxie, du bilan des parcours et du bandeau. Ce résultat est consolidé ; il ne constitue pas une suite navigateur complète passée en une seule exécution. Les 4 scénarios séparés des boîtes de ressources passent également.
- Le nouveau scénario de parcours contrôle 1 440/390 px dans les deux thèmes sur vue générale, comparaison, flottes, inventaire, événements, profil, préférences et diplomatie, avec contrôles Axe ciblés. Il vérifie aussi l’annulation en cascade, le retour arrière de la comparaison, la recherche wiki sans JavaScript et les métadonnées de l’accueil public.
- Les défauts de contraste détectés ont été corrigés ; le test du bandeau attend les polices et se place instantanément en haut de page avant les mesures, afin de ne pas comparer un contenu déjà défilé avec une barre fixe. Les assertions de géométrie et de pénurie restent actives.
- Les anciens tests de prix gardent leurs budgets et durées figés ; ils attendent désormais, en plus, l’électricité de la règle validée. Les fixtures historiques n’ont pas été réécrites.
- `git diff --check` : réussi avant les commits.

## Limites

CI GitHub lancée sur la PR #54 ; consulter ses résultats sur la dernière version de la branche. La réussite complète reste requise avant fusion, qui nécessite l’accord explicite du propriétaire. Aucun accès externe au wiki documentaire n’a été utilisé : le manuel livré avec le jeu et ses sources locales sont synchronisés. Les vérifications ciblées d’accessibilité ne constituent pas un audit RGAA complet. Aucun simulateur, composition enregistrée ou recherche avancée des rapports n’est ajouté.

## Corrections pendant la CI de la PR #54

La première exécution complète a détecté trois régressions de présence du panneau Vacances dans les cas sans participation valide ou avec fonction désactivée. Le raccourci du compte vise désormais une ancre informative permanente `vacances-info` ; `vacances` reste réservé aux commandes actives, conditionnées comme auparavant. Les refus serveur et les tests de non-présence sont conservés ; les tests vérifient aussi l’absence du formulaire d’activation et la présence de l’information.

Le lot navigateur 2 a également signalé une attente obsolète du libellé « Alliance » sur la métrique de guilde. Le scénario vérifie désormais « Guilde », conformément à la distinction demandée, avec le même contrôle du lien et du rendu.

Le lot navigateur 3 a relevé des attentes à adapter à la demande : points totaux après le commandant, explications du classement à ouvrir au clavier, et bouton « Reprendre mon empire ». Il a aussi détecté un débordement réel du champ de message sur tablette depuis l’ajout de la capacité dans le bandeau. Le fil de conversation borne désormais sa hauteur selon la place réellement disponible sur ordinateur/tablette, en observant le panneau et le bandeau ; le comportement mobile existant est conservé. Le test exige toujours que le champ soit entièrement dans la fenêtre.

Le lot navigateur 1 a détecté deux régressions réelles : le titre du bilan économique débordait sur mobile avec un nom de colonie sans espaces, et un changement de rang inscrit dans l’adresse réinitialisait le filtre lors d’un rafraîchissement. Le titre peut maintenant se répartir sur plusieurs lignes. Le catalogue distingue ses propres changements d’ancre des liens externes et conserve un filtre compatible lors du retour arrière ; le scénario existant vérifie aussi le retour/précédent et suivant.

Le lot navigateur 4 a relevé cinq scénarios qui utilisaient encore le parcours ou les libellés précédents : message générique de maximum nul, espionnage toujours déplié, annulation immédiate de recherche, ancienne appellation de la carte et taille uniforme des onglets. Ils vérifient désormais la cause chiffrée incluant l’électricité, le dépliage des cinq catégories au clavier, le bilan puis la confirmation d’annulation et les noms de vues demandés. Le contrôle de typographie conserve des tailles exactes et distingue les onglets des pages joueur déjà prévus à 14 px.

## Précisions du lot 1

Sommaire du wiki persistant sur ordinateur, sélecteur de chapitre mobile utilisable sans JavaScript, recherche et liens directs conservés. Feedback : « Envoyer mon retour ». Accueil : « Développez votre empire » évite de promettre un choix d’univers sur une section de présentation générale.

Classement : univers, catégorie et actualisation visibles, explications dans « Comprendre le classement ». États vides des flottes et de l’inventaire compacts ; les missions actives profitent de la largeur disponible en l’absence de flotte à quai. Raccourcis ciblés vers la sonde au chantier, l’onglet Rejoindre une guilde, les constructions et la préparation de missions pouvant ensuite devenir éligibles à un boost. Administration : priorité aux incidents ou signalements, avec « Préparer un événement » conservé dans les accès rapides.

Nouvelle décision électrique : multiplier par 100 le coût précédent, soit `100 × ceil(durée effective / 60)` ; minimum 100 E par commande et 200 E pour 61 secondes. Les tests et l’étude sont adaptés à ce palier. Complexité 3 : calculateur partagé, aperçus, paiement, maximum, remboursement et reconstruction automatique concernés.

Lot 2 : simulateur, compositions et recherche avancée dans une branche et une PR distinctes, après CI complète et fusion autorisée du lot 1. Aucun développement du lot 2 commencé.

## Revalidation des compléments

`make lint` et `make assets` réussis. Calcul électrique : 27 tests ciblés, 22 840 assertions. L’exécution PHP complète après passage au coût ×100 (879 tests) a relevé trois attentes de coût ou de stock de test devenues obsolètes ; elles ont été mises à jour sans retirer leurs vérifications. La reprise ciblée couvre 32 tests et 2 115 assertions, y compris le nouveau refus d’une recherche à 1 000 E avec 999 E, le paiement unique, les dépendances, l’accélération, les remboursements et les reconstructions. Les 13 tests Python passent également. Cette reprise consolidée ne constitue pas une nouvelle suite PHP complète en un passage.

Protection de `dev` vérifiée via l’API GitHub : PR obligatoire, branche à jour et cinq contrôles `quality` exigés (PHP et quatre lots navigateur), administrateurs inclus. `publish` n’est pas requis sur les PR. La CI doit encore être vérifiée sur le dernier commit publié ; aucune fusion automatique.

Les 22 scénarios navigateur des compléments sont validés après reprise ciblée du parcours général : 21 réussites initiales, puis parcours et wiki réussis ensemble après correction du ciblage du lien de sonde et du positionnement du sélecteur. La dernière reprise contrôle que le chapitre mobile reste visible sous le sélecteur persistant, même sans JavaScript. Les captures du wiki ordinateur/mobile dans les deux thèmes ont été examinées, en complément des contrôles automatisés.
