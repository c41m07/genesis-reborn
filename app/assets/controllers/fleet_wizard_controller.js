import { Controller } from '@hotwired/stimulus';
import { compactNumber, exactNumber } from '../quantity.js';

export default class extends Controller {
    static targets = ['step', 'indicator', 'counter', 'quantity', 'error', 'summary'];

    connect() {
        this.currentStep = 1;
        this.element.classList.add('fleet-wizard-ready');
        this.render();
    }

    next(event) {
        event.preventDefault();
        if (this.currentStep === 1 && !this.hasComposition()) {
            this.showError('Choisissez au moins un vaisseau pour poursuivre.');
            this.quantityTargets[0]?.focus();
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
        this.render();
    }

    previous(event) {
        event.preventDefault();
        this.errorTarget.hidden = true;
        this.currentStep = Math.max(1, this.currentStep - 1);
        this.render();
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
        const ships = this.quantityTargets
            .filter(input => Number(input.value) > 0)
            .map(input => {
                const label = input.closest('label')?.querySelector('.fleet-ship-info strong')?.textContent.trim() || 'Vaisseau';
                return compactNumber(Number(input.value)) + ' × ' + label;
            });
        this.summaryTarget.textContent = mission + ' · ' + destination + ' · ' + ships.join(', ');
        this.summaryTarget.title = this.quantityTargets.filter(input => Number(input.value) > 0).map(input => exactNumber(Number(input.value)) + ' × ' + (input.closest('label')?.querySelector('strong')?.textContent.trim() || 'Vaisseau')).join(', ');
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
    }

    render() {
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
    }
}
