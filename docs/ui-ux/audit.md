# Audit UI/UX — Genesis Reborn

## Lot 20 — refus d’accès et retours selon la session

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Refus dédié, administrateur | Un CSRF invalide aboutit à « console réservée aux administrateurs » malgré le rôle détenu | Mauvais diagnostic et retour au jeu au lieu de la console | Message générique du handler et carte sans variante de session | Notification neutre; explication du formulaire potentiellement expiré et retour console pour un administrateur | Vrai refus avec configuration inchangée, explication pertinente et retour console réel | Élevée / occasionnelle — reproduit puis corrigé |
| Refus dédié, anonyme | Retour au jeu renvoyant indirectement à la connexion | Action peu explicite | Même lien pour toutes les sessions | Action Se connecter, sans ouverture de la console | Connexion réellement ouverte; accès admin toujours protégé sans JS | Modérée / occasionnelle — testé |
| Refus dédié, thème clair | Texte et surtitre à 4,41:1 sur blanc | Contraste sous le seuil 4,5:1 pour ces textes | Token muted du thème clair appliqué à tous les paragraphes de la carte | Token text limité à cette carte en clair | Axe sans violation; calcul de palette à 14,32:1 | Élevée / occasionnelle — corrigé et retesté |
| En-tête commun admin | Nom accessible ne reprend pas le mot visible Admin | Commande vocale et nom annoncé incohérents | aria-label Console administrateur | Admin : ouvrir la console | Label visible inclus; Axe sur l’en-tête de la colonie et la page de refus admin | Modérée / régulière — corrigé et retesté |

La 403 générique est conservée dans ce lot : explication et retour fonctionnent avant/après entrée en univers, au clavier et sans JS. Aucune règle d’accès, journalisation, réponse de refus ou vérification CSRF modifiée; seule la notification du handler change de texte.

## Lot 19 — paramètres hérités/fermés et nouveau mot de passe

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Paramètres d’univers | Des vitesses enregistrées ×100 apparaissent dans des champs limités à ×40 sans explication | Enregistrement bloqué, risque d’ajuster l’équilibrage pour résoudre un problème de formulaire | Univers Turbo importé avec durées à 0,01; édition serveur légitimement limitée à des vitesses 0,01–40 | Avertissement listant les vitesses enregistrées hors plage, relié aux champs concernés, sans normalisation ni changement de bornes | Lecture et refus conservent le JSON; un choix explicite suivi d’une sauvegarde est nécessaire pour modifier la fixture | Élevée / occasionnelle — testé |
| Paramètres d’univers | Nouvel avertissement occupait seulement une colonne sur desktop | Bloc étroit et inutilement haut | Élément div absent des éléments pleine largeur de la grille | Étendre la règle existante à l’avertissement | Alignement avec les groupes et absence de débordement | Modérée / occasionnelle — finition après revue visuelle |

Univers fermé : interface déjà désactivée et explication présentes, conservées après test clavier/sans JS et refus serveur. Aucun besoin de modifier cette règle. Nouveau mot de passe : gabarit conservé après clôture de sa grille, sans nouvelle retouche visuelle; correction de focus du lot 18 maintenue.

## Lot 18 — formulaires publics au clavier

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Inscription, demande et nouveau mot de passe | Après refus serveur, focus sur BODY | Le clavier reprend au début de la page, loin des champs à corriger | Nouveau document sans cible de focus après erreur | Thème partagé : autofocus natif sur le formulaire invalide, nom accessible et focus visible; Tab mène au premier champ | Focus sur Formulaire à corriger après refus, y compris sans JavaScript; aucune prise de focus ajoutée au formulaire initial valide | Modérée / occasionnelle — constatée puis corrigée et testée |

Les règles d’authentification, bornes, protections CSRF, limitations et services ne sont pas modifiés. Les grilles UI publiques connexion/inscription/demande sont closes dans le périmètre décrit dans validation.md; acheminement réel des emails, lecteurs d’écran et autres moteurs de navigateur restent hors de cette validation. Le formulaire de nouveau mot de passe reçoit la correction partagée et un contrôle de non-régression, sans clôture de sa grille complète.

