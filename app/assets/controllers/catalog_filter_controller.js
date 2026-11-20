import { Controller } from '@hotwired/stimulus';
import { syncCatalogEmptyState } from '../catalog_empty_state.js';

export default class extends Controller {
    static targets = ['card'];

    apply(event) {
        const category = event.currentTarget.dataset.category;
        this.element.querySelectorAll('[data-action="click->catalog-filter#apply"]').forEach((button) => {
            const active = button === event.currentTarget;
            button.setAttribute('aria-pressed', String(active));
        });
        this.cardTargets.forEach((card) => {
            card.hidden = card.dataset.catalogVisible === 'false' || (category !== 'all' && card.dataset.category !== category);
        });
        syncCatalogEmptyState(this.element, this.cardTargets, category);
    }
}
