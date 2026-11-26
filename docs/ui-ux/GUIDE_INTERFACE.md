# Design system — Genesis Reborn

## Ordre des champs de ressources

- Le bandeau, la saisie du fret, les dons (Hall et projets collectifs) et les réglages administratifs correspondants présentent les ressources dans l’ordre de `ResourceService::DISPLAY_ORDER` : métal, nano, hydrogène, antimatière, électricité. L’ordre du DOM sert aussi à la navigation au clavier et n’est pas modifié en CSS. Les prix, les détails unitaires et les ressources manquantes des quatre catalogues reprennent ce même ordre, y compris après recalcul de la quantité.
- Un groupe n’affiche que les ressources que le parcours accepte ou propose déjà. Le Hall ne finance pas ses dons avec l’électricité, et un projet n’affiche pas de champ pour une ressource dont le besoin est couvert. Ce tri ne change ni les quantités ni les validations.

## Refus et actions de reprise

- Une explication de refus doit distinguer l’absence de session, un rôle insuffisant et une action refusée alors que le rôle est présent. Ne pas diagnostiquer une perte de droits à partir d’un refus de formulaire.
- Carte dédiée : anonyme → Se connecter; joueur → Retourner au jeu; administrateur → Retour à la console. Les routes et permissions restent celles de l’application. Le message global reste neutre et conserve l’information de consignation.
- En thème clair, les paragraphes et le surtitre de cette carte utilisent text plutôt que muted, trop proche du blanc. Correction locale, sans changement global de palette.
- Un aria-label doit inclure le libellé visible : Admin : ouvrir la console pour le lien Admin de l’en-tête commun. Vérifier aussi le composant sur une page de jeu consommatrice.

## Valeurs enregistrées hors de la plage éditable

- Distinguer la valeur existante et les bornes autorisées pour une nouvelle sauvegarde. Afficher les valeurs concernées, conserver le contenu des champs et expliquer le blocage; ne jamais normaliser silencieusement un réglage de jeu.
- Le composant des paramètres calcule les vitesses affichées à partir de la configuration résolue. Son avertissement possède un identifiant par univers, référencé uniquement par les champs concernés; il décrit les valeurs enregistrées, même pendant une correction non encore sauvegardée.
- La classe `admin-range-notice` partage les styles de `admin-draft-notice` et occupe toutes les colonnes du formulaire. Ce message informatif reste distinct de la cause d’un refus serveur; pas d’annonce live ajoutée au chargement.
- Univers fermé : explication et groupes désactivés conservés, sans proposition de correction hors plage. Les contrôles serveur restent indispensables même quand les champs sont désactivés.
- Sans JavaScript, une aide noscript indique que les cinq vitesses doivent être renseignées manuellement. Le formulaire reste soumis à la même validation native et serveur; le sélecteur seul ne promet pas de remplir les champs dans ce contexte.

## Focus après refus d’un formulaire public

- Le thème `form/registration_errors.html.twig` rend un formulaire soumis et invalide focalisable par programme (`tabindex=-1`), avec `autofocus` natif et le nom accessible Formulaire à corriger. Les formulaires initiaux ne reçoivent pas ces attributs.
- Le focus reçoit le même accent que les champs, sur deux pixels avec six pixels de recul. La touche Tab entre ensuite dans le premier champ; aucune interception clavier, temporisation ou dépendance JavaScript.
- Les erreurs restent associées aux champs par leurs identifiants, les valeurs non sensibles sont conservées et les mots de passe ne sont pas réaffichés. Le focus accompagne le retour serveur, sans remplacer ses validations.
- Consommateurs : inscription, demande de récupération et nouveau mot de passe. Connexion garde son autofocus email et son message neutre associés aux champs. Comportement vérifié dans Chromium, avec et sans JavaScript selon les parcours documentés.

## Paramètres d’univers et retours publics

- Le composant Twig `_universe_settings.html.twig` regroupe vitesses, limites et fonctionnalités dans des fieldsets nommés. Deux colonnes sur desktop, une sur mobile; cases natives de 18 px associées à leur texte. Univers fermé : groupes désactivés et explication.
- Les entrées affichent toutes des vitesses ×N. Le sélecteur remplit les cinq vitesses, et une saisie manuelle le remet en personnalisé. La conversion des durées appartient toujours au service existant; ne pas l’effectuer une seconde fois dans le contrôleur Stimulus.
- Le brouillon d’erreur n’accepte que les scalaires des champs autorisés, exclut le CSRF et disparaît après affichage ou succès. Zéro est conservé pour expliquer son refus, sans le remplacer par la valeur enregistrée.
- Les liens de retour des cartes publiques ont une cible de 44 px. Le sélecteur de la carte doit prévaloir sur la règle historique `.narrow > p:last-child > a`, qui fixe seulement 24 px.

