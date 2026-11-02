# Phase 12 — Couverture des états UI

Mise à jour : 01/11/2026. Ce registre complète UI_SCREEN_MATRIX.md par les états de chargement, vides, erreurs et résultats réellement vérifiés.

## Chargement et stabilité

Les routes sont rendues côté serveur. Le contrôle Playwright ouvre les 17 routes authentifiées suivantes, vérifie une réponse 200, le repère principal et son titre H1, l’absence d’image cassée et de commande sans nom dans l’arbre d’accessibilité Chrome, ainsi que l’absence de chargement bloqué (aria-busy=true ou data-loading=true) et d’erreur JavaScript : /, /planetes, les cinq vues de colonie (/planetes/{id} et buildings, technologies, ships, defenses), /carte, /flottes, /rapports, /diplomatie, /messages, /classement, /univers, /profil, /profil/preferences et /boosts. Le test confirme aussi les états vides du tableau ci-dessous. Il n’existe pas de spinner global de navigation : les pages arrivent avec leur HTML complet. Les requêtes métier asynchrones sont vérifiées dans leurs tests de parcours dédiés.

## Écrans et états

| Écran | Vide vérifié | Erreur, indisponibilité ou validation | Confirmation / résultat vérifié | Chargement |
|---|---|---|---|---|
| Connexion et inscription | Non applicable | Champs invalides, erreur de connexion, CSRF; récupération neutre, jeton invalide/expiré ou déjà utilisé | Inscription, connexion et changement du mot de passe | Navigation HTML; auth-validation-states, password-reset-flow |
| Accueil / vue générale | Message « Aucune activité récente » sur nouveau compte | Flashs de traitement dans le shell; aucun scénario de panne de source distinct | Dashboard, ressources, files et notifications; mise à jour live | Route /; contrôle anti-chargement bloqué |
| Planètes | Non applicable sur un compte initial avec planète garantie | Permissions / id d’accès contrôlés côté route; page 404 commune | Liste et sélection de colonie | /planetes; contrôle anti-chargement bloqué |
| Colonie — vue générale | Files et activité de nouveau compte | Actions protégées par session, CSRF et règles métier; branches détaillées pas toutes injectées au navigateur | Files et données réelles de colonie | Route /planetes/{id}; même contrôle |
| Bâtiments | Catalogue toujours peuplé; queue vide affichée | Préconditions et erreurs POST par la logique serveur; inventaire exhaustif de chaque échec non simulé | Ajout à la file et mise à jour de colonie (colony.spec.mjs) | Route ?section=buildings; aucun chargement bloqué |
| Technologies | Arbre et catalogue présents | Prérequis bloqués présentés sur chaque fiche; toutes les variantes d’échec serveur ne sont pas rejouées | Lancement et achèvement (research-flow.spec.mjs) | Route ?section=technologies; aucun chargement bloqué |
| Chantier spatial | File vide et catalogue visible | Quantités/prérequis bornés côté formulaire et serveur; pas de matrice complète de chaque erreur de quantité | Construction, flotte et estimation via colony.spec.mjs / fleet-wizard.spec.mjs | Route ?section=ships; aucun chargement bloqué |
| Défenses | File vide et catalogue visible | Préconditions indiquées sur les fiches; chaque rejet métier n’est pas simulé | Construction de défense via parcours de colonie | Route ?section=defenses; aucun chargement bloqué |
| Galaxie | Systèmes ou cibles inconnues explicitement représentés | Cibles non disponibles sans action; accès protégés côté serveur | Sélection d’une cible et lancement de colonisation/conquête (conquest-report.spec.mjs) | /carte; aucun chargement bloqué |
| Flottes et missions | Sans vaisseau, journal sans mission (« L’horizon vous attend »), aucune demande d’orbite | Composition, cible et estimation refusées avec retour de formulaire/erreur; panne d’estimation testée (fleet-preview-submit-state.spec.mjs); orbit refusée/protégée (orbit-request.spec.mjs) | Aperçu, envoi, retour et décision d’orbite | /flottes; contrôleur d’aperçu vérifié par tests dédiés |
| Rapports | « Aucun rapport dans cette catégorie » | Détail absent ou non autorisé répond 404 afin de préserver la confidentialité | Rapports de combat, espionnage et conquête (conquest-report.spec.mjs, espionage-report.spec.mjs) | /rapports; aucun chargement bloqué |
| Espionnage | Message spécifique sans sonde (fleet-empty-state.spec.mjs) | Pré-requis de flotte et confidentialité du rapport vérifiés | Rapport détecté et privé (espionage-report.spec.mjs) | /flottes?mission=spy; aucune image ni commande défectueuse |
| Diplomatie | Aucune alliance et aucun autre commandant sur l’univers initial | Protections CSRF/droits; toutes les mutations erronées ne sont pas testées en navigateur | Propositions, acceptation, membres et orbite (orbit-request.spec.mjs) | /diplomatie; aucun chargement bloqué |
| Messages | « Aucune conversation pour le moment » | Signalement/modération, permissions et erreurs CSRF (moderation-flow.spec.mjs) | Conversation privée et canal d’alliance avec plusieurs comptes | /messages; aucun chargement bloqué |
| Classement | Ligne vide si aucun commandant; sinon ligne du joueur | Contacts uniquement possibles vers un autre joueur | Tri accessible et contact (leaderboard-contact.spec.mjs) | /classement; aucun chargement bloqué |
| Univers | Adhésion/places indisponibles exposées par les états de liste | Conflit / adhésion séquentielle (universe-join-sequential.spec.mjs) | Sélection d’univers puis entrée dans la colonie | /univers; aucun chargement bloqué |
| Profil | Non applicable | Erreurs de formulaire contrôlées par Symfony; branches de stockage défaillant non injectées | Enregistrement du profil | /profil; aucun chargement bloqué |
| Préférences | Non applicable | Validation des choix et sauvegarde (preferences-accessibility.spec.mjs) | Thème, fuseau, mouvement réduit et notifications | /profil/preferences; aucun chargement bloqué |
| Boosts | Inventaire vide; opérations affichées selon les données de démarrage | Limites/règles couvertes par BoostSystemTest; toute panne de lecture réseau séparée non simulée | Événements, contribution et opération chronométrée via la suite d’intégration boosts | /boosts; aucun chargement bloqué |
| Administration | Catalogue et listes selon données de l’univers | Utilisateur sans rôle admin reçoit 403; pages 403 et 404 testées sur desktop/mobile (ui-error-pages.spec.mjs) | Gestion de catalogue (admin-catalogue.spec.mjs) et modération | /admin; route admin refusée testée; aucun chargement bloqué sur vues autorisées |

## Pages d’erreur communes

Des pages dédiées et accessibles existent pour 403, 404 et 500; l’essai navigateur confirme une vraie réponse 403 sur /admin et un 404 sur un rapport privé/manquant, en bureau et mobile. La page 500 est présente et lintée, mais aucune panne serveur n’est provoquée artificiellement pour vérifier sa réponse HTTP. En production, Symfony utilise ces vues avec APP_DEBUG=0.

## Portée et limites restantes

La campagne décrit et vérifie les états réellement atteignables d’un compte neuf et les erreurs principales des parcours; elle ne provoque pas chaque exception interne ni chaque indisponibilité d’un fournisseur de données sur chacun des écrans. Les routes SSR n’ont pas de phase de chargement visible distincte. La couverture axe automatise les règles WCAG 2.0/2.1 A/AA, WCAG 2.2 AA et bonnes pratiques sur 21 vues (17 authentifiées et 4 publiques) à 1440 et 390 px; elle est maintenant sans violation. La navigation clavier et le contraste échantillonné passent également. Cela ne remplace pas un passage manuel complet RGAA/WCAG ni un essai avec NVDA, VoiceOver ou Orca; aucune conformité réglementaire exhaustive n’est déclarée.
