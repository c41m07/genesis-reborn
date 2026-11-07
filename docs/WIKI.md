# Maintenance du wiki

Le guide est accessible aux comptes connectés sur `/wiki`, sans participation à
un univers obligatoire. Les liens sont présents près de Feedback et sur la vue
générale de la colonie, destination de l’accueil du jeu.

`docs/wiki.json` est l’unique texte explicatif du guide. Il explique les
mécanismes sans recopier les tableaux économiques, les prix ou les statistiques.
Les références détaillées proviennent directement de `docs/REGLES.md` et
`docs/DECISIONS.md`, consultables sous `/wiki/references/regles` et
`/wiki/references/decisions`. Elles sont affichées comme texte brut échappé ;
aucun HTML ou Markdown actif n’est interprété. Les identifiants de référence
sont limités à ces deux valeurs : aucun chemin fourni par le visiteur n’est lu.

Les références conservent des paragraphes historiques remplacés par des décisions
ultérieures (bonus, courbes et prix communs). Le guide signale ces écarts sans
additionner les anciennes règles aux nouvelles. Les valeurs propres à un univers
restent celles affichées par le jeu ; le wiki ne change aucun équilibrage.

Après une décision de gameplay, relire les rubriques explicatives touchées et les
sources de référence dans le même lot. Ne pas créer une deuxième copie chiffrée
des règles dans une vue Twig. Les paragraphes du guide sont rendus avec
l’échappement normal de Twig ; ne pas ajouter `raw`.

L’image de production copie seulement les trois fichiers de contenu du wiki ;
les exceptions de `.dockerignore` doivent être conservées. Les autres documents
restent exclus du contexte. Une compilation d’image et son déploiement restent
distincts des tests locaux du wiki.

Vérifications : scénario `tests/browser/wiki.spec.mjs` (accès anonyme, liens,
sommaire clavier, contenu des sources, référence inconnue, trois largeurs et deux
thèmes), puis six contrôles obligatoires avant publication. Ces contrôles ciblés
ne constituent pas un audit RGAA complet. Aucun déploiement n’est effectué par
l’ajout de cette page.

Le contrôle du code de QueueService/CatalogAccess précise deux points : le laboratoire intervient dans le déblocage initial, et la concurrence d’une recherche est contrôlée par rang. Le guide reprend ces périmètres sans réintroduire de condition permanente.

Lors de la validation, une course préexistante de transitions de carte a été reproduite et corrigée : les fins d’animation périmées ne doivent plus écraser un retour plus récent. Le test déterministe galaxy-transition.spec.mjs et le parcours réel galaxy-v2 complètent la vérification du wiki ; aucune règle de jeu n’est changée.
