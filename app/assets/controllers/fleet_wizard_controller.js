import { Controller } from '@hotwired/stimulus';
import { compactNumber, exactNumber } from '../quantity.js';

export default class extends Controller {
    static targets = ['step', 'indicator', 'counter', 'quantity', 'error', 'summary', 'recap', 'recapCount', 'recapRanks', 'recapShips', 'recapDestination', 'recapQuote'];

    static values = { origin: String };

    connect() {
        this.currentStep = 1;
        this.element.classList.add('fleet-wizard-ready');
        this.render();
        if (this.hasRecapTarget) this.recapTarget.hidden = false;
        this.updateRecap();
    }

    next(event) {
        event.preventDefault();
        if (this.currentStep === 1 && !this.hasComposition()) {
            this.showError('Choisissez au moins un vaisseau pour poursuivre.');
            const firstChoice = this.quantityTargets.find(input => !input.disabled);
            (firstChoice ?? this.stepTargets[0].querySelector('h3'))?.focus();
            return;
        }
        if (this.currentStep === 2 && !this.hasDestination()) {
            this.showError('Choisissez une destination pour cette mission.');
            this.destinationField()?.focus();
            return;
        }
        this.errorTarget.hidden = true;
        this.currentStep = Math.min(3, this.currentStep + 1);
        if (this.currentStep === 3) this.updateSummary();
        this.render(true);
    }

    previous(event) {
        event.preventDefault();
        this.errorTarget.hidden = true;
        this.currentStep = Math.max(1, this.currentStep - 1);
        this.render(true);
    }

    hasComposition() {
        return this.quantityTargets.some(input => Number(input.value) > 0);
    }

    destinationField() {
        const mode = this.element.querySelector('[name="destination_mode"]:checked')?.value || 'custom';
        return this.element.querySelector(mode === 'custom' ? '[name="coordinates"]' : '[name="' + mode + '_destination"]');
    }

    hasDestination() {
        const field = this.destinationField();
        return Boolean(field && !field.disabled && field.value);
    }

    updateSummary() {
        const missionField = this.element.querySelector('[name="mission"]');
        const mission = missionField?.selectedOptions[0]?.textContent.trim() || 'Mission';
        const targetField = this.destinationField();
        const option = targetField?.selectedOptions?.[0];
        const destination = (option ? (missionField.value === 'explore' ? option.dataset.systemCoordinates : option.textContent.trim()) : targetField?.value.trim()) || 'destination à confirmer';
        const composition = this.quantityTargets
            .filter(input => !input.disabled && Number(input.value) > 0)
            .map(input => ({ amount: Number(input.value), label: input.dataset.shipLabel }));
        const ships = composition.map(({ amount, label }) => compactNumber(amount) + ' × ' + label);
        const cargo = [...this.element.querySelectorAll('[name^="cargo["]')]
            .filter(input => Number(input.value) > 0)
            .map(input => (this.element.querySelector(`label[for="${input.id}"]`)?.textContent.trim() || 'Ressource') + ' : ' + exactNumber(Number(input.value)));
        const list = document.createElement('dl');
        for (const [label, value] of [['Origine', this.originValue], ['Destination', destination], ['Mission', mission], ['Composition', ships.join(', ')], ['Fret chargé', cargo.join(', ') || 'Aucun']]) {
            const entry = document.createElement('div'), term = document.createElement('dt'), description = document.createElement('dd');
            term.textContent = label; description.textContent = value; entry.append(term, description); list.append(entry);
        }
        this.summaryTarget.replaceChildren(list);
        this.summaryTarget.title = composition.map(({ amount, label }) => exactNumber(amount) + ' × ' + label).join(', ');
    }

    updateRecap(event = null) {
        if (!this.hasRecapTarget) return;
        // Le résumé est déduit des seuls champs actifs du formulaire. Un nouveau
        // choix invalide la mention du devis avant même sa prochaine réponse.
        const selected = this.quantityTargets.filter(input => !input.disabled && Number(input.value) > 0);
        const ranks = { basic: 0, intermediate: 0, superior: 0 };
        let ships = 0;
        for (const input of selected) {
            const amount = Number(input.value);
            ranks[input.dataset.shipTier] += amount;
            ships += amount;
        }
        this.recapCountTarget.textContent = exactNumber(ships);
        this.recapRanksTarget.textContent = Object.entries({ basic: 'Basique', intermediate: 'Intermédiaire', superior: 'Supérieur' })
            .map(([rank, label]) => exactNumber(ranks[rank]) + ' ' + label).join(' · ');
        this.recapRanksTarget.hidden = ships === 0;
        this.recapShipsTarget.hidden = ships === 0;
        this.recapShipsTarget.replaceChildren(...selected.map(input => {
            const item = document.createElement('li');
            const label = document.createElement('span');
            const amount = document.createElement('strong');
            label.textContent = input.dataset.shipLabel;
            amount.textContent = exactNumber(Number(input.value));
            item.append(label, amount);
            return item;
        }));

        const field = this.destinationField();
        const missionField = this.element.querySelector('[name="mission"]');
        const option = field?.selectedOptions?.[0];
        this.recapDestinationTarget.textContent = this.hasDestination()
            ? ((option ? (missionField?.value === 'explore' ? option.dataset.systemCoordinates : option.textContent.trim()) : field.value.trim()) || 'À choisir')
            : 'À choisir';
        if (event) this.recapQuoteTarget.textContent = 'Après le devis du trajet';
    }

    quote() {
        this.updateSummary();
        this.updateRecap();
        if (this.hasRecapTarget) this.recapQuoteTarget.textContent = 'Voir l’estimation du trajet';
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
    }

    render(moveFocus = false) {
        this.stepTargets.forEach(step => {
            const active = Number(step.dataset.step) === this.currentStep;
            step.hidden = !active;
            step.setAttribute('aria-hidden', String(!active));
        });
        this.indicatorTargets.forEach(indicator => {
            const number = Number(indicator.dataset.step);
            const active = number === this.currentStep;
            indicator.classList.toggle('active', active);
            indicator.classList.toggle('complete', number < this.currentStep);
            if (active) indicator.setAttribute('aria-current', 'step');
            else indicator.removeAttribute('aria-current');
        });
        this.counterTarget.textContent = 'Étape ' + this.currentStep + ' / 3';
        if (moveFocus) {
            // Le bouton précédent est masqué : le titre situe le clavier dans la nouvelle étape.
            const activeStep = this.stepTargets.find(step => Number(step.dataset.step) === this.currentStep);
            activeStep?.querySelector('h3').focus();
        }
    }
}