## Lot 17 — paramètres et validation publique

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Paramètres d’univers | Choisir ×2 injectait 0,5 dans les quatre vitesses de durée | Le formulaire pouvait enregistrer un ralentissement au lieu de l’accélération choisie | Inversion dans Stimulus suivie de l’inversion légitime du service | Affecter la vitesse à tous les champs; conserver le service | Cinq champs à 2 puis coefficients de durée à 0,5 après sauvegarde temporaire | Élevée / occasionnelle — testé |
| Paramètres d’univers | Erreur sans restitution et formulaire long peu structuré | Ressaisie et difficulté à vérifier les changements | Redirection générale et champs non regroupés | Brouillon limité aux champs utiles, contexte rouvert, cause et succès explicites, fieldsets responsive | Zéro refusé sans écriture, saisies conservées puis correction réellement sauvegardée | Modérée / occasionnelle — testé |
| Carte d’univers | Résumé Personnalisé malgré un profil reconnu par les vitesses | Doute sur le réglage effectif | Résumé fondé sur une étiquette différente du calcul du formulaire | Même détection pour résumé et sélection | Résumé ×2 puis Personnalisé après passage manuel à ×3 | Modérée / régulière — testé |
| Lien de récupération invalide | Retour connexion de 28 px malgré une règle prévue à 44 px | Cible tactile réduite | Sélecteur historique plus spécifique | Surcharge limitée au retour de la carte publique | Tous les liens de la carte atteignent 44 px aux cinq tailles | Modérée / occasionnelle — corrigé, testé |

La 404 reste conservée après contrôle réel. Les deux écrans simples sont VALIDÉS dans leur périmètre décrit dans validation.md. Cela ne valide pas automatiquement les autres consommateurs du layout public ni l’administration entière.

## Lot 16 — création/calendrier d’univers et colonies multiples

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Création d’univers | Erreur suivie d’un formulaire réinitialisé | Ressaisie et risque de modifier le profil choisi | Redirection sans brouillon de présentation | Conserver seulement les cinq champs du formulaire en session, afficher la cause près des champs et revenir à l’ancre du formulaire | Code déjà utilisé rejeté; nom/code/profil/taille de carte conservés | Modérée / occasionnelle — testé |
| Calendrier | Erreur sans dates restituées, succès sans retour explicite et retour hors onglet | Planification difficile à vérifier | POST redirige vers l’index général | Brouillon de dates, volet rouvert après erreur, confirmation de succès et retour à la carte ciblée | Ordre de dates invalide sans modification; correction, relecture UTC et retrait de fermeture fonctionnels | Élevée / occasionnelle — testé |
| Univers fermé | Calendrier éditable malgré interdiction serveur de rouvrir | Formulaire aboutissant à un refus prévisible | Règle existante non reflétée dans les contrôles | Calendrier désactivé et explication visible; règle serveur intacte | Champs et commande désactivés, aucune réouverture | Modérée / occasionnelle — testé après rechargement réel |
| Liste d’univers | Aucun filtre Planifié, alerte de configuration fondée sur des overrides vides et date nullable | Recherche incomplète et faux avertissement sur un univers valide | États affichés absents du filtre; configuration brute confondue avec configuration résolue | Ajouter Planifié; vérifier les sections résolues et indiquer l’ouverture immédiate quand elle est prévue par la règle existante | Univers créé ouvert sans fausse alerte, puis retrouvable après planification | Modérée / régulière — testé |
| Colonies multiples | Noms longs et badge sélectionné coupés | Colonies difficiles à identifier | Titre flex non réductible dans une carte masquant son débordement | Nom sécable, conteneur réductible, badge pouvant passer à la ligne; noms accessibles contextualisés | Deux noms longs lisibles aux cinq tailles, sélection et renommage préservés | Élevée / régulière — testé |
| Colonies vides | L’en-tête construit un lien avec une planète nulle | L’état vide prévu peut être empêché par une erreur de route | Raccourci Vue générale rendu sans vérifier la sélection | Afficher ce raccourci uniquement si une planète est présente | Page vide en 200 avec action vers la carte, sans lien invalide | Élevée / rare — testé sur fixture temporaire |

Les valeurs de profil, limites, ressources et bonus de position restent produites par les services existants. Le bloc de bonus de production déjà présent dans le fichier partagé est conservé, pas attribué à ce lot.

Résolu au lot 17 : le résumé et le formulaire utilisent la même reconnaissance du profil à partir des multiplicateurs, sans modification des valeurs de jeu.

