# Consignes du dépôt

- Mettre à jour le README dans le même lot lorsque changent l’architecture, les dépendances, les commandes ou les fonctionnalités qui y sont documentées.

- Répondre en français. Faire des changements ciblés et préserver tout travail déjà présent dans le dépôt.
- Ne pas changer les règles de jeu ou les valeurs d’équilibrage sans décision explicite; le code décrit le comportement, les décisions produit sont consignées dans [docs/DECISIONS.md](docs/DECISIONS.md) et les règles actuelles dans [docs/REGLES.md](docs/REGLES.md).
- Lancer d’abord les commandes disponibles dans le [Makefile](Makefile). Vérifications validées : `make validate`, `make lint`, `npm run build`, `make test`, `make test-browser`.
- Le périmètre livré et les objectifs sont dans [README.md](README.md), [docs/README.md](docs/README.md) et [docs/ROADMAP.md](docs/ROADMAP.md). Les audits dans `docs/agent/` sont historiques, sauf mention contraire.
- Conserver les logos et les SVG d’identité. Les illustrations matricielles du projet sont absentes volontairement; les prompts de recréation sont dans [docs/ARTWORK_PROMPTS.md](docs/ARTWORK_PROMPTS.md) et [docs/agent/ASSET_GENERATION_PROMPTS.md](docs/agent/ASSET_GENERATION_PROMPTS.md).