## Formulaires d’univers et cartes de colonies

- Création et calendrier sont extraits en deux composants Twig, sans changer leurs routes, noms de champs, bornes ni CSRF. Le calendrier regroupe les deux dates en fieldset avec légende UTC et aide associée; une colonne sur mobile.
- Après erreur, un brouillon de présentation limité aux champs utiles est consommé au prochain affichage. Aucun jeton CSRF n’est stocké dans ce brouillon. La cause est rappelée près du formulaire et reliée par `aria-describedby`, sans seconde alerte vocale; la notification générale existante est conservée.
- Les ancres vers un formulaire ou une carte activent leur onglet parent. Les articles ciblés acceptent le focus programmatique et gardent une marge sous l’en-tête fixe. Le succès d’un calendrier est annoncé explicitement.
- Un calendrier fermé est désactivé avec une explication, conformément au refus serveur existant. La création annonce l’ouverture immédiate réellement appliquée; le filtre inclut les univers planifiés. Aucun nouveau statut métier.
- Cartes de colonies : titre réductible et sécable, badge de sélection pouvant changer de ligne, article associé à son titre et actions de renommage nommées selon la colonie. La grille comparative et les illustrations existantes sont conservées.
- Sans planète sélectionnée, le raccourci exigeant son identifiant n’est pas rendu. Le contenu et l’action de l’état vide restent utilisables.

## Opérations sur comptes et trajets de flotte

- Les onglets prennent leur position depuis le bouton focalisé; flèches, Début et Fin déplacent le focus et la sélection. Les raccourcis utilisant Alt/Ctrl/Meta sont laissés au navigateur.
- Le contrôleur réutilisable `confirmation` présente un message textuel contextualisé avant soumission d’une action sensible. Annuler empêche la soumission, sans remplacer les CSRF et autorisations serveur. Il est utilisé pour les changements de rôle et les suspensions; la réactivation reste directe. Sans JS, les protections serveur existantes restent appliquées, sans cette confirmation supplémentaire.
- Actions sur comptes : noms accessibles incluant le commandant concerné, en-têtes de ligne et statuts textuels. Le token destructif admin distingue les opérations sensibles. Corps des noms et commandes fixé à 12 px; zones d’action de 44 px minimum prévues par le style.
- Les listes de sécurité et du journal distinguent absence de données et recherche sans correspondance, avec un seul message. Le CSV conserve son comportement de lignes visibles.
- Trajets : deux colonnes réductibles séparées par une flèche, noms longs sécables et grilles bornées. Le composant de flotte reste commun à l’accueil et à la page Flottes. Dans le journal, l’affichage du retour suit la même orientation que le composant partagé.

## Diagrammes et cartes interactives

- Recherche de collection : l’arbre réutilise le contrôleur `admin-filter` et la barre de filtres déjà construite pour le catalogue. Le nom historique du contrôleur ne limite pas son usage aux administrateurs. Pas de second moteur de filtrage.
- Arbre : `details` natifs pour chaque recherche; région horizontale nommée et focalisable. L’état vide distingue la préférence masquant les recherches verrouillées d’une recherche sans résultat. Les branches et les lignes décoratives suivent les nœuds visibles; le filtrage conserve les fiches ouvertes.
- Couleurs du diagramme : texte et séparation utilisent les tokens communs. Deux états sémantiques locaux (`--tech-available`, `--tech-missing`) disposent de valeurs claires lisibles; les statuts restent textuels. La correction est dans le CSS compilé, sans dupliquer le thème public.
- Carte classique : tableau sémantique et conteneur de défilement conservés. Les formulaires rapides disposent d’au moins 300 px dans leur colonne pour éviter un empilement excessif; le tableau peut défiler sur petit écran.
- V2 : résultats de recherche sous forme de boutons natifs, région nommée et compteur annoncé. Flèches pour parcourir, Entrée pour ouvrir, Échap pour fermer. Aucun faux rôle de liste à options.
- Les panneaux de détails sont non modaux : titre focalisé à l’ouverture, retour à la planète ou à la recherche lors de la fermeture, aucun piège de focus. Un seul panneau est visible à la fois. Les contrôles de zoom restent disponibles et sont déplacés pour éviter le panneau; disposition horizontale mobile.
- Un chemin visible vers la carte classique complète la recherche au clavier et sert de repli sans JavaScript. Le canvas et sa palette spatiale existants sont conservés.