## Lot 15 — comptes, onglets admin et flottes actives

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Onglets admin | Les flèches ne partaient pas de l’onglet focalisé | Navigation clavier imprévisible | Gestionnaire porté par la navigation mais index calculé avec `currentTarget` | Identifier l’onglet depuis la cible de l’événement, ignorer les raccourcis modifiés | Droite/gauche, boucle et Début/Fin cohérents, panneau et ARIA synchronisés | Élevée / régulière — corrigé, testé |
| Comptes admin | Rôles et suspensions exécutés immédiatement, boutons peu contextualisés | Risque de modifier le mauvais compte | Formulaires directs sans confirmation et noms répétés | Confirmation native pour rôles/suspension, cible dans le nom accessible, style sensible, statut explicite et en-tête de ligne | Annuler conserve l’état; accepter respecte toujours les mêmes permissions et journaux serveur | Élevée / occasionnelle — parcours temporaires réussis |
| Sécurité / Journal | Message « aucun résultat » pouvait accompagner la vraie liste vide | Retour ambigu après examen | Ligne vide statique plus message du filtre | Message unique adapté à la présence initiale de données; bouton d’examen harmonisé | Liste vide distincte de la recherche vide, export filtré préservé | Modérée / occasionnelle — examen et CSV réussis; journal initial vide non exercé |
| Flottes actives sur mobile | Cartes dépassaient à droite avec noms de planètes longs | Statut et données coupés | Taille minimale du journal en flex propagée à la colonne de grille | Colonnes bornées, contenu autorisé à rétrécir, noms sécables et trajet en deux colonnes | Pas de débordement du document ni de texte long tronqué aux cinq tailles | Élevée / régulière — corrigé sur Flottes et accueil |
| Journal de flotte au retour | Le texte conservait l’origine→destination de l’aller | Sens du déplacement mal compris | Copie du trajet indépendante du statut | Inverser uniquement l’affichage lorsque le statut est `returning`, comme le résumé partagé | Destination initiale→colonie d’origine dans le journal de retour | Modérée / régulière — test ajouté |

## Lot 14 — technologie et cartes

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Technologie, compte neuf | Grand panneau vide avec « 0 recherches », sans explication | Impression de fonctionnalité absente | Les recherches verrouillées sont déjà masquées par la préférence utilisateur; aucun état vide dédié | Expliquer cette préférence, proposer bâtiments et préférences; ne changer aucune disponibilité | État vide compréhensible, préférence respectée et liens réels | Élevée / début de partie |
| Technologie, arbre complet | Nombreux nœuds sans recherche; défilement conservé après filtrage | Recherche lente et première fiche partiellement coupée | Diagramme horizontal sans outils de sélection | Réutiliser les filtres existants, compter les nœuds affichés, masquer les branches vides et connecteurs hors filtre, recentrer au début après filtre | Recherche/état vide/remise à zéro et fiche ouverte conservée, liens de prérequis inchangés | Modérée / régulière |
| Technologie en clair | Titres et statuts très pâles sur fond clair | Lecture difficile | Couleurs fixes du diagramme; corrections présentes dans le fichier source du thème mais absentes du fichier public servi | Texte fondé sur les tokens et états sémantiques locaux à l’arbre, dans le CSS réellement compilé | Libellés, états et liens lisibles en clair et sombre | Élevée / régulière |
| Carte classique | Dernière colonne étroite empilant les commandes et allongeant fortement les lignes | Comparaison des planètes lente sur mobile | Formulaire rapide en flex-wrap dans une colonne minimale de 170 px | Garantir l’espace du formulaire, conserver le défilement du tableau et expliquer ce défilement | Actions accessibles sans comprimer les données, aucun débordement de page | Modérée / régulière |
| V2, recherche et vues | Focus supprimé ou laissé sur un élément masqué; résultats déclarés comme listbox sans modèle clavier correspondant | Navigation clavier difficile après sélection | `blur()` et remplacement de contenu sans restitution; rôles ARIA inadaptés | Boutons natifs dans une région nommée, flèches/Entrée/Échap, compte de résultats, focus sur le titre puis restitution à la commande pertinente | Galaxie→système→planète→vue rapprochée→retour réalisable au clavier | Élevée / régulière |
| V2, panneaux et zoom | Panneaux système/planète superposés et zoom pouvant recouvrir leur fermeture | Commandes masquées ou focus sur un panneau recouvert | Positions absolues identiques ou concurrentes | Un seul panneau de détails visible; zoom repositionné selon la largeur, sans supprimer ses actions | Aucun recouvrement des commandes de zoom et du panneau aux cinq tailles | Élevée / régulière |
| V2 sans JS | Canvas sans navigation utilisable explicite sur cet écran | Parcours interrompu | Dépendance au contrôleur interactif | Lien visible vers la carte classique et explication sans JS | Retour vers une carte fonctionnelle sans script | Modérée / occasionnelle |

