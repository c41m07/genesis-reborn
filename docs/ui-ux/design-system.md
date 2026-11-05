# Design system — Genesis Reborn

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
