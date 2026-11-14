import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rail'];

    connect() {
        this.viewport = matchMedia('(min-width: 1251px)');
        this.sync = () => this.update();
        this.viewport.addEventListener('change', this.sync);
        this.update();
    }

    update() {
        if (this.viewport.matches) {
            this.railTarget.tabIndex = 0;
            this.railTarget.setAttribute('role', 'region');
            this.railTarget.setAttribute('aria-label', 'Activité de la colonie et flottes en vol');
        } else {
            this.railTarget.removeAttribute('tabindex');
            this.railTarget.removeAttribute('role');
            this.railTarget.removeAttribute('aria-label');
        }
    }

    disconnect() { this.viewport?.removeEventListener('change', this.sync); }
}
