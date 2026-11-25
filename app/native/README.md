# Calcul exact des combats de grande taille

Le moteur natif utilise les mêmes règles que le moteur PHP, mais traite la durabilité
entière de chaque unité dans deux tableaux de doubles compactés. L'ordre de tir
est mélangé comme avec le générateur `Randomizer` PHP; les groupes de même
signature ne changent ni le rang ni la pondération des cibles. Les compteurs de
ciblage, les dégâts par propriétaire et les pertes incluent tous les tirs
effectivement résolus, sans approximation ou troncature. Le résultat compact
ne contient pas le détail de chaque tir ni la durabilité de chaque survivant:
il expose la quantité et les bornes de durabilité exactes par groupe.

Le processus Linux ne lit pas la base ni les secrets. Son entrée privée
`GR-COMBAT-2` contient les profils issus de l'instantané PHP et sa sortie est
revalidée avant que le worker n'enregistre le résultat. Les verrous et les
récompenses résident dans les services PHP. La mémoire virtuelle du calcul
natif est plafonnée à 512 Mio et son temps à 180 secondes; si la capacité est
dépassée, le processus échoue sans rapport partiel. Ce sont des limites de
calcul, pas des limites d'équilibrage.

La reprise v2 stocke le générateur Mt19937, le dernier round validé et les
doubles de durabilité dans un fichier gzip circulant uniquement par des fichiers
temporaires. Les cohortes déjà présentes sont retrouvées par identité stable,
indépendamment du rang d'un renfort dans la liste. La reprise v1 existante reste
importable: les mots Mt19937 et la durabilité des unités modifiées sont transmis
sans relancer les rounds déjà joués.

Compilation locale, avec GCC ou Clang sur Linux 64 bits petit-boutiste, les
en-têtes zlib et la bibliothèque zlib installés:

```sh
g++ -std=c++20 -O3 -fno-fast-math -ffp-contract=off -Wall -Wextra \
    app/native/combat_engine.cpp -lz -o /tmp/genesis-combat-engine
TEST_COMBAT_ENGINE_BINARY=/tmp/genesis-combat-engine \
    app/vendor/bin/phpunit app/tests/Service/CompactCombatEngineTest.php
```

Les options flottantes maintiennent les étapes d'arrondi de PHP. Lors d'un
changement de PHP, de compilateur ou d'algorithme de tir, rejouer les tests de
parité et de reprise avant livraison. Références d'implémentation PHP:
[bornes aléatoires](https://github.com/php/php-src/blob/PHP-8.4/ext/random/random.c),
[mélange](https://github.com/php/php-src/blob/PHP-8.4/ext/standard/array.c),
[état Mt19937](https://github.com/php/php-src/blob/PHP-8.4/ext/random/engine_mt19937.c).
