# Phase 02 — critères consolidés pour missions et combat

> Spécification de critères et instantané historique du 31 octobre 2026. Les décisions chiffrées appliquées à l’Alpha, y compris les stats de combat, boucliers, matchups et PNJ, sont dans [BALANCING_QCM_2026-11-02.md](BALANCING_QCM_2026-11-02.md). L’état actuel des validations est dans [TEST_STATUS.md](TEST_STATUS.md).

Date : 31/10/2026. Sources : docs/specifications/1 - Conception batiment vaisseau etc.txt §1.12; doc 2 §§1–2 et 3.5–3.8; doc 3 §§2–4; docs/REGLES.md; docs/DECISIONS.md.

## Règles explicites à préserver

### Missions et arrivée
- Le trajet, le carburant et la capacité sont recalculés côté serveur; le navigateur ne choisit pas le résultat ni les dates d'arrivée.
- Une attaque sur une planète déclenche à la première arrivée une fenêtre fixe de 600 secondes (10 minutes), confirmée par Kévin le 01/11/2026. Le paramètre `combat_window_seconds = 600` est déjà en place dans `config/game/rules.yaml`.
- Une flotte attaque ou défend peut rejoindre pendant la fenêtre; l'heure d'arrivée doit être enregistrée.
- Une flotte en défense sans attaque repart à l'expiration de la fenêtre. Une arrivée après résolution retourne directement au propriétaire.
- Une flotte en orbite est distincte de DEFENSE. Elle est acceptée manuellement, sauf contrat d'alliance direct où l'acceptation est automatique; elle peut aider à défendre pendant sa durée stationnée.
- Les trajets en orbite réservent le carburant aller + stationnement prévu + retour au départ. Les trajets inter-galaxies restent interdits faute de règle.

### Alliances et camps
- Relation alliée si contrat direct accepté, même guilde, ou guildes alliées; une hostilité entre guildes empêche un contrat personnel de maintenir l'alliance.
- Les attaquants alliés combattent dans un camp commun; les attaquants non alliés restent en FFA.
- Les défenses statiques de la planète combattent tous les attaquants, même en FFA.
- Une flotte de défense extérieure est incluse dans le camp défendeur pour le calcul de combat; les défenses statiques de la planète ne lui infligent pas de dégâts.
- Une unité ne cible que des unités ennemies vivantes. Les défenses statiques ciblent les rôles attaquants.

### Simulation
- Résolution côté serveur en rounds persistés, un round par minute, jusqu’à 10 rounds ou fin anticipée du combat. Chaque résultat de round doit être sauvegardé de manière idempotente avant le round suivant. Cette règle a été confirmée par Kévin le 01/11/2026; le round 1 démarre une minute après la première attaque.
- Plafond confirmé : 10 rounds par combat. En temps réel, le round 1 démarre à +60 secondes, puis un round commence chaque minute jusqu’au 10e à +600 secondes. Si la limite est atteinte avec des défenseurs survivants, les attaquants survivants battent en retraite. Toute flotte arrivée exactement au début d’un round est traitée avant ce round et peut y participer, notamment à +600 secondes avant le 10e round. Le combat se clôt immédiatement dès qu’un camp est éliminé; une flotte qui arrive après cette clôture repart sans rejoindre le combat.
- Chaque vaisseau et défense est simulé individuellement; les HP ne persistent pas entre combats et les survivants reviennent à leurs HP maximum.
- À chaque round, chaque unité vivante choisit une cible parmi les ennemis; une unité détruite avant son tour ne tire pas. Nombre de tirs aléatoire inclusif entre shots_min et shots_max.
- Chance de toucher = clamp(15, 95, précision + tracking - esquive_cible + signature_cible / 5 - 15).
- Dégâts = attaque de base × multiplicateur de matchup. Valeur de matchup non spécifiée pour un couple : multiplicateur neutre 1, conformément au catalogue existant.
- Les arrivées échelonnées sont enregistrées au moment où elles surviennent et rejoignent le prochain round non résolu; elles n’agissent ni ne subissent de dégâts avant leur arrivée.
- Résolution reproductible : générer et persister une graine avant résolution; conserver dans le rapport les actions suffisantes pour le rejeu sans recalculer le RNG côté client.

### Rapport, carburant et notification
- Persister l'événement, ses participants et les rounds; outcomes documentés : attaquant gagne, défenseur gagne, égalité.
- Les pertes de cargos réduisent la capacité de butin; l'hydrogène de retour réservé au départ reste disponible. Après une victoire des attaquants, tout le stock au-delà de 10 % de la capacité de stockage de chaque ressource est pillable, jusqu'à remplissage des soutes libres des attaquants survivants. En FFA, les camps attaquants survivants se partagent selon leur capacité cargo libre. Si la capacité manque, le mélange suit le prorata des stocks pillables; au retour, le dépôt est plafonné et le surplus perdu.
- Notification d'attaque : membres de la guilde du défenseur et alliés directs du défenseur; les guildes seulement alliées ne sont pas notifiées; les alliés de l'attaquant ne sont pas notifiés.
- Identité de l'attaquant masquée si le défenseur n'a pas découvert son système d'origine; le système d'origine reste visible.
- Renfort entrant : même politique de visibilité; le défenseur est notifié.