La représentation du canvas, les données révélées, les règles de mission, les disponibilités et les autorisations restent celles de l’existant. Les modifications d’équilibrage présentes simultanément dans le dépôt sont extérieures à ce lot.

## Lot 13 — authentification et erreurs

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Inscription | Les champs invalides référencent des identifiants d’erreur absents | L’erreur visible n’est pas correctement reliée au champ pour les aides techniques | Thème personnalisé sans les identifiants `id_errorN` attendus par le `form_row` Symfony installé | Conserver le thème et son alerte, attribuer ces identifiants à chaque erreur; partager avec la récupération | Tous les identifiants référencés par un champ invalide existent et sont visibles | Élevée / occasionnelle |
| Connexion | Erreur générique visible mais non reliée aux champs; introduction haute sur mobile | Correction moins claire et accès au formulaire repoussé | Aucun `aria-describedby`; titre immersif de 46 px sur petit écran | Relier le message aux deux champs, conserver le texte neutre et compacter uniquement l’introduction mobile | Focus email puis mot de passe au clavier; formulaire sans débordement et message associé | Modérée / fréquente |
| Récupération | Présentation et annonces d’erreurs différentes de l’inscription; peu de chemins de reprise | Parcours moins cohérent après saisie invalide ou lien expiré | Thèmes de formulaire et liens propres à chaque gabarit | Même thème d’erreurs et carte commune, liens de reprise, explication invalide/expiré/déjà utilisé | Formulaire utilisable sans JS; lien expiré mène vers une nouvelle demande | Modérée / occasionnelle |
| Accès refusé dédié | Tutoiement isolé | Ton incohérent avec les écrans d’accès | Texte spécifique à la console | Vouvoiement et carte d’accès commune | Message et retour au jeu compréhensibles, statut 403 préservé | Faible / rare |
| Erreur 403 générique | Message sur les droits même après un CSRF refusé | Mauvaise explication du refus de formulaire | Tous les 403 partagent le même texte | Évoquer autorisation ou session invalide et proposer un retour | Refus réel conservé, aucune permission modifiée | Modérée / rare |
| Erreur 500 | Mention technique de l’absence d’informations internes | Texte inutile pour reprendre le parcours | Copie orientée implémentation | Incident temporaire et action de reprise | Message sobre sans exception ni détail interne | Faible / rare |

Le layout autonome des erreurs est conservé : il fonctionne sans le shell, les données de jeu ni les assets compilés et possède déjà une action de 44 px, un lien d’évitement et un focus visible. La 404 ne nécessite pas de modification si les contrôles confirment sa lisibilité et sa navigation.

## Lot 12 — événements, retour joueur et choix d’univers

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Événements | En-tête dispersé; statut, portée et période peu différenciés | Annonce difficile à parcourir et durée ambiguë | En-tête flex à trois enfants et une seule date selon l’état | En-tête regroupé, libellés français, statut distinct, dates de début/fin avec UTC explicite, description multilignes et état vide avec retour au jeu | Seuls les événements autorisés apparaissent; dates et effet conservés, longs textes sans débordement | Modérée / occasionnelle |
| Retour joueur | Grande zone grise occupant 55 vh, priorité éloignée du type | Long formulaire et rupture visuelle, constatés à 390 px | Textarea sans thème et hauteur minimale liée au viewport | Fond thématique, hauteur adaptée, groupes réutilisés du compte, type/priorité rapprochés et aides reliées aux champs | Description lisible et formulaire utilisable sur mobile | Modérée / occasionnelle |
| Retour après erreur | Type et priorité revenaient aux valeurs initiales | Ressaisie et risque d’envoyer un classement différent | Options non liées au POST | Restitution des sélections, résumé unique des erreurs et consigne pour joindre de nouveau la capture | Erreur serveur puis envoi valide sans perte des valeurs | Modérée / occasionnelle |
| Univers | Règles en petit corps; ouverture dépendante d’un minuteur « Calcul… » et fermeture sans explication | Comparaison moins lisible et état programmé incomplet sans JS | Texte de 10 px et absence de date absolue/fallback | Corps des règles à 12 px, univers actuel identifié, date UTC et fallback sans JS, message d’inaccessibilité | Comparaison conservée, accès existants respectés, date disponible sans script | Modérée / régulière |

