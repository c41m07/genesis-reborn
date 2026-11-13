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

La CI du commit `c7d7886` a exécuté 880 tests PHP : un seul échec, indépendant du calcul électrique, dans le parcours HTTP de rappel. Son horloge figée au 12 novembre était remplacée par l’heure réelle au redémarrage du noyau entre requêtes ; après 24 h réelles, l’expiration de session intervenait avant le contrôle CSRF. Correction de complexité 2 : conserver le noyau et l’horloge simulée dans ce test, sans modifier la sécurité de l’application. Les assertions 403/404 sont conservées et le passage effectif de la flotte en retour est ajouté. Reprise locale : 28 tests, 210 assertions, réussis. La prochaine CI complète reste requise sur le commit corrigé.

Les quatre lots navigateur de la CI `c7d7886` sont tous réussis : 46 + 59 + 33 + 46, soit 184 scénarios. Aucun autre correctif n’est issu de cette exécution. Le job de publication est ignoré sur la PR, conformément au workflow. Ces réussites ne remplacent pas la nouvelle CI complète requise sur le correctif de l’horloge du test.

La CI `e230527` a ensuite relevé le même basculement d’horloge dans `GuildTagPlayerReportTest` (6 scénarios) et `ColonyAbandonmentTest` (3 scénarios), une fois leur date simulée dépassée de 24 h. Le test de rappel corrigé passe. Complexité réévaluée à 3 pour vérifier le cycle des requêtes HTTP et des sessions dans les tests concernés. Même correction ciblée de conservation du noyau dans ces deux classes, sans modifier leurs assertions ni la sécurité de production.

Après ces corrections, **`make test` complet réussit en un passage : 880 tests PHP, 211 209 assertions et 13 tests Python**. La CI complète sur la version corrigée demeure obligatoire avant fusion. Aucun nouveau changement fonctionnel depuis les 184 scénarios navigateur réussis.

## Précision du revenu de base

Décision explicite : seules les bases métal et nano restent à 10/h avant modificateurs ; antimatière et hydrogène passent de 10/h et 80/h à 0, comme l’électricité déjà nulle. Les productions industrielles et les stocks existants ne sont pas modifiés. Complexité 3 : contrôle des périodes hors ligne, modificateurs et pénuries, sans migration.

Le libellé « Manque » du catalogue indique le coût moins le stock disponible, arrondi à l’unité supérieure. Sur demande précisée ensuite, le manque est intégré directement au bouton désactivé et gris : « Manque 300 Métal · 100 Nano · 200 Électricité » par exemple. Le message séparé dans les coûts est retiré. Le bouton retrouve son action lorsque le lot est constructible ; les aperçus conservent leurs contrôles serveur. Les images des rangs verrouillés sont en noir et blanc jusqu’au déblocage. Complexité 3 : rendu serveur sans JavaScript et mise à jour du bouton pour les lots.

Revalidation locale du passif : `make test` réussit avec 880 tests PHP, 211 214 assertions et 13 tests Python. Après intégration du bouton de manque, ajout d’un test HTTP en mode Twig strict : 1 test, 25 assertions réussies sur les quatre catalogues et le retour de l’action après réapprovisionnement. Ce test reproduit le risque de portée de `queueFull`, désormais défini avant les boucles du template. `make lint` et `make assets` réussissent.

Les sept scénarios navigateur concernés passent après les reprises du scénario de catalogue : boutons de manque, montants multiples, réactivation après réapprovisionnement, illustrations verrouillées puis déverrouillées, thèmes clair/sombre, mobile et parcours sans JavaScript. Les assertions de formulaire ciblent maintenant la commande, distincte des formulaires d’aide inclus dans les cartes.

Coûts des quatre catalogues : grille limitée à cinq ressources sur une ligne, adaptée à une, deux, trois ou quatre ressources. Dans les cartes étroites à cinq coûts, les libellés longs sont abrégés ; les noms et montants exacts restent disponibles dans les alternatives et titres. Vérification renforcée du cas réel d’un vaisseau à cinq coûts aux largeurs 1 920, 1 440, 390 et 320 px, sans débordement.

Validation de la ligne à cinq ressources : compilation réussie, deux scénarios navigateur réussis sur les quatre catalogues et aux largeurs 1 920/1 440/390/320 px, puis contrôle visuel de la carte à cinq coûts à 320 px. Le rendu Twig strict reste validé avec 1 test et 25 assertions.

## Bilan par ressource et synthèse allégée

Sur demande, le grand bilan groupé de la vue générale est remplacé par une fiche dans le bandeau pour chaque ressource. Ouverture au survol et au focus, bouton natif utilisable au toucher et sans JavaScript, fermeture par Échap ou bouton. Les sources, consommations, soldes et estimations proviennent toujours du même instantané économique ; les valeurs et détails sont rafraîchis par le canal existant. Les dépenses électriques ponctuelles restent distinguées des consommations continues.

Complexité 4 : composant partagé sur les pages privées, actualisation et accès aux données. Le détail est activé uniquement pour la colonie du propriétaire dans le bandeau et sur l’endpoint déjà protégé ; les instantanés par défaut utilisés ailleurs ne sont pas enrichis. Vérifications serveur des refus inter-joueurs et inter-univers.

La liste détaillée « Vos colonies » de la vue générale est retirée au profit du compteur et de son lien vers Planètes déjà présents. Les dates d’acquisition et leur ordre restent dans Planètes et le sélecteur de colonie ; le test d’ordre vérifie maintenant ces points d’accès, sans supprimer ses contrôles de dates inconnues.

Validation des fiches économiques : suite complète locale réussie, 882 tests PHP (211 274 assertions) et 13 tests Python. Les deux scénarios finaux du bandeau et du parcours passent : ouverture/fermeture au focus, Échap, ouverture directe dans le résumé mobile replié, thèmes clair/sombre, géométrie de la fiche, contrôle Axe ciblé et accès sans JavaScript. Quatre scénarios complémentaires de comptes/flottes passent également. Les captures des fiches dans les deux thèmes ont été examinées. Lint et compilation réussis ; CI complète requise sur la version publiée.

La CI de `8562253` a relevé une attente obsolète du test `empire-details` : il cherchait encore les cartes détaillées sur la vue générale après leur retrait demandé. Le scénario vérifie maintenant leur absence, l’accès à Planètes puis tous les comportements de survol, clavier, tactile, dimensions et accessibilité sur cette page. Le suivi des flottes reste testé sur la vue générale. Reprise locale réussie du scénario complet ; aucune assertion d’interaction n’est retirée de la fonctionnalité conservée.

Les cartes à cinq coûts adaptent aussi la taille des montants en vue condensée : les libellés restent abrégés sur les cartes étroites et les montants longs ne se chevauchent pas. Le contrôle navigateur couvre ce cas aux mêmes largeurs de 320, 390, 1440 et 1920 pixels.