## Conquête JcE — règles confirmées et réglages de départ Alpha

Kévin a confirmé que la conquête cible uniquement les planètes neutres marquées PNJ (aucun JcJ), avec flottes et défenses PNJ dans Omega et Turbo. Les autres planètes neutres restent vierges et colonisables sans combat; une planète colonisée par un joueur ne peut pas être conquise. Une victoire avec un vaisseau colonie survivant permet la prise; ce vaisseau est consommé et max_planets reste applicable. Les bâtiments et stocks restent sur la planète; la flotte PNJ et les défenses fixes disparaissent. Après une attaque échouée, les pertes PNJ persistent.

La spécification conserve 1 à 9 planètes par système solaire. Environ 30 % des planètes actuelles sont tirées au sort comme PNJ et cette classification est stockée dans les JSON pour rester fixe; les autres sont vierges. Les catalogues définissent un niveau fixe 1–5 par planète PNJ et un préréglage par niveau (bâtiments, ressources, flotte et défenses). La composition de départ est provisoire et modifiable dans config/game/planet_conquest.yaml. La répartition initiale suit la position orbitale : 1–2 niveau 1, 3–4 niveau 2, 5–6 niveau 3, 7–8 niveau 4, 9 niveau 5.

## Critères d'acceptation techniques
1. Deux arrivées simultanées au premier attaquant ne créent qu'un événement de combat par planète et fenêtre.
2. L'événement démarre seulement à la première arrivée ATTAQUE; les flottes DEFENSE seules ne déclenchent pas de combat.
3. L'arrivée, le rôle, la flotte et le round d'entrée sont persistés et idempotents.
4. Une même graine + mêmes snapshots produit un journal byte-for-byte identique; une résolution rejouée ne modifie pas deux fois stocks, unités ou notifications.
5. Une unité éliminée ne tire pas; une unité arrivée plus tard n'intervient pas avant son round; les limites de touches et multiplicateurs sont bornés et validés.
6. Les camps et droits de ciblage respectent les relations et les exceptions des défenses statiques.
7. Les retours des survivants, pertes, butin, carburant réservé et soutes sont atomiques et testés sur MariaDB isolée.
8. L'accès aux rapports est limité aux participants du combat; les notifications ne divulguent pas d'identité non découverte.
9. Les handlers/outbox tolèrent doublons, messages périmés, panne de Mercure et reprise après redémarrage.
10. Aucun bouton d’attaque/défense/orbite/espionnage ne s’affiche avant que le parcours correspondant soit câblé et protégé par CSRF, propriété et contrôles d’univers.
11. L’espionnage cible uniquement les planètes connues non possédées par l’émetteur, accepte uniquement des sondes, les consomme à l’arrivée, applique les coefficients configurables de détection et de profondeur, et limite chaque rapport à son émetteur. Une détection avertit le propriétaire et bloque les renseignements.

## Points explicitement provisoires ou incomplets
- La fenêtre de 600 secondes (10 minutes) et le plafond de 10 rounds sont confirmés par Kévin le 01/11/2026. Le catalogue global conserve `provisional=true` pour les autres valeurs encore à équilibrer.
- Plusieurs statistiques et la matrice de matchup sont incomplètes; ne pas traiter les valeurs provisoires comme validées.
- L'algorithme exact de choix de cible, l'ordre d'initiative, la distribution du nombre de tirs, l'arrondi des dégâts/HP et la règle de coalition transitive ne sont pas complètement définis dans les textes. Une règle déterministe simple doit être documentée avant d'être codée.
- En FFA, les camps survivants et l'outcome global exigent une règle unique; l'enum du document ne permet pas d'exprimer un vainqueur parmi plusieurs camps attaquants.
- Les seuils de butin sont confirmés par Kévin : 10 % de capacité reste protégé, le surplus est pillable dans la limite des soutes; l’équilibrage économique restera à observer en jeu.

## État d’implémentation au 31/10/2026

Les missions attaque et défense, les événements persistés, rounds échelonnés, pertes, retours et rapports privés sont intégrés et couverts par des tests d’intégration. La flotte en défense sans combat repart à l’expiration de sa fenêtre; `orbit_fuel` est réservé puis consommé. La mission orbite prend en charge la demande manuelle et l’acceptation automatique par contrat direct, le stationnement temporisé, le retour et l’entrée en défense lors d’un combat ouvert. Le butin est implémenté avec le seuil protégé de 10 % confirmé par Kévin; la conquête JcE est intégrée avec des compositions provisoires; l’espionnage est intégré : sondes uniquement, catégories graduées, détection configurable, alerte au propriétaire, rapports privés. Migration 11 ajoutée; lint et schéma validés après intégration. Les tests fonctionnels post-espionnage passent; les règles confirmées sont consignées. La fenêtre de 10 minutes est fixée par Kévin; les autres valeurs de combat demeurent provisoires.