La grille des univers et toutes les règles comparées sont conservées : elles sont déjà structurées pour comparer deux univers, comme le montre la capture desktop avant intervention. Aucun service métier ni contrôle d’accès modifié.

## Lot 11 — catalogue administrateur et inventaire joueur

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Catalogue admin | Nombreuses entrées à parcourir sans recherche | Accès lent à une valeur précise | Navigation par famille seulement | Recherche nom/identifiant/rang, filtre famille, compteur et remise à zéro; masquage des groupes sans résultat | Une entrée retrouvée sans perdre les valeurs saisies; sauvegarde et restauration inchangées | Modérée / occasionnelle — scénarios isolés réussis |
| Inventaire | Quantité seule, effet et capacité peu expliqués | Difficile de comprendre la récompense et son attente | Présentation minimale malgré les données disponibles | Progression native, effet issu de la configuration, états vide/plein/attente et explication des règles existantes | Bonus utilisés exclus du compte, attente distincte, effet et capacité exacts | Modérée / régulière — états et responsive testés |

Captures finales revues à 1440 et 390 px pour le catalogue et à 390 px pour l’inventaire. Aucun jeu de captures avant/après disponible pour ce lot. L’inventaire conserve son périmètre de consultation : aucune nouvelle action de consommation n’est ajoutée.

## Lot 10 — navigation mobile et notes de version

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Layout admin | Navigation entière empilée avant le contenu sur mobile | Défilement important avant toute tâche | Sidebar desktop simplement empilée | Menu inline repliable sous 760 px avec bouton, Échap et gestion du focus; fallback sans JS | Titre de page avant 250 px au chargement, liens accessibles et état annoncé | Modérée / régulière — corrigé et testé |
| Notes joueur/admin | En-tête composé de trois enfants flex indépendants; titres longs | Hiérarchie et retours de ligne irréguliers | Structure différente des pages principales | Regroupement commun et retour de mots longs | Titre et description dans le même flux sans débordement | Modérée / occasionnelle — captures revues |
| Notes admin | Création et toutes les éditions déployées, textarea natif, suppression peu distincte | Liste longue et opérations difficiles à distinguer | Formulaires toujours ouverts et styles incomplets | Volets natifs, résumé titre/version/statut, champs thématiques et zone destructive séparée | Création, édition, publication et lecture préservées; confirmation de suppression conservée | Modérée / occasionnelle — parcours réussis, suppression non déclenchée |
| Création après erreur | Statut et date saisis réinitialisés dans le formulaire | Risque de devoir ressaisir ou de changer le statut involontairement | Options et valeur de date non liées au brouillon | Restituer statut/date et rouvrir le volet après POST | Statut publié et date conservés lors d’un titre vide | Modérée / occasionnelle — test isolé réussi |

## Lot 9 — conversations et compte

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Messagerie | Formulaire de signalement déployé sous chaque message reçu; textarea sans surface thématique dédiée | Lecture chargée et rédaction peu lisible | Formulaire répété dans les deux branches; style natif du textarea | Composant Twig commun dans un volet natif et textarea basé sur les tokens | Lecture sans champs de signalement ouverts; ouverture clavier et signalement fonctionnels; envoi privé préservé | Modérée / régulière |
| Profil | Identifiants, mots de passe et confirmation sans regroupement; erreur générale sans résumé annoncé | Modification et correction difficiles à comprendre | `form_widget` global sans hiérarchie métier visible | Trois fieldsets, explication du mot de passe facultatif et de la déconnexion, résumé d’erreur | Champs conservés après erreur; modification sans nouveau mot de passe et reconnexion possibles | Modérée / occasionnelle |
| Préférences | Réglages hétérogènes empilés; message d’erreur au tutoiement et contenu d’erreur imbriqué dans un paragraphe | Repérage et correction moins clairs | Regroupement incomplet dans le gabarit | Groupes affichage/horloge, raccourcis, confort; erreur en bloc d’alerte et vouvoiement | Préférences persistantes, cases associées aux labels, rendu sombre/clair et responsive | Modérée / occasionnelle |

