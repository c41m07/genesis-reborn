# Référencement et pages publiques

## Configuration

`DEFAULT_URI` définit l’origine publique absolue. En production, renseigner
l’adresse HTTPS réelle, cohérente avec `APP_DOMAIN` et le proxy ; aucun domaine
n’est inventé par le code. Le modèle .env.prod.example reste à remplacer.
Les URL canoniques et Open Graph ignorent le Host reçu.

`app/src/Service/PublicPageMetadata.php` est la liste unique des pages indexables :
accueil et inscription. Le sitemap réutilise cette liste ; robots.txt l’annonce,
sans bloquer la connexion. Les autres vues utilisent noindex. Les pages privées
restent protégées par Symfony Security : l’indexation ne remplace pas la sécurité.

En développement, Symfony ajoute volontairement `X-Robots-Tag: noindex`.
La configuration générale, utilisée en production et pour les tests HTTP, le
désactive pour laisser indexer l’accueil et l’inscription. Une préproduction
publique doit définir explicitement `disallow_search_engine_index: true`.

L’image de partage est l’emblème existant à
`/art/genesis-reborn-emblem.webp`, accessible sans connexion. Les métadonnées
figurent dans le HTML initial. La disponibilité Internet et le rendu Discord
restent à vérifier sur le domaine réel après déploiement.

## Contrôles après déploiement

Vérifier anonymement les réponses 200 de l’accueil, de l’inscription, de robots.txt,
du sitemap et de l’image, avec leurs types text/plain, application/xml et image/webp.
Le sitemap doit contenir exactement les deux URL canoniques indexables. Vérifier
le HTML initial, HTTPS, l’absence d’un en-tête noindex ajouté par le proxy sur les
pages publiques et la présence de noindex sur la connexion. Les pages privées
doivent toujours demander l’authentification.

## Search Console : actions manuelles restantes

Aucun accès propriétaire Search Console ni domaine public vérifié n’est disponible
dans cette mission. Aucune validation, soumission ou indexation n’est annoncée.

1. Avec le compte propriétaire autorisé, sélectionner la propriété existante,
   ou ajouter une propriété Domaine pour le vrai domaine.
2. Suivre la validation DNS et ajouter le TXT fourni par Google chez le fournisseur
   DNS. Préserver les validations existantes et attendre la confirmation Google.
3. Dans Sitemaps, envoyer l’adresse publique absolue de `/sitemap.xml`.
   Vérifier le résultat de lecture dans Search Console.
4. Inspecter l’accueil et l’inscription avec l’outil d’inspection d’URL ;
   contrôler les URL canoniques et les erreurs. L’envoi ne garantit pas l’indexation.

Sources officielles consultées le 7 novembre 2026 :
[validation de propriété](https://support.google.com/webmasters/answer/9008080?hl=fr)
et [envoi du sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap?hl=fr).

## Accessibilité ciblée

L’accueil possède un seul h1, un logo avec alternative Genesis Reborn, des liens
explicites et un accès au contenu au clavier. Les emblèmes d’en-tête accompagnés du
nom du jeu restent décoratifs. Les formulaires conservent libellés, erreurs liées
et CSRF. Le focus et les textes publics sont renforcés. Les tests navigateur
vérifient clavier, débordement et contrastes avec axe sur les pages publiques.
Ils ne constituent ni un audit complet ni une déclaration de conformité RGAA.
