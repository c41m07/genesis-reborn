# Flottes entrantes — 16 novembre 2026

## Périmètre validé

Deux cadres distincts : « Flottes en vol » conserve le suivi de vos propres missions ;
« Flottes entrantes » annonce les arrivées
d’autres commandants sur vos colonies. Il reste présent même sans arrivée
visible : compteur zéro et message « Aucune flotte entrante visible vers vos colonies ». Aucun mouvement allié vers une autre destination n’est ajouté.
Le panneau reprend les mêmes composants visuels que « Flottes en vol » : cadre,
titre avec compteur, cartes de mission, trajet et échéance. La Vue générale finalisée
selon sa maquette validée place les arrivées avant les vols personnels, à droite de
l’activité sur grand écran et après celle-ci sur mobile. Flottes et la colonne de droite
des quatre catalogues (Bâtiments, Recherche, Chantier spatial et Défenses) conservent
les arrivées après les vols personnels. Sur mobile, les deux cadres restent visibles
après le catalogue. Un seul panneau apparaît par page. La carte, la messagerie, les classements
et les autres vues conservent la surveillance sans cadre ajouté au-dessus du contenu.
La page d’accès refusé garde le retour au jeu ou à la console comme première action
après le lien d’évitement. Aucune nouvelle page, onglet ou navigation n’est ajouté.
La barre de progression utilise l’heure serveur lorsque le départ est public.
Les attaques masquent leur date de départ et affichent uniquement leur échéance.

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

## Récapitulatif du transport entrant — 20 novembre 2026

« Consulter le récapitulatif du transport » ouvre le rapport économique existant,
dans sa page dédiée. Le panneau, sur ordinateur comme sur mobile, ne duplique
pas le bilan. Un seul chargement groupé récupère les identifiants des rapports
pour les arrivées de transport visibles dont le joueur est le destinataire
enregistré au départ. Un changement de propriétaire de la colonie ne transfère
pas ce droit. Aucun lien n’est présenté si le rapport historique est absent ;
aucun rapport n’est reconstitué. La route conserve sa vérification des participants.

| Mission non hostile | Action du destinataire | Composition / fret |
|---|---|---|
| Transport | Rapport économique depuis l’arrivée visible, réservé aux participants enregistrés du même univers | Composition détaillée et carburant privés ; fret expédié, livré ou retourné dans le rapport économique, pas dans le panneau |
| Défense / renfort | Résumé de l’arrivée vers sa colonie, sans nouveau lien | Composition détaillée et fret privés |
| Mise en orbite | Résumé et demande d’orbite selon l’autorisation existante | Composition détaillée et fret privés |
| Stationnement | Missions entre colonies du même commandant | Détails pour le propriétaire, pas de destinataire tiers |
| Exploration / colonisation | Pas de destinataire joueur d’une colonie occupée | Pas de partage tiers de composition ni de fret |

L’espionnage demeure privé pendant le trajet, même entre alliés ; la détection
des attaques et des conquêtes conserve ses règles. La carte, les détails privés,
les relations, les autorisations d’envoi et le calcul du transport ne changent pas.

## Vérifications de l’extension initiale

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

## Validation de la présentation commune

- 16 tests PHP de visibilité / 209 assertions réussis.
- Sept parcours navigateur distincts réussis : trois de surveillance (détection,
  focus, horloge serveur décalée, origines privées, carte, six pages, mobile, thèmes
  et orbite) et quatre parcours existants de vols, Vue générale et état Espionnage vide.
- Comparaison des styles calculés des deux cadres et cartes sur les six pages,
  absence de doublon et vérifications ciblées Axe dans les deux thèmes.
- make validate, make lint, make assets, syntaxes JavaScript et git diff --check réussis.
- Captures réelles et journaux sous output/playwright/incoming-style/ (hors Git).
  Version locale actualisée ; routes / et /health répondent 200, assets servis vérifiés.
- Ce bilan décrit la recette locale avant publication. L’envoi dans une PR vers dev
  est ensuite autorisé, avec fusion conditionnée à toute la CI et sans livraison Alpha.


## Navigation dédiée — 16 novembre 2026

La séparation de Flottes conserve le panneau d’arrivées sur Missions actives et Préparer une mission. Les pages Orbites et À quai gardent le contrôleur de surveillance nécessaire aux alertes communes ; les orbites sont actualisées uniquement si leur région est présente. La colonie affichée est transmise et contrôlée côté serveur. Les règles de visibilité de ce suivi ne changent pas. Voir le [suivi de navigation](NAVIGATION_PAGES_DEDIEES.md).