## Accès et messages d’erreur

- Cartes publiques : `auth-card` complète le panneau étroit existant; titres sécables, liens de reprise d’au moins 44 px. La connexion conserve son introduction sur desktop et la compacte sous 600 px.
- `auth-form` et le thème `form/registration_errors.html.twig` partagent les retours de l’inscription et de la récupération. Chaque erreur reçoit l’identifiant `id_errorN` déjà référencé par Symfony; `aria-invalid` et `aria-describedby` restent produits par les lignes du formulaire natif.
- Erreur de connexion : un message neutre unique, relié aux deux champs; aucun détail sur l’existence d’un compte. Les valeurs, noms et attributs autocomplete/CSRF existants sont préservés.
- Les erreurs de champ combinent texte, repère de bordure et annonce; la couleur seule ne suffit pas. Aucun ajout d’animation ou de dépendance.
- Les erreurs serveur gardent leur layout autonome existant, pour pouvoir s’afficher sans les données de jeu ou la compilation frontend. La palette sombre, le lien d’évitement et le bouton de retour restent communs aux 403/404/500.

## Annonces, formulaire de retour et choix d’univers

- Événement : carte avec statut textuel, type et portée, titre associé par `aria-labelledby`, description avec retours à la ligne, effet existant et période début/fin. Dates sémantiques `time`, format UTC explicite. Grille des périodes sur deux colonnes, une sous 600 px.
- Formulaire de retour : réutilise `account-panel`, `account-section` et `account-grid`; ne crée pas un second système de formulaire. Textarea thématique redimensionnable, aides `aria-describedby`, résumé d’erreurs annoncé et sélections restituées après erreur.
- Univers : conserver la grille comparative et les `dl` existants. Le marqueur d’univers actuel combine bordure et texte; une ouverture programmée présente une date absolue et le minuteur existant. Sans JS, la date reste lisible.
- Ces styles réutilisent les tokens de panneau, ligne, texte, accent et statut existants, en sombre comme en clair.

## Recherche de catalogue et capacité d’inventaire

- Le contrôleur partagé `admin-filter` accepte un texte de recherche explicite, des groupes et leurs ancres. Les filtres masquent les éléments sans les recréer : les saisies non enregistrées sont conservées. Le compteur est annoncé poliment; la remise à zéro restitue le focus à la recherche.
- Barre de recherche : labels visibles, grille sur desktop, une colonne sous 760 px, styles fondés sur les tokens existants. Sans JavaScript, la barre est cachée et toutes les entrées restent disponibles.
- Édition de catalogue : grilles de champs auto-fit, une colonne sous 520 px; styles désormais centralisés et limités au catalogue.
- Capacité : élément HTML `progress` nommé, doublé d’un compte textuel. Les récompenses en attente ont un texte explicite et un repère latéral; la couleur seule ne porte pas l’information. Le pourcentage affiché vient du service existant.

## Navigation admin et édition de notes

- Menu admin inline : sous 760 px, bouton de 44 px minimum, panneau replié initialement, états ARIA synchronisés, Échap et retour de focus. Navigation permanente au-dessus; tous les liens restent visibles sans JavaScript.
- Le panneau n’est pas une modale et ne piège pas le focus. La destination principale accepte un focus programmatique pour les liens internes.
- Notes : création et édition en `details` natifs; résumé titre/version/statut, formulaire de contenu pleine largeur et suppression dans une zone séparée. Lecture joueur via un `details` avec nom contextualisé.
- Token destructif admin `--danger` : `#ff8f8f` en sombre, `#9f2936` en clair. Contrastes calculés sur les panneaux prévus, non mesure globale du rendu.

## Formulaires de compte et conversations

- `account-panel` : panneau de compte borné à 900 px, champs organisés en deux colonnes puis une sous 600 px.
- `account-section` : fieldset et legend pour regrouper les champs associés; espacement et séparateur communs. La confirmation du profil explique la déconnexion existante.
- Résumé d’erreur dans un bloc `role="alert"`; erreurs natives Symfony et identifiants des champs conservés.
- `ui/message_report.html.twig` reçoit `message` et `peer_id` : même formulaire de signalement pour privé et guilde, dans un `details` natif. Le champ de motif ne s’affiche qu’après ouverture; jeton, action et identifiant du message conservés.
- La rédaction emploie les tokens `--bg`, `--text` et `--line`, qui suivent le thème. Les noms longs peuvent revenir à la ligne dans l’en-tête de conversation.