## Lot 8 — rapports et traitement d’un retour

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Centre de rapports | Le filtre Économie gardait les combats | Catégorie affichée incohérente | La sélection ne supprimait les combats que pour Espionnage | Réserver les combats à Tous et Combats | Aucun lien de combat sous Économie, rapport conservé sous Combats | Modérée / régulière |
| Rapport de combat | Nombres bruts non alignés et unités en cellules ordinaires | Comparaison des pertes plus lente | Tableau sans présentation numérique dédiée | Séparateurs de milliers, alignement à droite, unités en en-têtes de ligne et légende | Pertes comparables, structure et défilement clavier conservés | Faible / régulière |
| Rapport d’espionnage | Lecture incompatible avec les bâtiments/recherches répartis par rang; recherches traitées comme quantités | Rapport incomplet ou erreur de rendu | Template supposant un élément plat et une quantité pour la recherche | Lire formats plat et par rang; afficher les niveaux et cartes de ressources structurées | Labels et niveaux présents dans les deux formats; cas détecté sans renseignements | Élevée / régulière |
| Détail d’un retour admin | Textarea gris natif observé, titre long susceptible de déborder | Lecture et saisie difficiles dans le thème sombre | Style des champs incomplet, titre sans retour de mot | Fond/texte/bordure sémantiques, redimensionnement vertical et titre sécable | Note lisible, titre dans le viewport, sauvegarde du statut et de la note | Modérée / régulière |

## Lot 7 — données et filtres

| Page | Problème | Impact | Cause | Amélioration | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Classement | Aucun repère visuel de tri actif | Ordre des valeurs à déduire | `aria-sort` sans traitement graphique | Flèches de tri et en-tête actif via tokens existants | Tri identifiable, liens et annonce préservés | Modérée / régulière — corrigé, rendu final à confirmer |
| Retours admin | Largeur minimale des six colonnes supérieure à l’espace disponible sur certains écrans | Champs ou bouton hors cadre | Grille rigide, sidebar et marges héritées | Grille auto-fit et champs sans marges externes | Champs et bouton dans le viewport aux six largeurs; recherche conservée après GET | Élevée / régulière — test isolé réussi |
| Retours admin local | HTTP 500 sur colonne de pièce jointe absente | Accès impossible | Ancien schéma et migration initiale déjà marquée exécutée | Migration additive conditionnelle autorisée par l’utilisateur | Page accessible et retours existants préservés | Bloquante / systématique — corrigé |
| Navigation admin mobile | Longue navigation avant le contenu, observée sur capture 390 px | Accès aux tâches ralenti | Sidebar desktop empilée sans repli | Étudier une navigation repliable dans un lot commun admin | Contenu et menu atteignables au clavier avec moins de défilement | Modérée / régulière — non corrigé |

## Observations confirmées au navigateur

Audit initial, le 5 novembre 2026. Deux surfaces ont été observées dans la session déjà ouverte à 1920 px. Les autres constats du tableau restent des axes à vérifier, pas des défauts visuels prétendument observés.

