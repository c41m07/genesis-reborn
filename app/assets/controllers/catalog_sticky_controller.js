import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rail'];

    connect() {
        this.resize = new ResizeObserver(() => {
            this.element.style.setProperty('--catalog-ribbon-height', `${this.railTarget.offsetHeight}px`);
        });
        this.resize.observe(this.railTarget);
        this.onToggle = event => {
            const selected = event.target;
            if (!selected.matches('details[data-catalog-disclosure]') || !selected.open) return;
            this.disclosures.forEach(disclosure => {
                if (disclosure !== selected) disclosure.open = false;
            });
        };
        this.onOutside = event => {
            if (!this.railTarget.contains(event.target)) this.close();
        };
        this.onEscape = event => {
            if (event.key !== 'Escape') return;
            const opened = this.disclosures.find(disclosure => disclosure.open);
            if (!opened) return;
            event.preventDefault();
            this.close();
            opened.querySelector('summary').focus();
        };
        this.railTarget.addEventListener('toggle', this.onToggle, true);
        document.addEventListener('pointerdown', this.onOutside);
        this.element.addEventListener('keydown', this.onEscape);
    }

    get disclosures() {
        return [...this.railTarget.querySelectorAll('details[data-catalog-disclosure]')];
    }

    close() {
        this.disclosures.forEach(disclosure => { disclosure.open = false; });
    }

    disconnect() {
        this.resize.disconnect();
        this.railTarget.removeEventListener('toggle', this.onToggle, true);
        document.removeEventListener('pointerdown', this.onOutside);
        this.element.removeEventListener('keydown', this.onEscape);
    }
}
