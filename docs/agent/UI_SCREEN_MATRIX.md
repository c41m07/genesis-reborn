# Genesis Reborn — matrice des écrans

Mise à jour : 01/11/2026. Références : routes/controllers et templates du dépôt, brief Figma A3–A6, site public de prototype inspecté avec Playwright. Les données Figma sont explicitement fictives et ses actions sont simulées en mémoire ; elles ne sont pas des données de production.

| ID | Écran / route réelle | Rôle et actions | Variante prototype Figma | Desktop / tablette / mobile | États et test / preuve | Statut |
|---|---|---|---|---|---|---|
| UX-01 | Connexion /connexion, inscription /inscription | Visiteur ; formulaires, erreurs, CSRF | Pages publiques intégrées au vrai parcours d’accès | App et captures responsive; Figma ne publie pas cette vue | Inscription/connexion, CSRF PHP, password-reset-flow E2E (neutre, jeton invalide/usage unique, reconnexion); docs/capture de validation supprimee et inscription-theme.png | VALIDÉ |
| UX-02 | Vue générale / et /planetes/{id} | Joueur ; ressources, files, flottes, accès rapides | Dashboard lié aux données métier et opérations | Bureau/tablette/mobile; preuves captures historiques supprimées | Thème sombre initial, quatre métriques, colonie active, files, flotte et journal; dark-dashboard.spec.mjs valide la grille et menu fermé mobile | VALIDÉ |
| UX-03 | Planètes /planetes | Joueur ; colonies, sélection et raccourcis bâtiments/chantier/flottes, accès carte pour coloniser | Catalogue de colonies avec sélection | Bureau/mobile; captures historiques supprimées | Route dédiée; états libres calculés depuis les cibles non possédées et non PNJ; données de monde réelles, aucune température/champ inventé | VALIDÉ |
| UX-04 | Bâtiments /planetes/{id}?section=buildings | Joueur propriétaire ; niveaux, coûts, prérequis, files | Catalogue avec file latérale | Capture docs/capture de validation supprimee | Hero retiré des vues catalogue; cartes 2 colonnes et rail files/flottes; actions POST existantes conservées | VALIDÉ |
| UX-05 | Technologies /planetes/{id}?section=technologies | Joueur propriétaire ; arbre, prérequis, recherche | Résumé du catalogue puis arbre secondaire | Capture docs/capture de validation supprimee | Catalogue avant arbre replié; file et opérations dans rail; research-flow.spec.mjs valide lancement et application | VALIDÉ |
| UX-06 | Chantier spatial /planetes/{id}?section=ships | Joueur propriétaire ; construire unités | Catalogue et file latérale | Captures captures historiques supprimées | Grille de travail à deux colonnes responsive, coûts et quantités réels, file latérale | VALIDÉ |
| UX-07 | Défenses /planetes/{id}?section=defenses | Joueur propriétaire ; unités stationnaires | Catalogue distinct et file latérale | Captures captures historiques supprimées | Défenses conservées séparément des unités mobiles; grille et rail communs aux catalogues | VALIDÉ |
| UX-08 | Galaxie /carte | Joueur ; systèmes, filtres, actions selon cible | Cibles joueur, vierges et PNJ avec actions réelles | Carte desktop/mobile et routes 1440/768/390 | Responsive, access/permissions et cartes vérifiés; captures carte-theme; règles PNJ dans PHASE_05/09 | VALIDÉ |
| UX-09 | Flottes et missions /flottes | Joueur ; composition, cible, carburant, confirmation, orbite | Assistant en étapes, estimation serveur et missions réelles | Wizard desktop/mobile, mission en vol, orbite | fleet-wizard, colony et orbit-request couvrent estimation, envoi, retours, accord et état orbital | VALIDÉ |
| UX-10 | Rapports /rapports, détail combat | Participants autorisés ; détail, pertes, butin | Centre de rapports filtrable et rapport complet | Bureau/mobile; captures rapport-combat-detail et rapports-theme | Filtres, pertes, capture JcE, clavier et permissions/404 hors participants testés par conquest-report | VALIDÉ |
| UX-11 | Espionnage /rapports/espionnage/{id}, lancement via flottes | Joueur ; envoi et rapport privé | Flux réel de sonde, résolution, détection et rapport | Bureau/mobile; captures rapport-espionnage-detail | espionage-report 1/1 vérifie l’envoi, résultat détecté et confidentialité; permissions backend couvertes | VALIDÉ |
| UX-12 | Conquête JcE depuis /carte → /flottes | Joueur ; préparation, combat PNJ, capture avec développement conservé | Résultat de combat et état de capture visibles | Bureau/mobile; rapport-combat-detail | conquest-report 1/1, assertions serveur de capture et conservation du monde; focus et responsive validés | VALIDÉ |
| UX-13 | Alliances /diplomatie | Membres selon rôle ; demandes, relations, membres | Diplomatie, guildes et demandes réelles | Capture bureau/mobile, deux guildes | Création/adhésion/transfert/dissolution, alliance réciproque et orbit-request 1/1 vérifiés | VALIDÉ |
| UX-14a | Classements /classement | Joueur ; filtres et contacter | Classements réels par univers | Captures classement desktop/mobile | leaderboard-contact vérifie tri aria-sort, contact et ouverture de messagerie | VALIDÉ |
| UX-14b | Messages /messages | Joueur ; messages privés et alliance | Inbox privée et canal de guilde réel | Captures messages desktop/mobile | Envoi/réception deux comptes, signalement, masquage et permissions testés | VALIDÉ |
| UX-14c | Notifications et rapports | Joueur ; alertes et rapports non lus | Notifications Mercure et centre de rapports filtrable | Captures dashboard/rapports; états desktop/mobile | E2E Mercure réel, filtres de rapports, alertes dashboard et préférences de notifications testés | VALIDÉ |
| UX-15 | Administration /admin | Administrateur ; utilisateurs, signalements, univers, réglages | Console séparée, avec accès global aux univers | Captures docs/capture de validation supprimee et admin-catalogue-theme.png | Shell admin dédié, liste globale et liens univers/catalogue; aucun lien admin dans la navigation de colonie; admin-catalogue.spec.mjs 1/1 | VALIDÉ |
| UX-16 | Boosts /boosts | Joueur ; inventaire, événements, opérations chronométrées et consommation | Écran dédié relié à Opérations; accès mobile via Plus | Routes validées à 1440/768/390 par ui-routes-responsive.spec.mjs | Inventaire, récompenses en attente, progression des événements et actions sur files/flottes; BoostSystemTest et responsive 1/1 | VALIDÉ |

