# Design system — Genesis Reborn

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

Les surtitres `eyebrow` sont retirés des pages. Les métadonnées utiles (dates, versions, identifiants et coordonnées) restent présentes sous une présentation ordinaire. La galaxie interactive n’affiche plus les paragraphes `gv2-access-help`. Les filtres du centre de rapports conservent leur présentation antérieure en onglets soulignés ; ils constituent une exception au style commun en pastilles.
