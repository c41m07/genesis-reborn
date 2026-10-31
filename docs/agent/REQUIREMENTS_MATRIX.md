# Genesis Reborn — matrice de traçabilité des exigences

Mise à jour : 31/10/2026 (Europe/Paris). Source principale : mission V3 et brief Figma fournis par Kévin. Les lignes des phases 01–11 renvoient aux audits détaillés existants ; une phase n'est validée que selon les preuves de son audit, et non par cette ligne de synthèse.

États : VALIDÉE = critères attestés par l’audit référencé ; PARTIELLE = exigences applicables ouvertes ; EN COURS = vérification ou livraison inachevée ; À FAIRE = non commencée ; IMPLÉMENTÉE NON VALIDÉE = code présent, preuves insuffisantes.

| ID | Exigence atomique / groupe traçable | Source | Phase | Statut | Preuves ou lacunes |
|---|---|---:|---:|---|---|
| P01-AUD-01 | Audit de l’existant, Git, architecture, code et tests | V3 §8 | 01 | VALIDÉE | PHASE_01_AUDIT.md |
| P02-SPEC-01 | Décisions gameplay récentes, règles PNJ/défenses/espionnage et paramètres provisoires distingués | V3 §2, §6 | 02 | VALIDÉE | REGLES.md, DECISIONS.md, PHASE_02_DECISIONS.md |
| P03-ENV-01 | Architecture, installation, migrations et environnement reproductibles | V3 §8 | 03 | VALIDÉE | PHASE_03_AUDIT.md, TEST_STATUS.md |
| P04-AUTH-01 | Inscription, connexion, sessions, rôles et contrôles d’accès | V3 §7–8 | 04 | VALIDÉE | PHASE_04_SECURITY_AUDIT.md |
| P04-AUTH-02 | Récupération sécurisée de mot de passe | V3 §7–8, annexe A UX-01 | 04 | À FAIRE | Absente du code selon PROMPT_PHASE_CROSSCHECK.md |
| P04-PREF-01 | Préférences de compte persistées | V3 §7–8 | 04 | À FAIRE | Absentes du code selon PROMPT_PHASE_CROSSCHECK.md |
| P05-UNIV-01 | Univers, carte et accès/persistance des planètes | V3 §7–8 | 05 | VALIDÉE | PHASE_05_UNIVERSE_AUDIT.md |
| P05-COL-01 | Distinguer planète vierge colonisable et planète PNJ conquérable | Décision utilisateur, V3 §2 | 05/08/09 | VALIDÉE | REGLES.md, tests cités dans PHASE_05 et PHASE_09 |
| P05-CAP-01 | Capturer un PNJ sans réinitialiser bâtiments, stocks et développement | Décision utilisateur, V3 §2 | 05/09 | VALIDÉE | PHASE_09_COMBAT_AUDIT.md ; règle dans docs métier |
| P06-ECO-01 | Production, plafonds, rattrapage temporel et dépenses protégées | V3 §7–8 | 06 | VALIDÉE | PHASE_06_ECONOMY_AUDIT.md |
| P07-BLD-01 | Bâtiments, recherches, files, coûts, prérequis et annulations | V3 §8 | 07 | VALIDÉE | PHASE_07_BUILDINGS_TECHNOLOGIES_AUDIT.md |
| P07-DEF-01 | Défenses stationnaires : production et pertes, jamais envoyées en mission | Décision utilisateur, V3 §2 | 07/08/09 | VALIDÉE | PHASE_07 et PHASE_08 ; test de composition mission |
| P08-FLT-01 | Composition, carburant, trajet, retour et idempotence des missions | V3 §8 | 08 | VALIDÉE | PHASE_08_FLEET_MISSIONS_AUDIT.md |
| P08-ESP-01 | Mission d’espionnage avec sondes, confidentialité et détection selon règles documentées | V3 §2, §8 | 08 | VALIDÉE | PHASE_08 audit ; parcours navigateur à couvrir en phase 13 |
| P09-CBT-01 | Combat, pertes, butin, rapports privés et notification idempotente | V3 §8 | 09 | VALIDÉE techniquement | PHASE_09_COMBAT_AUDIT.md ; équilibrage reste provisoire |
| P10-SOC-01 | Diplomatie, alliance, classement et messagerie protégés | V3 §8 | 10 | VALIDÉE techniquement | PHASE_10_SOCIAL_STRATEGY_AUDIT.md |
| P11-ADM-01 | Administration, modération, droits et journal d’audit | V3 §8 | 11 | VALIDÉE | PHASE_11_ADMIN_AUDIT.md |
| P11-BAL-01 | Réglage fin des catalogues bâtiments, technologies et unités par administrateur | V3 §8 | 11 | À FAIRE | Outil d’équilibrage incomplet ; PHASE_11_ADMIN_AUDIT.md |
| P12-IA-01 | Architecture de navigation et inventaire exhaustif des écrans réels | V3 §8–9, annexe A | 12 | EN COURS | UI_SCREEN_MATRIX.md ; prototype public audité sous Playwright |
| P12-WF-01 | Wireframes low-fidelity desktop 1440, tablette 768, mobile 390 pour les écrans applicables | V3 §8, annexe A | 12 | IMPLÉMENTÉE NON VALIDÉE | Site Figma publié ; audit détaillé dans FIGMA_HANDOFF.md ; plusieurs vues absentes/incomplètes |
| P12-FLOW-01 | Parcours prototype pour inscription, bâtiments, recherche, colonisation, conquête PNJ, espionnage, missions, orbite, alliances et admin | V3 §8, annexe A | 12 | IMPLÉMENTÉE NON VALIDÉE | Figma annonce 7 parcours; couverture écran observée mais tous les flux n’ont pas été exécutés |
| P12-RSP-01 | Navigation et données stratégiques utilisables aux trois tailles, sans fonctionnalité supprimée | Brief A2–A3 | 12 | PARTIELLE | Figma mesuré sans débordement horizontal à 390 px ; tablette 768 et tous états non vérifiés |
| P12-STATE-01 | États chargement, vide, indisponible, erreur, confirmation, succès, accès refusé et résultats de mission | Brief A6 | 12 | PARTIELLE | Matrice d’écran en cours ; états non exhaustifs dans le prototype |
| P12-ART-01 | Direction graphique, logo et assets originaux avec provenance/licence | V3 §8–9, annexe A7 | 12 | PARTIELLE | Assets existants listés dans ASSET_MANIFEST.md ; provenance à vérifier ; aucun nouvel asset produit |
| P12-APP-01 | Écrans intégrés aux vraies routes/services avec permissions, sans action fictive | V3 §8 étape F | 12 | PARTIELLE | Templates/routes réels recensés dans UI_SCREEN_MATRIX.md ; correspondance écran par écran à achever |
| P12-A11Y-01 | Clavier, contrastes, mouvement réduit, retours d’état et vérification RGAA/WCAG | V3 §8–9 | 12/13 | PARTIELLE | Corrections existantes, audit non exhaustif ; cible 390 responsive Playwright réussie |
| P13-QA-01 | Campagne browser, sécurité, concurrence et performance complète | V3 §8 | 13 | À FAIRE | Une campagne complète a expiré sans rapport restitué ; scénarios ciblés consignés dans TEST_STATUS.md |
| P14-OPS-01 | CI, sauvegarde/restauration, diagnostics et procédures d’exploitation testés | V3 §8 | 14 | PARTIELLE | Vérifications manquantes listées dans PROMPT_PHASE_CROSSCHECK.md |
| P15-FINAL-01 | Audit final indépendant et verdict documenté, sans prétendre prêt si critères ouverts | V3 §8, §16 | 15 | À FAIRE | Aucun verdict production |
| F01 | Accès, inscription et récupération | Brief A4 | 12 | PARTIELLE | Absent/incomplet; voir UI_SCREEN_MATRIX.md |
| F02 | Tableau de bord spatial | Brief A4 | 12 | PARTIELLE | Présent, prototypes données fictives; voir UI_SCREEN_MATRIX.md |
| F03 | Gestion des planètes | Brief A4 | 12 | PARTIELLE | Présent, simulations; voir UI_SCREEN_MATRIX.md |
| F04 | Bâtiments | Brief A4 | 12 | PARTIELLE | Présent, simulations; voir UI_SCREEN_MATRIX.md |
| F05 | Technologies | Brief A4 | 12 | PARTIELLE | Présent, simulations; voir UI_SCREEN_MATRIX.md |
| F06 | Chantier spatial | Brief A4 | 12 | PARTIELLE | Présent, simulations; voir UI_SCREEN_MATRIX.md |
| F07 | Défenses stationnaires et destructibles | Brief A4 | 12 | PARTIELLE | Présent et correctement distingué; voir UI_SCREEN_MATRIX.md |
| F08 | Galaxie et types de planètes | Brief A4 | 12 | PARTIELLE | Présent, actions simulées; voir UI_SCREEN_MATRIX.md |
| F09 | Flottes, missions, coûts et confirmation | Brief A4 | 12 | PARTIELLE | Présent, flux simulé; voir UI_SCREEN_MATRIX.md |
| F10 | Rapport de combat autorisé et détaillé | Brief A4 | 12 | PARTIELLE | Liste présente, détail non vérifié; voir UI_SCREEN_MATRIX.md |
| F11 | Espionnage, détection et rapport privé | Brief A4 | 12 | PARTIELLE | Vue d’envoi présente, résultat incomplet; voir UI_SCREEN_MATRIX.md |
| F12 | Conquête PNJ JcE et conservation du développement | Brief A4 | 12 | PARTIELLE | Carte expliquée, résultat non vérifié; voir UI_SCREEN_MATRIX.md |
| F13 | Alliances et diplomatie | Brief A4 | 12 | PARTIELLE | Présent, simulations; voir UI_SCREEN_MATRIX.md |
| F14 | Classements, messages et notifications | Brief A4 | 12 | PARTIELLE | Listes présentes, interactions non vérifiées; voir UI_SCREEN_MATRIX.md |
| F15 | Administration et opérations protégées | Brief A4 | 12 | PARTIELLE | Écran admin non observé; mention admin non interactive; voir UI_SCREEN_MATRIX.md |

Cette matrice reste un registre vivant : toute exigence atomique découverte pendant les phases 12–15 doit recevoir un ID propre, ses dépendances, les tests et sa preuve. La présence d’un écran Figma ne valide jamais le comportement métier intégré.