## Rapports et suivi administrateur

- Données numériques comparables : chiffres tabulaires, séparateurs de milliers et alignement à droite dans le tableau des pertes; l’unité est un en-tête de ligne.
- Renseignements : cartes de rang avec titre, badge de rang et niveau; ressources dans une liste de définitions stock/production/capacité. Chaque catégorie garde son titre et son état vide.
- Textarea de suivi : fond `--bg`, texte `--text`, bordure `--line`, hauteur minimale 10 rem, redimensionnement vertical; labels sans marges externes dans la grille du formulaire.
- Les titres de dossier issus des joueurs peuvent revenir à la ligne même lorsqu’un mot est très long.

## Fondations observées dans le code

Les surfaces joueur et admin partagent les tokens de `assets/styles/app.css`. Leurs contrastes restent à mesurer avant validation globale.

| Token | Valeur | Usage |
|---|---|---|
| `--bg` | `#090f1b` | Fond global |
| `--panel` | `#101b2b` | Panneaux |
| `--line` | `#243247` | Séparateurs |
| `--text` | `#e7eef6` | Texte principal |
| `--muted` | `#9aabc2` | Texte secondaire |
| `--accent` | `#77d5e9` | Liens et focus |
| `--gold` | `#e7b76b` | Actions et tri actif |
| `--green` | `#87d8b4` | États positifs |

- Typographie actuelle : Manrope, repli system-ui; base 13 px, titres principaux 36 px, secondaires 18 px.
- Focus commun : contour de 2 px en accent, décalé de 4 px. Audit clavier complet en attente.
- Tableau triable : conserver les en-têtes `scope`, `aria-sort` et les liens réels. Indicateur décoratif masqué aux lecteurs d’écran; fond de l’en-tête actif teinté via `color-mix`. Conteneur de défilement conservé pour les données larges.
- Filtres admin : colonnes auto-fit d’au moins 160 px selon l’espace disponible; deux colonnes sous 900 px et une sous 600 px. Espacement géré par le conteneur, sans marges externes sur les labels et champs.

## État initial

Les fondations existantes doivent être relevées dans `assets/styles/`, `assets/app.js`, les contrôleurs Stimulus, `templates/base.html.twig` et `templates/admin/layout.html.twig` avant toute normalisation. Ce document n’invente pas de tokens ni de composants qui n’ont pas encore été inspectés.

## Principes retenus pour l’audit

- Identité spatiale moderne, sobre et lisible; garder les accents décoratifs au service de la hiérarchie.
- Préserver les conventions Symfony, Twig, Bootstrap/SCSS et Stimulus effectivement présentes.
- Partager les fondations joueur/admin; différencier leur navigation et densité quand l’usage l’exige.
- Conserver labels, focus clavier, erreurs explicites et états accessibles.
- Centraliser les tokens après relevé des valeurs existantes et valider leurs contrastes avant adoption.

## À documenter après inspection

Couleurs sémantiques; typographies et échelle; espacements/grille/breakpoints; rayons/ombres; icônes; focus, hover, disabled et erreurs; règles de mouvement; boutons, champs, tableaux, filtres, cartes, onglets, menus, modales, notifications, alertes, badges, pagination, progressions, états vides et chargements.

### Onglets et filtres en pastilles

La référence visuelle commune est le filtre de catalogue : pastilles arrondies, contour discret, fond transparent et état actif cyan légèrement teinté. Utiliser la classe partagée `ui-tabs` et les règles de `assets/styles/tabs.css` pour les onglets de page, les filtres de catalogue et les familles de l’administration. Les pastilles passent à la ligne sur petit écran ; le focus clavier reste visible. L’état actif s’appuie sur `aria-current`, `aria-selected` ou `aria-pressed` selon le comportement existant. Les thèmes clair et sombre utilisent leurs variables de couleur.

### Ajustements des en-têtes et rapports

Sur téléphone, dans les catalogues Bâtiments, Recherche, Chantier et Défenses, la flèche Ressources ouvre ou replie ensemble les détails de stock et les Files de la colonie. Le résumé des stocks et le suivi des vols restent accessibles. Sans JavaScript, ressources et files restent visibles ; le bureau conserve sa disposition.

Les surtitres `eyebrow` sont retirés des pages. Les métadonnées utiles (dates, versions, identifiants et coordonnées) restent présentes sous une présentation ordinaire. La galaxie interactive n’affiche plus les paragraphes `gv2-access-help`. Les filtres du centre de rapports conservent leur présentation antérieure en onglets soulignés ; ils constituent une exception au style commun en pastilles.

