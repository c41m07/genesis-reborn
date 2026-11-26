import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item', 'all', 'allLabel', 'submit', 'count'];

    connect() {
        this.allLabelTarget.hidden = false;
        this.update();
    }

    disconnect() {
        // Le formulaire reste utilisable si le contrôleur est déconnecté ou absent.
        this.allLabelTarget.hidden = true;
        this.submitTargets.forEach(button => { button.disabled = false; });
    }

    toggleAll() {
        this.itemTargets.forEach(item => { item.checked = this.allTarget.checked; });
        this.update();
    }

    update() {
        const selected = this.itemTargets.filter(item => item.checked).length;
        this.allTarget.checked = selected > 0 && selected === this.itemTargets.length;
        this.allTarget.indeterminate = selected > 0 && selected < this.itemTargets.length;
        this.submitTargets.forEach(button => { button.disabled = selected === 0; });
        this.countTarget.textContent = selected ? `${selected} rapport${selected > 1 ? 's' : ''} sélectionné${selected > 1 ? 's' : ''}` : '';
    }
}
