// La page Guide réutilise la projection serveur déjà rendue, comme le raccourci après sa lecture ciblée.
export function technologyGuideGoal(node) {
    if (!node) return null;
    return {
        label: node.dataset.label,
        tier: node.dataset.tierLabel,
        recommendation: JSON.parse(node.dataset.recommendation),
    };
}

export function technologyGuideRecommendationText({ state, requirement }) {
    if (state === 'step') {
        return `${requirement.label} → niveau ${requirement.level} (actuel : ${requirement.current})`;
    }
    return {
        acquired: 'Objectif atteint : accès acquis définitivement.',
        prerequisites_met: 'Prérequis atteints : consulter la fiche de cet élément.',
        unavailable: 'Aucune étape disponible ici. Consultez les prérequis dans le Guide.',
    }[state];
}