### Guildes et relations — composition B validée pour L1A

Ma guilde, Membres et Relations entre commandants utilisent les compositions
de `guild-workspace.css` : bannière compacte, canal en action principale,
échanges prioritaires, membres en tableau adaptatif et relations en cartes.
Les surfaces, polices et couleurs reprennent les variables du jeu ; les repères
de relation comportent toujours du texte, et les initiales sont décoratives.
Les images personnalisées restent celles de la guilde ; l’emblème existant
sert de repli. Aucun visuel ou logo d’identité n’est remplacé.

Sur ces pages, le menu « Pages de l’espace » indique la page active sous 600 px
et déplie les liens réels. Il reste ouvert sans JavaScript ; Échap ferme le menu
et rend le focus au résumé. Le changement de format replace le focus sur un
élément visible. Une seule destination diplomatique reste directement affichée
pour les joueurs sans guilde. Le thème clair reprend ses variables existantes.
Ces choix restent limités au [lot L1A](../REFONTE_VISUELLE_INTERFACES.md).

### Découverte et création de guilde — composition A validée pour L1B

`guild-discovery.css` compose les résultats en lignes et aligne Présentation,
Contact et Adhésion sur ordinateur ; les actions sont empilées sur mobile. La
création garde les labels et aides à proximité des champs, avec une aide latérale
sur ordinateur. Les liens de l’espace restent visibles, sans menu supplémentaire.
La fiche conserve le nom de guilde comme titre principal, ses visuels réels ou
l’emblème de repli, puis présentation et commandant en sections successives.
Les thèmes utilisent les variables existantes. Le brouillon de nom/tag est
échappé et conservé après erreur ; il ne remplace pas la validation serveur.

### Gestion de guilde — composition A validée pour L1C

`guild-control.css` conserve les grades à gauche sur ordinateur puis au-dessus
du formulaire sur petit écran. Nom, priorité et sept permissions restent dans le
même formulaire. Les réglages de tag/transfert sont visibles ; les actions
sensibles utilisent un dépliable décrivant leurs conséquences avant l’envoi.
Les membres consultent les grades et disposent du départ, selon les droits
existants. Les traités restent en lignes avec relation actuelle et proposition
distinctes. Description et fichiers restent groupés dans le formulaire de
personnalisation ; les fichiers s’empilent sur mobile.

Le menu natif est partagé dans `space-navigation-menu.css`. Sans JavaScript,
les liens restent dépliés ; son comportement au clavier, au redimensionnement et
au retour de focus reste celui validé en L1A. Les confirmations utilisent le
comportement natif de `details`, sans nouvelle dépendance.

### Missions et préparation — composition A validée pour L2A

`fleet-workspace.css` reste limité à `.fleet-redesign` : missions, préparation et
rappel. Les vols personnels utilisent des lignes à deux colonnes sur grand écran,
puis une colonne sur mobile ; le bouton Composition et fret reste distinct du
rappel. Les composants de Vue générale et des catalogues gardent leur disposition.
Les arrivées conservent leur panneau séparé, même vide.

La préparation place ses trois missions résumées et ses arrivées à droite sur
grand écran, après le formulaire sur petit écran. Résumé et estimation sont côte
à côte lorsque la largeur suffit. Le fret est déplié pour les missions qui le
permettent. Le changement explicite d’étape place le focus sur son titre ; les
erreurs ciblent toujours le champ concerné. Une actualisation ne prend pas le
focus. Le menu mobile reprend `space-navigation-menu` et ses liens serveur ;
sans JavaScript, le menu et les trois étapes restent lisibles. Les confirmations
et états de blocage de devis conservent leurs contrôleurs existants.

### Orbites, quai et reprise — composition A validée pour L2B

`fleet-harbour.css` étend le socle L2A à ces trois destinations. Les demandes
sont visibles sans ouverture préalable ; origine/statut et échéance/actions
occupent deux colonnes sur grand écran, puis une sur mobile. Le compteur garde
son actualisation même sans dépliable. Aucune réponse n’est présentée lorsque
le serveur ne l’autorise plus.

Le quai place les unités et la réserve côte à côte sur ordinateur. Les
stationnements en attente gardent une section large portant « tout l’empire » ;
leurs détails restent dépliables. En l’absence de réserve, les unités reprennent
la largeur disponible. La reprise garde choix et devis sur deux colonnes, puis
les empile sur petit écran. Le calcul ne soumet pas la confirmation ; erreurs,
versions, liens serveur et formulaires sans JavaScript sont conservés.


