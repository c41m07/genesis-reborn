import { Controller } from '@hotwired/stimulus';
import { compactNumber, exactNumber } from '../quantity.js';

export default class extends Controller {
    static targets = ['result'];
    static values = { url: String };

    get submitButton() {
        return this.element.querySelector('button:not([type="button"])');
    }

    initialize() {
        this.submitLocked = this.submitButton?.disabled ?? false;
    }

    connect() {
        this.schedule();
    }

    disconnect() {
        clearTimeout(this.timer);
        this.abort?.abort();
    }

    schedule(event) {
        clearTimeout(this.timer);
        if (event && this.hasResultTarget) {
            this.resultTarget.textContent = 'Estimation en cours…';
            this.resultTarget.classList.remove('error');
        }
        if (this.submitButton) {
            this.submitButton.disabled = true;
        }
        this.timer = setTimeout(() => this.estimate(), 300);
    }

    async estimate(event) {
        if (event && this.hasResultTarget) {
            this.resultTarget.textContent = 'Estimation en cours…';
            this.resultTarget.classList.remove('error');
        }
        if (event && this.submitButton) {
            this.submitButton.disabled = true;
        }
        this.abort?.abort();
        this.abort = new AbortController();

        try {
            const submitted = new URLSearchParams(new FormData(this.element)).toString();
            const response = await fetch(this.urlValue, {
                method: 'POST',
                body: new FormData(this.element),
                signal: this.abort.signal,
                headers: { Accept: 'application/json' },
            });

            if (!response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('Session expirée. Reconnectez-vous.');
            }

            const preview = await response.json();
            if (submitted !== new URLSearchParams(new FormData(this.element)).toString()) return;
            if (!response.ok) {
                throw new Error(preview.error || 'Estimation indisponible.');
            }

            const format = value => compactNumber(Math.ceil(value));
            const timezone = document.body.dataset.timezone || 'Europe/Paris';
            const arrival = preview.arrival_at ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'medium', timeZone: timezone }).format(new Date(preview.arrival_at)) + ' · ' + timezone : 'À confirmer';
            const list = document.createElement('dl');
            for (const [label, value] of [['Distance', exactNumber(Math.ceil(preview.distance)) + ' UA'], ['Durée', `${Math.floor(preview.seconds / 3600)} h ${Math.floor(preview.seconds % 3600 / 60)} min ${preview.seconds % 60} s`], ['Arrivée estimée', arrival], ['Carburant aller-retour', format(preview.total_fuel) + ' H'], ['Capacité de fret disponible', format(preview.freight_capacity)]]) {
                const entry = document.createElement('div'), term = document.createElement('dt'), description = document.createElement('dd');
                term.textContent = label; description.textContent = value; entry.append(term, description); list.append(entry);
            }
            this.resultTarget.replaceChildren(list);
            this.resultTarget.classList.remove('error');
            this.dispatch('quoted', { detail: preview });
            if (this.submitButton) {
                this.submitButton.disabled = this.submitLocked;
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.resultTarget.textContent = error.message;
                this.resultTarget.classList.add('error');
                if (this.submitButton) {
                    this.submitButton.disabled = true;
                }
            }
        }
    }
}
