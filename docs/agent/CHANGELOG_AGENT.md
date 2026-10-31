# Genesis Reborn — journal de l'agent

## 2026-10-31 — espionnage (implémentation initiale)

- Ajout d’une mission sondes uniquement sur toute planète connue non possédée par l’émetteur; les sondes sont consommées à l’arrivée.
- Ajout du calcul configurable de détection et de profondeur d’information, des alertes au propriétaire et des rapports privés par auteur.
- Ajout de l’affichage de mission depuis la carte, de l’index des rapports et de la page de détail; migration 11 créée et appliquée à la base locale.
- Les règles et coefficients sont documentés dans PHASE_02_DECISIONS.md. Les profils PNJ et le réglage d’espionnage restent provisoires.
- Validation finale : 67 tests / 335 assertions; make lint (PHP, 62 YAML, 17 Twig, conteneur et schéma); make validate et make verify-install réussis avec 11 migrations. Ajout de trois tests d’intégration espionnage; correction du test de pertes PNJ pour vérifier le vaisseau par slug.

## 2026-10-31 — conquête JcE (tranche intégrée)

- Conquête uniquement contre les planètes neutres, activée dans Omega et Turbo; les planètes vierges restent colonisables et les planètes détenues par un joueur sont exclues. Une victoire avec un vaisseau colonie survivant permet la prise, la limite habituelle de colonies s’applique.
- Environ 30 % des planètes ont été réparties aléatoirement comme PNJ dans les cartes JSON et le reste reste vierge; ce tirage est figé. Les niveaux 1–5 sont inscrits dans les catalogues d’univers. Les préréglages créent bâtiments, ressources, flotte PNJ et défenses fixes au premier combat; les valeurs de composition sont provisoires et modifiables.
- À la prise, bâtiments et stocks restent en place; flotte PNJ et défenses disparaissent. Les pertes PNJ persistent après une attaque échouée. Deux tests d’intégration couvrent ces parcours.
- À ce point du journal, l’espionnage restait en attente et la phase 02 n’était pas encore close; la validation finale est consignée dans l’entrée espionnage ci-dessus.

## 2026-10-31 — butin de combat (tranche livrée)

- Ajout du pillage des stocks au-delà du plancher protégé de 10 % de la capacité de stockage propre à chaque ressource, selon le choix de Kévin.
- Après victoire des attaquants, le fret libre des flottes survivantes se répartit selon leur capacité restante, carburant réservé au retour compris dans l’espace occupé.
- Le rapport joueur n’affiche que le butin de ses propres flottes; tests d’intégration et lint réussis : 61 tests / 274 assertions.

## 2026-10-31
- Reprise du dépôt sur feat/genesis-reborn-production-completion, HEAD 80668fa. Le statut Git WSL ne signalait au départ aucun changement suivi; .idea/ non suivi a été préservé.
- Création des documents de suivi sous docs/agent/. Les fichiers préexistants sont préservés.
- Validation Compose réussie. PHPUnit initial : 51 tests, 189 assertions; après le simulateur : 57 tests, 200 assertions, réussite. Lint : PHP valide; 58 YAML; 14 Twig; conteneur Symfony et schéma Doctrine validés.
- Les anciens résultats de docs/LIVRAISON-DOMAINES-UI.md sont datés du 21/10 et ne remplacent pas le contrôle courant.
- Aucune suppression ou substitution de fonctionnalité. Le commit de suivi de la documentation est enregistré dans l'historique local.

## 2026-10-31 — simulateur de combat (tranche incomplète)

- Ajout de CombatSimulator, moteur pur déterministe sur snapshots, avec camps/roles, arrivées différées, choix de cible, tirs, formule de précision, matchups, pertes et résultats.
- Ajout de tests unitaires couvrant répétabilité, délais d'arrivée, destruction avant le tour, bornes de précision, défenses statiques et validation des statistiques.
- Le service n'est pas relié aux flottes ou à la base; aucun parcours d'attaque n'est déclaré opérationnel.
- Après ajout : make test = 57 tests / 200 assertions; make lint réussi.
