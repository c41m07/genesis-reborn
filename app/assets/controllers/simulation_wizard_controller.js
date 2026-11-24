import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['stage', 'link', 'current', 'sources'];
    static values = { step: String };

    connect() {
        this.narrow = window.matchMedia('(max-width: 800px)');
        this.sourcesTarget.open = !this.narrow.matches;
        this.resize = ({ matches }) => { if (!matches) this.sourcesTarget.open = true; };
        this.narrow.addEventListener('change', this.resize);
        const anchor = window.location.hash.replace('#simulation-', '');
        const requested = this.stageTargets.some(stage => stage.dataset.step === anchor) ? anchor : this.stepValue;
        this.show(requested);
    }

    disconnect() {
        this.narrow?.removeEventListener('change', this.resize);
        this.stageTargets.forEach(stage => { stage.hidden = false; });
    }

    go(event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        this.show(event.currentTarget.dataset.step, true);
    }

    returnToScenario() {
        this.show('ships');
    }

    show(step, focus = false) {
        const active = this.stageTargets.find(stage => stage.dataset.step === step) ?? this.stageTargets[0];
        this.currentTarget.value = active.dataset.step;
        for (const stage of this.stageTargets) stage.hidden = stage !== active;
        for (const link of this.linkTargets) {
            if (link.dataset.step === active.dataset.step) link.setAttribute('aria-current', 'step');
            else link.removeAttribute('aria-current');
        }
        if (focus) active.querySelector('h2').focus();
    }

    showInvalid(event) {
        // Rendre visible le premier champ invalide avant que le navigateur ne tente de le focaliser.
        if (this.invalidPending) return;
        const stage = event.target.closest('[data-simulation-wizard-target="stage"]');
        if (!stage) return;
        this.invalidPending = true;
        this.show(stage.dataset.step);
        const details = event.target.closest('details');
        if (details) details.open = true;
        queueMicrotask(() => { this.invalidPending = false; });
    }

    edited(event) {
        if (event.target.type !== 'number') return;
        const status = document.getElementById(event.target.getAttribute('aria-describedby'));
        if (!status?.hasAttribute('data-simulation-wizard-source-status')) return;
        const variants = {
            own: 'Valeurs modifiées pour cette simulation uniquement.',
            report: 'Hypothèse modifiée depuis le rapport.',
            unknown: 'Inconnu dans le rapport · hypothèse saisie.',
        };
        status.textContent = variants[status.dataset.wizardStatus];
    }
}