### Vue générale — référence validée, finalisation du 18 novembre 2026

La page existante conserve son URL. Une alerte de stockage ou de production
précède la bannière ; elle disparaît en vacances. La bannière regroupe la colonie
active, les points quotidiens de l’empire et quatre accès : Planètes, Classement,
Flottes et Guilde. Les chiffres proviennent des données existantes ; la maquette
ne fournit aucune valeur de jeu. Les points sont répartis en quatre colonnes,
puis deux sur petit écran.

L’activité de la colonie reste à gauche sur grand écran ; chaque file offre un
lien vers sa page dédiée, même à vide. Les détails et annulations conservent leur
comportement. Les arrivées précèdent les vols personnels dans la colonne de
suivi. Sur petit écran : activité, arrivées, vols, journal. Le journal reste en
pleine largeur. Aucune rubrique n’est remplacée par un faux onglet JavaScript.

Les composants partagés conservent leurs liens dans les en-têtes, y compris
après actualisation. Le bouton global des événements et la barre de ressources
restent dans leur emplacement commun. Ces adaptations à la maquette évitent de
modifier le fonctionnement des autres vues. Le style est limité à `.overview-page`.


### Compositions de flotte — proposition A validée pour L2C

Un modèle par ligne sur ordinateur : contenu à gauche, chargement et gestion à
droite. Sur petit écran, les actions suivent le contenu. La suppression conserve
sa confirmation native ; Modifier garde le nom du modèle dans son nom accessible.
La création, la modification et le chargement restent des pages dédiées, avec
le menu mobile commun à Flottes. La liste garde sa propre page d’entrée.

L’éditeur conserve un tableau sur grand écran et regroupe les trois rangs sous
chaque vaisseau sur mobile. Une seule série de champs est rendue ; libellés,
en-têtes et rôles restent accessibles. Le choix de colonie et ses disponibilités
sont côte à côte lorsque la largeur suffit, puis empilés. Les colonnes Demandé,
Chargeable et Manquant deviennent des libellés visibles sur chaque groupe mobile.
Les manques conservent texte et couleur ; le lien de chantier reste souligné.
Les styles sont limités à `.fleet-compositions`, sans changer les règles de chargement.


### Inventaire — proposition A validée pour L3

La capacité commune occupe toute la largeur sur Boosts, Boîtes de ressources et
Récompenses en attente. La navigation mobile réutilise le menu natif partagé et
conserve les compteurs ; ceux-ci sont optionnels pour les autres espaces.
Le choix d’opération reste un filtre de la même tâche, dans la page Boosts.
Stock et utilisation sont côte à côte lorsque la largeur suffit. Le sélecteur
reste masqué sans JavaScript : les formulaires de toutes les opérations restent
alors visibles. Les revues existantes conservent leurs boutons et le retour du focus.

Chaque boîte garde contenu et destination ensemble, sur deux colonnes puis une.
Toutes les ressources attribuées restent visibles, y compris les valeurs nulles.
Les conditions d’ouverture restent près du bouton. L’attente conserve une liste
ordonnée native, sa numérotation et sa pagination, avec une ligne par récompense.
L’aide complémentaire reste dépliable. Les styles sont limités à `.inventory-redesign`.

## Mon compte — proposition A du lot L4

Quatre pages dédiées : identifiants, préférences, vacances et suppression.
Navigation secondaire commune repliable sur mobile, ouverte sans JavaScript.
Sur grand écran, le profil, les vacances et la suppression rapprochent les
informations et leur confirmation ; une colonne sur tablette/mobile.
Le profil garde un seul formulaire, son mot de passe facultatif dépliable et
la confirmation par mot de passe actuel. Les préférences gardent un seul
bouton d’enregistrement. Portées distinctes visibles : réglages communs au
compte, vacances de l’univers sélectionné, suppression dans tous les univers.
Actions irréversibles identifiables, labels/focus/erreurs conservés ; styles
isolés dans `account-workspace.css`. Résultats de recette et captures dans le
[suivi de refonte](../REFONTE_VISUELLE_INTERFACES.md).

## Projets collectifs — proposition A du lot L5

Trois listes dédiées et une fiche par projet. Deux menus natifs partagés : guilde,
puis projets ; repliables sur mobile et ouverts sans JavaScript. Listes en lignes
sur grand écran : identité, financement et minimums personnels. Modèles avec
conditions de récompense visibles avant lancement ; paliers dépliables. Fiche
active avec progression à gauche et don à droite, puis une colonne ; aucun
formulaire sur une fiche close ou pendant les vacances. Maximums de don en texte,
libellés et confirmations existants. Styles limités à `.project-redesign` ;
[recette et captures](../REFONTE_VISUELLE_INTERFACES.md).

