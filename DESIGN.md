# Genesis Reborn : direction artistique

Ce document est une entrée courte inspirée du format [Awesome DESIGN.md](https://github.com/VoltAgent/awesome-design-md), pas un nouveau système graphique. Il décrit l’identité de Genesis Reborn et n’importe aucune charte de marque externe.

## Direction visuelle

- Science-fiction orbitale industrielle et crédible : bleu nuit, acier patiné, éclairages cyan froids et ambre sur les équipements. Préserver logos, SVG et illustrations d’identité. Les images installent le lieu et l’ambiance ; elles ne remplacent ni titre, ni information de jeu, ni action.
- Interface de stratégie dense mais lisible. Panneaux et séparateurs discrets, texte contrasté et alignements stables. Le cyan (`--accent`) sert aux liens et au focus ; l’or (`--gold`) met en valeur les actions là où le composant l’emploie déjà. Préserver le thème clair et ne jamais communiquer un état uniquement par la couleur.
- Pour les bandeaux illustrés, prendre le panorama de l’Avent comme référence de composition : silhouette identifiable, zone calme derrière le titre et pas de texte incrusté. Vérifier le recadrage sur mobile.

## Mettre en œuvre

1. Ouvrir le [guide d’interface](docs/ui-ux/GUIDE_INTERFACE.md) et le [skill genesis-ui-ux](.agents/skills/genesis-ui-ux/SKILL.md). Retrouver la maquette validée ou un écran comparable avant de créer une nouvelle variante.
2. Lire [les tokens existants](app/assets/styles/app.css) et réutiliser les composants et le [layout principal](app/templates/base.html.twig). Ne pas ajouter de palette brute, de duplicata de composant ou de framework pour traiter un écart local.
3. Comparer le rendu réel à la référence : ordinateur (1920 × 1080 par défaut), mobile, clair/sombre selon le parcours, focus visible et états vide/attente/erreur. Conserver les interactions et protections existantes. Suivre [AGENTS.md](AGENTS.md) pour la recette.
