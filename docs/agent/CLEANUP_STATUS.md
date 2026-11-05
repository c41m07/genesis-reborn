# Nettoyage du depot — suivi unique

## Perimetre
Nettoyage du code, configuration, scripts, tests, documentation, consignes IA et images matricielles. Preserver fonctionnalites et regles de jeu. Exclure dependances, contenus generes, volumes, bases et PDF. Conserver SVG, logos, favicon et icones identitaires.

## Etat initial
- Depot: `/home/caim/Projets/Genesis_Reborn`, branche `feature/catalogue-ui-research-defenses`.
- 146 chemins deja modifies, supprimes ou non suivis au demarrage; contenu a preserver.
- Aucun `AGENTS.md` racine. Dossiers voisins hors perimetre.
- Commandes prevues: `make validate`, `lint`, `assets`, `test` et `test-browser`.
- Inventaire anterieur: 201 images matricielles sous `public/`, `assets/` et `docs/`. Logo identifie par contexte: `public/art/genesis-reborn-emblem.webp` et `.png`. SVG identitaires: `favicon.svg` et `icons.svg`.

## Etapes
- [x] Confirmer acces et etat Git initial.
- [x] Inventorier commandes et images.
- [x] Etablir controles de reference.
- [x] Retirer les usages d’illustrations de la colonie et du catalogue, le contrôleur, la feuille dédiée et le manifeste désormais inutilisés.
- [x] Supprimer les images matricielles hors emblèmes de marque et adapter les vues et références documentaires.
- [x] Consolider les références principales, ajouter les consignes IA racine et qualifier l’audit de recherches comme historique.
- [x] Supprimer les références aux captures raster retirées; préserver les rapports datés et les prompts de recréation.
- [ ] Examiner le code, scripts et configurations restants; vérifier la chronologie et le contenu détaillé des documents de balance.
- [ ] Relire le diff final complet et exécuter les vérifications finales.

## Decisions
- Aucun reset, restauration ou ecrasement des changements initiaux.
- Embleme conserve en tant que logo; favicon et icones SVG conserves.
- Audit d'equilibrage historique; aucun reequilibrage.
- `make validate`: reussi.
- `npm run build`: reussi (5 fichiers generes dans `public/build`, ignore par Git).
- `make lint`: reussi; PHP, 77 YAML, 39 Twig, conteneur Symfony et schema valides.
- `make test`: termine avec 126 tests, 2303 assertions, 3 erreurs et 14 echecs. Execution faite avant les changements de nettoyage, sur l'arbre deja modifie au depart. Les erreurs portent notamment sur valeurs d'equilibrage/catalogue et opérations actives/colonies; voir sortie de cette execution dans l'historique de session.
- Après retrait des médias, `make validate`, `make lint` et `npm run build` réussissent.
- `make test-browser` complet : 30/36 scénarios réussis. Six échecs dans les tests métier préexistants de catalogue, colonisation, prérequis de recherche et rapport de conquête; ne pas réinterpréter ces échecs comme causés par la suppression des médias.
- Le scénario `responsive-breakpoints` a d’abord échoué sur son assertion `naturalWidth` de l’illustration de planète supprimée. Cette assertion et sa capture temporaire ont été retirées; relance isolée réussie, 1/1.
- Les six scénarios navigateur en échec incluent des attentes de valeurs de recherche ou de libellés qui divergent des fichiers de configuration présents, et des parcours de colonisation bloqués par la limite actuelle de colonies. Aucun équilibrage ni règle métier n’a été changé pour contourner ces attentes.
- Vérification des liens Markdown relatifs dans les documents examinés : aucune cible manquante.
- Les captures de validation ont été recréées par la première suite navigateur puis supprimées de nouveau; les tests ne produisent plus de captures raster dans `docs/validation/`.