## Événements et Défis — proposition A du lot L6

Deux listes dédiées et une fiche par défi, avec menu secondaire natif partagé.
Annonces et bonus en lignes, dates regroupées ; défis avec identité, progression/
récompense et accès rapprochés sur grand écran. Fiche avec objectif/récompense
à gauche et participation/action à droite ; une colonne sur tablette/mobile.
Sections En cours/À venir sur le même document, avec ancres et paginations
indépendantes. Les données personnelles et les états des objets restent visibles,
y compris un objet déjà acquis avant annulation. Les dates UTC des fiches et le
résumé utilisé par l’administration sont conservés. Styles limités à
`.event-redesign` ; [recette et captures](../REFONTE_VISUELLE_INTERFACES.md).

## Colonies — proposition A du lot L7A

Planètes conserve deux présentations du même ensemble : cartes et comparaison,
avec `view=table` et `resource`. Cartes horizontales sur grand écran : illustration
existante, identité et informations, puis actions ; une colonne sur tablette/mobile.
Renommage et bonus/malus restent dépliables, stocks/vaisseaux dans le panneau
existant utilisable au clavier et au toucher. Comparatif sémantique à cinq ressources
sur grand écran, à une ressource sélectionnée sur format étroit, avec formulaire
GET de repli sans JavaScript.

L’abandon garde une page dédiée, son bilan complet et ses refus sans divulgation.
Confirmation à droite sur grand écran, sous le bilan sinon ; aucune information
nécessaire n’est cachée. Le nom, les champs de protection et les destinations
existantes sont conservés. Styles limités à `.colony-redesign`, sans modification
de la Vue générale ; [recette et captures](../REFONTE_VISUELLE_INTERFACES.md).

## Catalogues du cœur de jeu

- Dans les quatre catalogues, un badge à côté du rang montre le niveau actuel (bâtiments et recherches) ou le nombre d’unités en réserve ou installées (vaisseaux et défenses). Le zéro est visible mais discret si le rang est affiché. La flèche verte reste visible quand la commande est disponible.
- Les grandes valeurs suivent la notation compacte du jeu ; la description accessible de l’onglet conserve la valeur exacte, même pour un rang verrouillé. Les libellés peuvent s’abréger lorsqu’ils manquent de place, sans changer le nom accessible du rang. Le badge n’ajoute aucun arrêt au clavier. La carte du rang choisi garde son compteur détaillé et ses prérequis.
- « Prérequis manquants » ouvre une liste de liens en superposition. Le rang verrouillé garde son libellé et son illustration passe du noir et blanc à la couleur au survol ou au focus.
- Bâtiments et Recherche gardent le même ordre de lecture : intitulé et rang, coût et temps du niveau visé, effet principal, puis action. Le détail reste replié par défaut. Le mode condensé garde l’effet principal et la même action native.
- L’effet, son avant/après et les valeurs acquises viennent de la projection du catalogue. Une production nominale de la colonie ne décrit pas le débit net après consommations. Un maximum déjà en file n’est pas présenté comme acquis ; les commandes et la durée déjà engagées restent dans les files.
- Deux bandeaux courts identifient le pont d’ingénierie et le laboratoire colonial. Leur illustration est décorative, avec un fond thématique qui découple la lisibilité du texte de l’image. Le cyan indique la sélection ; l’or marque la commande principale disponible, en complément de son libellé.
- Chantier et Défenses : une caractéristique de l’unité précède le coût de la commande. Un nombre nul de vaisseaux en réserve ou de défenses installées n’est pas un verrou. Max remplit la quantité du devis sans jamais passer la commande ; lorsqu’aucun lot n’est disponible, il est désactivé. Le prix d’une unité reste dans les détails et ne varie pas avec la quantité saisie.
- Préparation de flotte : conserver les trois étapes et la grille de quantités/stock/Max par rang. Le récapitulatif est une lecture du formulaire, à droite sur grand écran, au-dessus près de la grille sur les formats étroits, et sans autre bouton d’envoi. Coût et durée ne sont confirmés que dans l’estimation existante.
- Galaxie : outils regroupés sans bandeau illustré. L’inspecteur est dans le flux, à droite sur grand écran, au-dessus de la carte sur formats étroits ; ni la fiche ni ses actions ne couvrent le canvas. Le cyan relie le système sélectionné et sa fiche. Entrer dans le système reste une action séparée et ne doit pas se confondre avec la sélection, le zoom ou la liste classique. L’aide Premiers pas n’occupe pas la carte d’ensemble ni la liste ; elle reste disponible dans les vues locales du système et des planètes. L’anneau vert signale toujours les systèmes avec une colonie personnelle. Dans un système connu, le Hall installé est un repère fixe près de l’étoile. Au survol du système, les coordonnées sont précédées du tag de la guilde si elle en a un. Les flottes dont le trajet local est connu sont représentées par une flèche orientée dans le sens du vol, entre les planètes ou entre une planète et la limite du système. La station pirate est illustrée à part, seulement pour le joueur dont la reconnaissance a déjà révélé sa présence. Le repère pirate n’est pas une case planétaire. Les illustrations des stations ne dépassent pas les globes ; le survol et le focus affichent leur détail. Activer le Hall ouvre la présentation de sa guilde. Activer la station pirate ouvre la campagne.
- Vue générale : bandeau de la colonie et « À surveiller » ouvrent le poste de commandement. Le détail des points se déplie au-dessus du résumé pour garder le bandeau à sa hauteur initiale. Le panneau suit tout l’empire dans l’univers sélectionné : une attaque effectivement signalée d’abord, puis deux prochaines fins/arrivées connues et la colonie correspondante. Les stocks/restrictions de production restent des informations de la colonie active. Ne pas dater une file en attente ou une recherche dont la colonie n’est plus contrôlée. Journal, files locales, arrivées et propres flottes gardent leurs espaces distincts.