| Page | Problème | Impact | Cause | Amélioration proposée | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|---|
| Colonie, section Bâtiments `/planetes/1499?section=buildings` | Les cartes de catalogue consacrent une grande hauteur aux illustrations; les coûts et actions sont repoussés sous le pli, avec une liste de 39 éléments | Consultation et comparaison plus lentes | Grille de cartes illustrées denses observée sur la capture | Tester une carte plus compacte et une hiérarchie coût/action visible plus tôt, sans retirer les visuels utiles | Comparer le nombre d’éléments/action visibles au premier écran; vérifier lisibilité et parcours d’amélioration | Modérée / forte |
| Administration `/admin` | À 1920 px, une tuile d’état des opérations reste seule sur la rangée | Espace d’écran moins bien exploité et scan irrégulier | Grille de cartes de services avec cinq éléments observés | Vérifier grille responsive et équilibre des colonnes, notamment sur écran large | Cartes réparties sans cellule orpheline et texte lisible aux résolutions cibles | Faible / moyenne |
| Administration `/admin#admin-reports` | Avant correction, l’état vide affichait deux phrases proches alors que le filtre était vide | Retour redondant, pouvait donner l’impression d’un filtrage actif | Deux états vides visibles simultanément | Corrigé : le message par défaut s’affiche sans recherche; le message de non-résultat est réservé aux recherches | Une seule explication pertinente est annoncée quand il n’existe aucun signalement et quand la recherche ne retourne aucun résultat | Faible / occasionnelle — corrigé, reste à tester le filtre visuellement |
| Administration `/admin/events` | Le champ description était réduit à une petite zone, la case de visibilité prenait une ligne séparée et les champs étaient empilés | Saisie de description difficile et édition d’événement longue à parcourir | Le textarea n’avait pas de dimensions dédiées; le style global des champs appliquait une largeur complète aux cases | Corrigé : deux colonnes desktop, description pleine largeur, case alignée, une colonne mobile | Test à 1440/768/390 px sans débordement; textarea >240 px, case 18×18 px et bouton visible | Modérée / régulière — responsive réussi, actions de création/édition non soumises |
| Flottes `/flottes` | L’écran observé présente uniquement l’état « Aucun vaisseau disponible »; la colonne de contenu occupe une partie du grand écran | Les actions de mission ne sont pas évaluables dans cet état et la page laisse un espace important à droite | Compte observé sans vaisseaux; largeur du parcours bornée dans la vue courante | Conserver l’état vide et son lien; vérifier la largeur avec une flotte disponible et sur tablette/mobile avant de décider d’un ajustement | Avec flotte disponible, étapes et résumé utilisent l’espace de travail sans compression; état vide reste explicite | Forte / forte |
| Vue principale et détail colonie | À examiner | Non évalué | Inspection UI partielle | Vérifier la hiérarchie ressources, production, files et actions récurrentes | Actions principales repérables, aucun chevauchement aux tailles cibles, fonctions inchangées | P0 / forte |
| Flottes | À examiner | Non évalué | Inspection UI non faite | Parcours sélection → destination → mission → estimation → confirmation à observer | Étapes et erreurs compréhensibles, actions accessibles au clavier | P0 / forte |
| Autres pages de l’inventaire | À auditer | Non évalué | Inspection page par page en attente | Évaluer typographie, contraste, densité, responsive et accessibilité | Constats documentés par page avec preuve et critère de réussite | P1–P2 / variable |

## Constats issus de la lecture statique

Ces constats viennent des gabarits et scénarios de test, sans observation visuelle récente du navigateur. Ils ne valent pas validation de la page.

