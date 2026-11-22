// Le filtre et le sélecteur de rangs partagent le même état vide. L’aide du
// chantier n’est pas un résultat de catégorie, même si aucun rang n’est visible.
export function syncCatalogEmptyState(element, cards, category) {
    const empty = !cards.some(card => !card.hidden);
    const shipyard = element.querySelector('[data-shipyard-empty]');
    const showShipyard = Boolean(shipyard && cards.length && category === 'all' && empty);
    if (shipyard) shipyard.hidden = !showShipyard;
    const filterEmpty = element.querySelector('[data-catalog-empty]');
    if (filterEmpty) filterEmpty.hidden = !empty || showShipyard;
}