## Parcours connexes

- Rapports : distinguer la portée (actifs ou archives), le filtre et les actions de sélection ; préserver les onglets soulignés. Le statut du transport se lit à côté du trajet, avant le bilan des ressources ; une information non obtenue par espionnage n’est pas un zéro.
- Guildes : conserver bannière compacte et accès au canal ; distinguer tag réellement configuré, nombre de membres, rôle et relations. L’emblème de repli est décoratif, sans droits ni état déduits du visuel.
- Événements : un seul repère de navigation entre la programmation et les défis/rotations. Le nombre d’événements appartient à sa période et ne prétend pas compter les défis.
- Accès : ordre formulaire puis scène ; la création de compte dans le menu reste secondaire pendant une tâche d’authentification. Les demandes courtes gardent le formulaire et l’aide sans répéter l’illustration.
- Visuels décoratifs validés : le portrait « Accès au commandement » est partagé par Connexion et Inscription seulement ; la scène reste masquée en présentation étroite. Le panorama de l’Avent reste derrière le titre HTML et les liens, avec un voile et un cadrage mobile dédiés. Les variantes WebP sont choisies par le navigateur ; aucune illustration n’est indispensable pour utiliser le formulaire ou le calendrier.
- Administration : mettre l’univers concerné dans le contexte du catalogue, puis comparer champ, durée et coûts dans le même ordre « Actuel / Proposé ». Les hypothèses du devis et la confirmation restent distinctes du calcul de jeu.


## Assistant du simulateur de combat

- Une étape montre la même catégorie pour les deux camps, côte à côte sur ordinateur, puis empilés sur téléphone. Chaque tableau affiche toutes ses lignes et défile avec la page. Les ancres et tous les champs sont rendus côté serveur ; JavaScript ne fait que guider l’affichage et placer le focus. Un champ invalide ramène à son étape. Le résultat reste sur la même page.
- Les sources sont deux actions explicites : la copie de la colonie reprend seulement les vaisseaux disponibles et les recherches du membre ; le rapport reste limité à l’instantané de l’émetteur. Appliquer une source conserve l’autre camp et ne produit ni mission ni calcul.
- La date et le lien restent associés au rapport ; un renseignement non révélé n’est pas affiché comme zéro. Un champ laissé inconnu compte pour zéro pour le calcul seulement après confirmation. Les défenses fixes et la forteresse appartiennent uniquement au défenseur.

## Suppression depuis les archives de rapports

La suppression est proposée dans l’onglet Archives personnelles uniquement, dans
le même formulaire que la restauration. Par ligne et par sélection, les actions
ont des libellés distincts. La confirmation native précise le caractère définitif
du retrait des archives. Le formulaire reste utilisable sans JavaScript ; la note
d’aide précise l’effet. Les commandes se replient sous le rapport sur mobile.
Seule la visibilité personnelle change : la fiche du rapport, les marqueurs
administratifs et la visibilité des autres joueurs ne sont pas modifiés en base.