| Page | Problème / impact | Cause | Amélioration proposée | Critère de réussite | Gravité / fréquence |
|---|---|---|---|---|---|
| Colonies `/planetes` | Une carte rassemble plusieurs données et raccourcis; densité et parcours petits écrans inconnus | Carte répétée par colonie avec détails et formulaire de renommage replié | Examiner états mono/multi-colonies et ajuster seulement si hiérarchie ou mobile gêne l’action principale | Sélection, renommage et raccourcis identifiables sans débordement | Modérée / régulière |
| Technologie `/technologies` | Lisibilité et navigation d’un arbre potentiellement dense inconnues | Vue partagée avec dépendances/prérequis | Tester recherche, détails, prérequis et clavier; améliorer le regroupement si le scan est lent | Recherche et prérequis compréhensibles, interactions clavier utilisables | Modérée / régulière |
| Carte `/carte` | Longueur du catalogue et du tableau selon le système inconnue; risque de défilement important | Table des planètes et catalogue de systèmes présents dans la même vue | Comparer systèmes connus/inconnus et vérifier la navigation responsive du tableau | Actions accessibles, en-têtes lisibles et aucun débordement injustifié | Modérée / régulière |
| Carte V2 `/carte-v2` | Accès clavier complet aux cibles du canvas non confirmé | Aide de la carte décrit glisser, molette et clic; recherche et zoom sont des contrôles séparés | Tester recherche et clavier pour ouvrir un système et une planète; clarifier ou ajouter un chemin non gestuel si nécessaire | Parcours galaxie → système → planète réalisable au clavier, retours d’état perceptibles | Élevée / régulière |
| Accueil `/` | Avant correction, le rafraîchissement d’une zone live pouvait détacher le lien ou résumé ayant le focus clavier | Le contrôleur ne protégeait que les champs de formulaire avant de remplacer les autres zones live | Corrigé : différer la mise à jour tant que le focus reste dans la zone, puis réessayer à la sortie du focus | Lien focalisé conservé pendant une actualisation; mise à jour appliquée dès que le focus quitte la zone | Modérée / régulière — test isolé réussi; rendu visuel encore à contrôler |
| Authentification `/connexion` à `/mot-de-passe/reinitialiser/{token}` | Contrôleurs et formulaires prévoient des retours, mais leur rendu et leur compréhension après erreur ne sont pas vérifiés | États d’erreur et flash partagés par Symfony; parcours réinitialisation à deux états | Vérifier connexion invalide, erreurs de formulaire, confirmation neutre de demande et lien expiré au navigateur | L’utilisateur sait quoi corriger ou faire ensuite, sans révéler si un email correspond à un compte | Modérée / occasionnelle |
| Accès refusé `/acces-refuse` | Le texte emploie le tutoiement tandis que la connexion et la réinitialisation utilisent le vouvoiement; le ton varie | Copie de la page dédiée | Harmoniser le ton lors de la passe éditoriale, sans modifier les règles d’accès | Même registre grammatical sur le parcours d’accès | Faible / rare |
| Vue principale `/` | À 1920×860, le grand visuel de colonie occupe la majeure partie du premier écran; les raccourcis d’empire restent visibles et les panneaux d’activité commencent sous le pli | Le visuel immersif et les métriques partagent la même colonne; pas de contrôle compact effectué | Conserver la hiérarchie observée pour l’instant; vérifier ordre, densité et accès aux panneaux à 1280/768/390 px avant de réduire le visuel | Raccourcis essentiels accessibles sans ambiguïté; panneaux d’activité atteignables sans débordement horizontal aux tailles testées | Modérée / fréquente — inspection desktop seulement; focus live couvert par test isolé |
| Erreurs 403/404/500 | Les pages utilisent un mini-layout et des styles inline propres, visuellement distincts du reste du produit | `error.html.twig` est autonome, avec palette, typographie et composants locaux | Conserver le fallback autonome, puis vérifier lisibilité, focus et lien d’accueil sur les trois codes | Message et action visibles/clavier, sans fuite d’information technique | Faible / rare |
| Diplomatie `/diplomatie#alliance-guilds` | Capture 1920 px : quand l’annuaire est vide, le formulaire seul n’indique pas l’absence de guildes; l’action semble flotter dans une grande zone vide | La vue rend le formulaire de création même si `guilds` est vide, sans état vide associé | Corrigé : message de statut affiché dans le formulaire seulement si aucune guilde n’est présente | Absence explicite annoncée, action de fondation reste visible; aucun message quand la liste contient des guildes | Modérée / occasionnelle — tests de section, état vide et responsive à 390 px réussis; rendu après correction confirmé à 1920 px |
| Liste des colonies `/planetes` | À 1920 px, une carte apparaît dans la première des trois colonnes, laissant un espace libre important avec une seule colonie; l’action et les coordonnées restent lisibles | Grille conçue pour afficher plusieurs colonies; compteur et action de colonisation occupent toute la largeur | Conserver la grille pour les comptes multi-colonies; vérifier états vide/multiples et l’ordre mobile avant d’en changer la largeur | Coordonnées, statut, renommage et raccourcis restent identifiables; aucune carte tronquée ni débordement horizontal | Faible / régulière — rendu desktop vu; tests responsives sans débordement à 1440/1280/768/390 px; rendu compact non inspecté |

## Axes de contrôle

Audit visuel : hiérarchie, lisibilité, palette, espacement, navigation, tableaux, formulaires, états, densité et tailles d’écran. L’observation courante est à 1920 px seulement.

Audit ergonomique : parcours, clarté des actions, retours après action, erreurs, interactions clavier, responsive et compréhension des données de jeu.

Les pages ne seront marquées « AUDITÉE » qu’après analyse de leur code et rendu; elles ne seront « VALIDÉE » qu’après contrôles visuels et fonctionnels convenus.
