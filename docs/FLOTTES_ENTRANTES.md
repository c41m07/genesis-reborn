# Flottes entrantes — 16 novembre 2026

## Périmètre validé

Deux cadres distincts : « Flottes en vol » conserve le suivi de vos propres missions ;
« Flottes entrantes » reprend le cadre d’attaques détectées et annonce les arrivées
d’autres commandants sur vos colonies. Il reste présent même sans arrivée
visible : compteur zéro et message « Aucune flotte entrante visible vers vos colonies ». Aucun mouvement allié vers une autre destination n’est ajouté.
Le panneau reste un complément de la page Flottes, sans nouvelle page ni formulaire.

| Mission | Visibilité dans le panneau |
|---|---|
| Transport | Dès le départ vers votre colonie ; les règles d’accueil restent celles de la mission |
| Défense / renfort | Dès le départ vers votre colonie ; alliance ou pacte de défense requis pour un nouvel envoi selon les règles existantes |
| Mise en orbite | Dès le départ ; automatique entre alliés, demande entre neutres, refus d’envoi entre ennemis selon les règles existantes |
| Attaque / conquête | Uniquement après détection, avec le préavis actuel |
| Espionnage | Jamais pendant le trajet, même entre alliés ; la détection à l’arrivée reste indépendante |
| Exploration / colonisation | Une destination système ou planète libre n’est pas une arrivée vers une colonie possédée ; le suivi du commandant reste dans ses vols |
| Stationnement / retours personnels | Dans vos propres vols, sans doublon dans les arrivées étrangères |

Le filtre utilise le propriétaire courant de la destination, comme la carte.
Un vol déjà expédié reste une arrivée à suivre tant qu’il vise votre colonie ;
les contrôles d’autorisation et de relation à l’arrivée restent inchangés.
Les anciennes demandes orbit_pending restent consultables sans faux compte à rebours.
Les demandes récentes continuent à se décider avant l’arrivée dans la page Flottes.

## Informations et protection

Mission, commandant, colonie visée, nombre total de vaisseaux, points et échéance
sont affichés. Composition détaillée, fret et navigation privée restent masqués.
L’origine n’est affichée que si elle est déjà découverte. Espionnage, attaque non
détectée, autre univers et colonie d’un autre commandant sont exclus au serveur.
Les attaques détectées restent en tête et sont signalées par du texte et une bordure.
Les arrivées non offensives utilisent un libellé d’arrivée et une bordure neutre.

L’actualisation existante toutes les cinq secondes, l’horloge serveur, le focus
clavier, les annonces accessibles et la sélection de carte sont conservés.
Le panneau est adaptatif, avec les thèmes sombre et clair. Le suivi des propres
vols conserve son organisation actuelle sur mobile et reste accessible dans Flottes.

## Réalisation locale

Complexité 4/5 : risques d’exposition des sondes, d’origines privées, de mélange
d’univers et d’écritures pendant les consultations. La sélection SQL est limitée
aux flottes étrangères en trajet vers les colonies possédées et aux anciennes
demandes d’orbite en attente. Les effectifs sont groupés pour les seules flottes
visibles. Aucune liste globale d’univers ni synchronisation métier dans ce lecteur.
Les protections de FleetVisibility sont communes avec la carte ; aucun nouveau
contrat d’accueil, droit de combat, coût, trajet ou état de flotte n’est modifié.
Les attributs et le format d’actualisation existants restent compatibles avec
les pages déjà ouvertes. Aucune dépendance, migration ou livraison Alpha.

## Vérifications locales

- 32 tests PHP ciblés / 1 113 assertions réussis : arrivées neutres et alliées,
  attaques, préavis, origine inconnue, sondes, univers, retours exclus, empires
  sans joueur, états d’orbite historiques et lectures sans mutation.
- Trois scénarios navigateur distincts réussis : actualisation sans rechargement,
  focus, horloge serveur, carte tactile, deux cadres, transport neutre, fret privé,
  orbite en attente, mobile et accessibilité ciblée dans les thèmes clair et sombre.
- `make validate`, `make lint`, `make assets` et `git diff --check` réussis.
- Les nouvelles fixtures d’attaque ont été ajustées pour respecter le plafond de
  préavis de 50 % du trajet ; le jeton du retour navigateur respecte ses 36 caractères.
  Les contrôles ont été relancés sans retirer d’assertion ni modifier les règles.
- Les captures réelles et journaux sont conservés sous output/incoming-fleets/ et
  app/test-results/ (hors Git). Aucune nouvelle CI GitHub ni publication distante.
