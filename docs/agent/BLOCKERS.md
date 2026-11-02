# Travaux restants avant production

Mise à jour : 2 novembre 2026. Le jeu est au stade d’une **Alpha jouable contrôlée**. Les travaux listés ci-dessous ne bloquent pas l’observation de l’Alpha; ils empêchent de conclure à la préparation de production.

## Équilibrage à mesurer pendant l’Alpha

Kévin a choisi une orientation modérée et des valeurs de départ détaillées dans [BALANCING_QCM_2026-11-02.md](BALANCING_QCM_2026-11-02.md). Elles sont appliquées au jeu comme base d’essai. Les objectifs de progression, l’économie, les pertes, le coût de reconstitution, les trajets et les profils PNJ devront être comparés aux résultats des joueurs Alpha puis ajustés. Leur présence en configuration ne signifie pas qu’ils ont déjà été validés empiriquement.

## Portes de production

- **Accessibilité manuelle :** compléter la revue avec lecteur d’écran et cadrer la conformité RGAA/WCAG; les contrôles automatisés, clavier et responsive réalisés ne remplacent pas cette revue.
- **CI distante :** exécuter le workflow sur la plateforme GitHub retenue; seul le contrôle local est disponible et aucun push n’a été effectué.
- **Capacité :** mesurer sous charge soutenue, fixer les seuils de supervision et les objectifs de service.
- **Sauvegarde et reprise :** définir une destination distante, chiffrement, rétention, surveillance et exercice PRA.
- **Campagne Alpha :** recueillir et analyser les données de progression avant de décider des réglages de balance à conserver ou modifier.

Aucun déploiement ni aucune publication n’ont été réalisés. Les preuves détaillées restent dans [l’audit final](PHASE_15_FINAL_AUDIT.md), la [matrice des exigences](REQUIREMENTS_MATRIX.md) et le [runbook](RUNBOOK_OPERATIONS.md).
