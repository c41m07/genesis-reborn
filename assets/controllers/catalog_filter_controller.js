import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card'];

    apply(event) {
        const category = event.currentTarget.dataset.category;
        this.element.querySelectorAll('[data-action="click->catalog-filter#apply"]').forEach((button) => {
            const active = button === event.currentTarget;
            button.setAttribute('aria-pressed', String(active));
        });
        this.cardTargets.forEach((card) => {
            card.hidden = category !== 'all' && card.dataset.category !== category;
        });
    }
}
