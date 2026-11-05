import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['profile', 'multiplier'];

    apply() {
        const option = this.profileTarget.selectedOptions[0];
        const speed = Number(option?.dataset.speed);
        if (!Number.isFinite(speed) || speed <= 0) return;

        for (const input of this.multiplierTargets) {
            input.value = String(Number(speed.toFixed(3)));
        }
    }

    markCustom() {
        this.profileTarget.value = 'custom';
    }
}
