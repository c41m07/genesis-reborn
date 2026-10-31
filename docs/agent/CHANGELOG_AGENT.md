# Genesis Reborn — journal de l'agent

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
