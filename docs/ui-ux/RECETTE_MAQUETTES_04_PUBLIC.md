# Maquettes 04 — accès, site public et univers

Base : `cca8c6b`, direction visuelle validée dans le dossier `04_PUBLIC` de l’archive Génésis Reborn. Périmètre UI isolé : aucune route, permission, validation, règle d’univers, donnée juridique ni métadonnée d’indexation n’est modifiée. Complexités : accueil 2/5, présentation des formulaires 3/5 (messages et attributs d’erreur à préserver), sommaire juridique 2/5, choix d’univers 2/5, erreurs autonomes 1/5.

| Référence | Adaptation déployable |
|---|---|
| 01 · Accueil | Bandeau illustré, proposition à gauche, aperçu du jeu à droite. Les deux captures d’origine gardent leur lien vers le PNG de détail. Introduction et Galaxie constituent le rang suivant. |
| 02 · Connexion | À gauche, la tâche native et les reprises, à droite la scène ; sur petit écran seule la tâche est présentée. Libellés, noms de champs, autofocus, association d’erreur, visibilité du mot de passe, jeton et routes existantes. |
| 03–04 · Inscription et récupération | Planche d’états : une page est affichée à la fois dans le vrai parcours. Les formulaires courts restent centrés ; le mot de passe et sa confirmation sont côte à côte seulement en grand format. Les confirmations ne révèlent pas l’existence d’un compte. |
| 05 · Erreurs | 403/404/500 gardent un HTML autonome, un retour à l’accueil et un focus visible sans dépendance. L’accès réservé à la console garde le retour contextualisé. |
| 06–07 · Juridique | Navigation de document inchangée ; sommaire latéral et article contenant l’avertissement, sommaire puis contenu sur petit écran. Les ancres existantes de confidentialité sont conservées, les autres ne changent pas les intitulés. Le retour en haut se trouve après le contenu sur téléphone. |
| 08–09 · Univers | Un seul titre et une seule introduction, cartes avec statut avant les règles, aucun stock fictif. Les vitesses, règles, ouvertures et fermetures viennent toujours des données, l’action native distingue créer de reprendre. |
| 10 · Mobile | Variante à 390 px : la grille devient une colonne, commandes et aides s’étendent si les mots ou les erreurs sont longs. |

## Écarts volontaires et options de restauration

- Les écrans à états multiples sont des planches de revue, pas des menus simultanés dans le jeu. Aucun formulaire supplémentaire et aucun faux statut n’est ajouté pour recopier leur composition.
- Le repère « Votre empire vous attend » reste secondaire (44 px maximum contre 47 px pour le titre du formulaire) ; l’illustration n’est pas affichée en mobile. Reprendre l’emphase 52 px du dessin est possible, sans toucher au parcours, mais inverse partiellement la hiérarchie visuelle validée pour les accès.
- Le sommaire juridique n’affirme pas qu’un lien est sélectionné avant l’action du lecteur. Mise en avant au clavier ou au survol ; les clauses originales, contacts et résumés d’erreur restent visibles.
- Le sélecteur conserve le nombre d’univers réellement configurés et le chrome connecté. Les données synthétiques et la fermeture dessinée n’ajoutent pas d’univers. Les cartes se développent si un nom, un statut ou un retour serveur l’exige plutôt que de tronquer du texte.
- Les liens d’aperçu en taille réelle, le rendu HTML sans JavaScript, les notices neutres et l’option « Se souvenir de moi » sont conservés.
- Il n’y a pas de comparaison d’octets ou de latence au-delà des garde-fous existants sur les WebP ; le nouveau fond décoratif s’appuie sur le WebP de l’application.

## Recette

Les captures de la recette `entry-maquettes.spec.mjs` sont en viewport CSS **1920 × 1080, DPR 1**, sans capture pleine page, puis **390 × 844** pour les cas mobiles. Le scénario `entry-refonte.spec.mjs` renseigne l’état d’un premier compte sans empire. Les autres états et comparaisons sont fonctionnels ; vérifier les largeurs 1440/768/390/320, les deux palettes, le clavier, les ancres, les liens et les messages natifs sur copie synthétique avant toute fusion. Le gabarit 500 est vérifié statiquement ; aucun incident n’est déclenché. Les résultats d’un passage portent sur le commit de la recette, pas sur l’application publiée.
