# Genesis Reborn — matrice des écrans

Mise à jour : 31/10/2026. Références : routes/controllers et templates du dépôt, brief Figma A3–A6, site public de prototype inspecté avec Playwright. Les données Figma sont explicitement fictives et ses actions sont simulées en mémoire ; elles ne sont pas des données de production.

| ID | Écran / route réelle | Rôle et actions | Variante prototype Figma | Desktop / tablette / mobile | États et test / preuve | Statut |
|---|---|---|---|---|---|---|
| UX-01 | Connexion /connexion, inscription /inscription | Visiteur ; formulaire, erreurs, CSRF | Entrée mentionnée dans l’architecture, aucun écran d’accès ouvert depuis le menu | App responsive à vérifier ; 1440/768/390 Figma absent | tests AuthenticationTest ; récupération de mot de passe absente | PARTIEL |
| UX-02 | Vue générale / et /planetes/{id} | Joueur ; ressources, files, flottes, accès rapides | Vue générale, Terra Nova, colonies, files et journal | Prototype mesuré 1440 et 390 ; app responsive testée | Playwright responsive 1/1 réussi ; screenshots temporaires /tmp/genesis-figma-make.png et /tmp/genesis-figma-mobile.png | PARTIEL |
| UX-03 | Planètes /planetes/{id}, sélection via barre latérale | Joueur ; colonies, champs, caractéristiques | Écran Planètes, 3 mondes, sélection et action coloniser | 1440 et 390 observés ; 768 à contrôler | prototype fictif ; test app de sélection de colonie dans GameplayAccessTest | PARTIEL |
| UX-04 | Bâtiments /planetes/{id}?section=buildings ; POST construire/annuler | Joueur propriétaire ; niveaux, coûts, prérequis, files | Catalogue économie/infrastructures et actions de production simulées | 1440 observé ; responsive app à valider | prototype ; tests PHP de files ; Playwright construction réussie avec toast après correction runner | PARTIEL |
| UX-05 | Technologies /planetes/{id}?section=technologies | Joueur propriétaire ; arbre, prérequis, recherche | Catalogue classé propulsion/militaire | 1440 observé ; 390 à contrôler | test Playwright précédent technologie rapporté, campagne à relancer | PARTIEL |
| UX-06 | Chantier spatial /flottes, données de flotte | Joueur propriétaire ; construire unités | Catalogue de vaisseaux et quantités | 1440 observé ; app responsive à contrôler | test navigateur mission complet non rejoué dans cette session | PARTIEL |
| UX-07 | Défenses /planetes/{id}?section=defenses ou catalogue | Joueur propriétaire ; produire unités stationnaires | Explicite : unités destructibles stationnaires, jamais des bâtiments/vaisseaux missionnables | 1440 observé ; 768/390 à contrôler | règle conforme dans le prototype ; PHASE_08 audite l’exclusion en flotte | PARTIEL |
| UX-08 | Galaxie /carte | Joueur ; systèmes, filtres, actions selon cible | Planètes joueur, vierges, PNJ, exploration | 1440 observé ; responsive app à contrôler | Figma indique conservation de l’économie PNJ ; parcours réel application à vérifier | PARTIEL |
| UX-09 | Flottes et missions /flottes ; POST estimation/envoi | Joueur ; composition, cible, carburant, confirmation, orbite | Formulaire en trois étapes, ressources fictives, défense exclue | 1440 observé ; 390 et clavier à vérifier | tests de flotte PHP ; E2E complet trop long/expiré | PARTIEL |
| UX-10 | Rapports /rapports, /rapports/combat/{id} | Participants autorisés ; détail, pertes, butin | Centre de rapports et filtres ; détail de combat non vérifié | 1440 observé ; variantes à tester | tests de permissions combat ; confidentialité UI à vérifier | PARTIEL |
| UX-11 | Espionnage /rapports/espionnage/{id}, action via /flottes | Joueur ; envoi et rapport privé | Formulaire d’envoi et risque estimé, rapports illustratifs | 1440 observé ; états à tester | tests d’espionnage PHP rapportés ; prototype simulations non liées au serveur | PARTIEL |
| UX-12 | Conquête JcE depuis /carte → /flottes | Joueur ; préparation, combat PNJ, capture avec développement conservé | Ligne PNJ et action de préparation ; écran résultat pas vérifié | 1440 observé ; 768/390 à contrôler | règles et backend audités phase 09 ; scénario UX non entièrement parcouru | PARTIEL |
| UX-13 | Alliances /diplomatie | Membres selon rôle ; demandes, relations et membres | Écran alliance, diplomatie et candidatures | 1440 observé ; responsive app à vérifier | Playwright social historique ; flux Figma non exécuté | PARTIEL |
| UX-14a | Classements /classement | Joueur ; filtres et contacter | Classement joueur/alliance | 1440 observé | Test navigateur courant non exécuté | PARTIEL |
| UX-14b | Messages /messages | Joueur ; messages privés et alliance | Inbox/canal alliance, envoi illustratif | 1440 observé | protections métier phase 10 ; interaction du prototype non testée | PARTIEL |
| UX-14c | Notifications et rapports | Joueur ; alertes et rapports non lus | Journal du tableau de bord avec trois alertes | Pas d’écran notification dédié confirmé | Mercure réel E2E pass après correction de l’URL publique dans compose.browser-test.yaml | PARTIEL |
| UX-15 | Administration /admin | Administrateur ; utilisateurs, signalements, univers, réglages | Réglages contiennent une mention d’usurpation admin, sans action interactive ni écran admin dédié observé | Prototype Figma incomplet ; app admin à vérifier | GameplayAccessTest contrôle l’accès admin ; équilibrage de catalogues manquant | PARTIEL |

## Éléments transversaux

- Shell Figma : logo, univers, sélection de planète, quatre ressources, horloge, notifications, compte et navigation latérale.
- Navigation mobile du prototype : barre inférieure Accueil/Planètes/Galaxie/Flottes/Plus ; Plus ouvre le menu complet. À 390 px, Playwright mesurait scrollWidth = 390, innerWidth = 390.
- Breakpoint tablette 768 px : Playwright mesure 768 px de largeur de document à 768 px de viewport; le bouton « Ouvrir la navigation » affiche 15 entrées. Attribut aria-expanded absent sur le bouton dans le prototype.
- Contrôle clavier/lecteur d’écran du site Figma publié : non réalisé.
- Les routes d’action POST ne sont pas des écrans mais doivent apparaître dans les interactions et confirmations.
- D’autres écrans d’états (chargement, erreurs, confirmation, accès refusé) doivent être ajoutés à cette matrice au fur et à mesure du parcours de l’application.
