import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['summary'];

    connect() {
        this.mobile = matchMedia('(max-width: 600px)');
        this.onViewportChange = () => this.sync();
        this.mobile.addEventListener('change', this.onViewportChange);
        this.sync();
    }

    disconnect() {
        this.mobile.removeEventListener('change', this.onViewportChange);
    }

    sync() {
        // Le HTML reste déplié sans JavaScript ; sur ordinateur, le résumé est masqué.
        const focusedElement = document.activeElement;
        const focusSummary = this.mobile.matches && this.element.contains(focusedElement);
        const focusCurrentPage = !this.mobile.matches && focusedElement === this.summaryTarget;
        this.element.open = !this.mobile.matches;
        // Un changement de format ne doit pas laisser le focus dans un élément masqué.
        if (focusSummary) {
            this.summaryTarget.focus();
        } else if (focusCurrentPage) {
            this.element.querySelector('[aria-current="page"]')?.focus();
        }
    }

    close(event) {
        if (!this.mobile.matches || !this.element.open) {
            return;
        }
        event.preventDefault();
        this.element.open = false;
        this.summaryTarget.focus();
    }
}