## Éléments transversaux

- Shell : logo, univers, sélection de colonie, cinq ressources persistées, horloge, notifications, compte et navigation latérale. Thème sombre par défaut; le thème clair conserve la feuille et les couleurs historiques, choix persisté dans Préférences.
- Navigation mobile du prototype : barre inférieure Accueil/Planètes/Galaxie/Flottes/Plus ; désormais implémentée sur toutes les routes authentifiées. « Plus » ouvre le menu complet. Playwright contrôle les routes à 390 px sans débordement.
- Breakpoint tablette 768 px : Playwright mesure 768 px de largeur de document à 768 px de viewport; le bouton « Ouvrir la navigation » affiche 15 entrées. Attribut aria-expanded absent sur le bouton dans le prototype.
- Contrôle clavier/lecteur d’écran du site Figma publié : non réalisé.
- Les routes d’action POST ne sont pas des écrans mais doivent apparaître dans les interactions et confirmations.
- La couverture écran par écran des états de chargement, vides, erreurs et résultats est documentée dans UI_STATE_COVERAGE.md et référencée par P12-STATE-01.


## État visuel global — 01/11/2026

Les vues du menu ont été ouvertes à 1440, 768 et 390 px par ui-routes-responsive.spec.mjs (1/1). Des captures de référence locale sont générées pour les pages stratégiques, les quatre sections de colonie, le compte, les univers, les pages publiques et l’administration (captures de validation supprimees). Les parcours principaux UX-01 à UX-15 sont validés avec les preuves listées dans chaque ligne. La couverture représentative des erreurs et états de chargement est documentée dans UI_STATE_COVERAGE.md; les quatre SVG hérités ont été remplacés et leur provenance est consignée dans ASSET_MANIFEST.md. L’audit manuel exhaustif RGAA/WCAG et lecteur d’écran reste partiel.


## Mise à jour de la navigation responsive — 01/11/2026

La barre basse du prototype est disponible sur toutes les pages authentifiées à moins de 740 px; Plus ouvre le menu latéral complet. Les ressources passent en deux colonnes. Contrôle Playwright : routes de jeu, compte, sections de colonie et pages publiques, desktop 1440 et routes authentifiées mobile 390, aucun débordement. Filtres de rapports testés en navigation. Captures mobiles de chaque route dans captures de validation supprimees. Les différences fonctionnelles observées sont maintenues dans les statuts PARTIEL jusqu’à la reprise des structures de page spécifiques.
