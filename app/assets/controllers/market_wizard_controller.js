import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['origin', 'step', 'indicator', 'control'];

    connect() {
        if (!this.hasStepTarget) return;
        this.controlTargets.forEach(button => { button.hidden = false; });
        this.show(0);
    }

    next() {
        for (const field of this.stepTargets[0].querySelectorAll('input, select')) {
            if (!field.reportValidity()) return;
        }
        this.show(1, true);
    }

    back() { this.show(0, true); }

    show(index, focus = false) {
        this.originTarget.hidden = index !== 0;
        this.stepTargets.forEach((panel, i) => { panel.hidden = i !== index; });
        this.indicatorTargets.forEach((item, i) => {
            item.classList.toggle('active', i === index);
            if (i === index) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
        });
        if (focus) this.stepTargets[index].querySelector('legend').focus();
    }
}
