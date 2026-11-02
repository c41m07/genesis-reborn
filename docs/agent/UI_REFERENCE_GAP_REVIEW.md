# Revue comparative UI — maquette publiée / application

Date : 01/11/2026. Référence inspectée avec Chromium/Playwright : https://chili-sunset-36220944.figma.site/ (15 vues interactives, captures de référence conservées dans /tmp/figma-*.png). Comparaison app : captures Playwright de docs/validation/*-theme*.png et lecture des templates/routes.

## Inventaire écran par écran

| Vue Figma | Page réelle | Écart constaté | Action de rapprochement |
|---|---|---|---|
| Vue générale | /planetes/{id} | Structure similaire (hero, métriques, activités, colonies), mais hero et métriques racontent la colonie active plutôt que l’empire. Maquette présente 4 indicateurs globaux, activités, mouvements et journal. | Conserver métriques réelles et ressources du jeu; compléter liens/hiérarchie dashboard sans données fictives. |
| Planètes | /planetes | Catalogue réel déjà intégré avec sélection, raccourcis et places libres calculées. À 0 place libre, le CTA affichait encore « Coloniser un monde ». | Libellé désormais conditionnel : « Explorer la galaxie » à 0 place libre, « Coloniser un monde » sinon; style d’action principale et destination /carte conservés. Preuve : PHASE_12_PASS_HISTORY.md et captures planetes-empty-space-20261101-*.png. |
| Bâtiments | /planetes/{id}?section=buildings | Bandeau planète grand format et strip flottes insérés avant le catalogue; arbre de recherche sur la page qui n’est pas concernée. La maquette utilise liste à deux colonnes et file latérale. | Refaire la grille en contenu + file à droite; réserver le hero au dashboard; conserver coûts, bénéfices, prérequis et annulation. |
| Technologies | /planetes/{id}?section=technologies | Même hero/strip intrusifs; arbre avant le catalogue alors que la maquette donne priorité au résumé de prérequis et aux recherches. | Catalogue d’abord, arbre conservé en panneau secondaire repliable, file latérale persistante. |
| Chantier spatial | /planetes/{id}?section=ships | Même hero/strip; cartes très denses et 4 colonnes, maquette 2 colonnes avec filtres et file. | Grille 2 colonnes responsive, file en colonne latérale. |
| Défenses | /planetes/{id}?section=defenses | Même hero/strip; maquette met un avertissement distinct et une file latérale. | Conserver la distinction mécanique défense/bâtiment/flotte; appliquer même workspace et avertissement visible. |
| Flottes et missions | /flottes | L’assistant existe déjà, mais le viewport commence par les formulaires par colonie et la mission active est sous le formulaire; maquette a composition à gauche et missions à droite. | Workspace en 2 colonnes et hiérarchie d’étapes Figma, en gardant flotte/destination/fret/confirmation métier. |
| Galaxie | /carte | Structure de contrôles et tableau proche; libellés et contrôles de navigation différents. | Conserver distinction joueur/PNJ/vierge; harmoniser commandes et légende. |
| Rapports | /rapports | Centre et onglets proches; l’application n’a pas de vue de rapport économique et traite les filtres sans résultat réel. | Conserver les filtres raccordés aux données; états vides et cartes cohérents. |
| Espionnage | /flottes?mission=spy | Pas de compositeur spécialisé si aucune flotte n’est disponible; la maquette montre coordonnées/sondes/risque/consommation et colonne de renseignements. | Garder mission de flotte réelle; améliorer le cadrage/état vide, ne pas inventer une ressource ou envoyer des sondes fictives. |
| Alliances | /diplomatie | La bannière d’ensemble disparaissait sans guilde et l’ancre « Vue d’ensemble » n’avait pas de cible. | Bannière persistante décrivant l’état réel de l’univers; quatre ancres stables, formulaires de contrats et guildes inchangés. Testé sur univers activé et désactivé; voir PHASE_12_PASS_HISTORY.md. |
| Classements | /classement | Tableau cohérent mais local expose colonne flotte/défenses, points réels et tri, maquette affiche progression et contact. | Garder métriques/tri réels, moderniser en-tête de tableau et actions. |
| Messages | /messages | Structure maître/détail similaire; prototype utilise données fictives et entrée préremplie, app vide si aucune conversation. | Conserver la boîte d’envoi réelle, état vide, panneau détail, report/masquage et canal alliance. |
| Architecture du prototype | Pas de route de jeu | Écran de documentation du prototype, non fonctionnel dans l’app. | Ne pas exposer une fausse page joueur; documentation existante dans docs/agent/FIGMA_HANDOFF.md. |
| Paramètres | /profil/preferences et /profil | Préférences réelles, mais sans sélecteur de thème; prototype agrège thèmes et accès. | Ajouter thème clair/sombre persisté et regrouper les préférences. |
| Admin | /admin (hors prototype) | Templates dans shell joueur et entrée dans navigation de colonie; univers listés globalement mais console non séparée visuellement. | Console dédiée avec son propre layout/navigation; accès admin à tous les univers et catalogues conservé. |

## Contraintes de fidélité

- Le prototype a 4 ressources de démonstration alors que le jeu possède 5 ressources persistées distinctes. Les cinq restent visibles.
- Les noms, températures, nombres de champs et statistiques de démonstration ne sont pas repris dans l’app sans équivalent stocké.
- La fidélité porte sur la hiérarchie, les gabarits, les composants et le responsive; aucun écran fictif ne remplace une action métier existante.
- Les vues d’authentification, univers, profils, détails de rapports et administration ne sont pas représentées par une page de jeu équivalente dans la publication, elles gardent des vues cohérentes avec le système.
