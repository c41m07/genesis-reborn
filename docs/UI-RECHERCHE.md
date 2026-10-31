# Recherche UI et direction retenue — 21 octobre 2026

Périmètre : refonte du jeu existant après correction des domaines socle/auth/univers/colonie/missions logistiques/diplomatie. Les règles restent celles de REGLES.md et des décisions utilisateur. Aucun écran de combat/classement non implémenté n’est ajouté comme faux bouton.

## Sources publiques consultées

| Référence primaire | Matériel effectivement consulté | Interprétation de design pour Genesis Reborn |
|---|---|---|
| [OGame / Gameforge](https://play.ogame.gameforge.com/forgemasters), [fiche officielle](https://gameforge.com/fr_FR/play/ogame) | Présentation officielle, captures proposées, interface publique | Séparer économie, recherche et flotte ; visuel spatial fort et commandes clairement regroupées |
| [Projet Génésis](https://www.projetgenesis.com/) | Page publique et ses quatre miniatures de jeu | Structure de commandement dense, navigation stable et informations opérationnelles proches des actions |
| [Astro Empires](https://www.astroempires.com/?lang=en) | Présentation et galerie publique de cartes/construction/production/systèmes | Distinguer le contexte planétaire de la carte et des flottes ; lecture rapide des informations chiffrées |
| [Antaris Legacy](https://antaris-legacy.com/presentation) | Galerie publique (contrôle, bâtiments, technologies, appareils, missions) | Navigation latérale et catalogues cohérents, avec illustrations par famille et tableaux lisibles |
| [Fallen Galaxy](https://www.fallengalaxy.eu/) | Page officielle annonçant le retour du jeu ; interface complète non accessible | Atmosphère de transmission spatiale ; rester mesuré sur les animations et conserver les données au premier plan |
| [Astra Genesis, refonte de la vue Planète](https://www.playastragenesis.com/fr/devlog/rework-vue-planete) | Devlog officiel détaillé et captures avant/après proposées | Faire de la colonie un lieu identifiable, montrer les débits et regrouper les opérations sans ajouter de fausses règles de population ou d’emplacements |

Les observations de mise en page sont une interprétation de ces références publiques, pas un audit d’accès aux comptes de ces jeux. Les pages/captures de travail sont dans `docs/research` ; elles ne sont pas utilisées dans les assets du produit.

« Orvion » : aucune référence fiable au jeu spatial n’a été identifiée sous ce nom. Une URL a été demandée ; le site homonyme trouvé présente un puzzle, donc il n’est pas retenu comme référence spatiale. Pas de substitution silencieuse à Orion.

## Direction : un poste de commandement sobre

- Cadre bleu nuit, surfaces ardoise et lignes fines ; cyan pour la navigation/activité, ambre pour l’action, violet pour la recherche.
- Navigation latérale persistante sur grand écran et menu accessible sur mobile.
- Bandeau de ressources avec quantités réelles, production horaire et stockage. Aucune monnaie inventée.
- Vue planétaire illustrée, données de contexte réelles et sélection des colonies.
- Bâtiments/recherches/vaisseaux/défenses en cartes compactes : niveau, durée et coût, prérequis explicites.
- Files réelles intégrées dans la scène planétaire, timers et mises à jour sans rechargement ; actions conservées.
- Carte stellaire avec repères visuels et liste exploitable, sans fuite du nombre de planètes inconnues.
- Missions avec estimation autoritaire serveur : distance, ETA, carburant aller-retour et soute de fret.
- Diplomatie en français avec états compréhensibles, transfert et dissolution de guilde.
- SVG originaux créés dans le dépôt pour les icônes et l’environnement : aucune image ou marque d’un autre jeu réutilisée dans l’interface.
- Focus visible, labels, navigation clavier, réduction des animations, contraste et usage mobile vérifiés dans Chromium. Les catégories s’ouvrent par des liens Symfony et restent consultables sans JavaScript.
