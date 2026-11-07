import { Controller } from '@hotwired/stimulus';

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
            if (!response.ok) {
                throw new Error(preview.error || 'Estimation indisponible.');
            }

            const format = value => Math.ceil(value).toLocaleString('fr-FR');
            this.resultTarget.textContent = `${format(preview.distance)} UA · ${Math.floor(preview.seconds / 3600)} h ${Math.floor(preview.seconds % 3600 / 60)} min ${preview.seconds % 60} s · Carburant aller-retour : ${format(preview.total_fuel)} H · Fret disponible : ${format(preview.freight_capacity)}`;
            this.resultTarget.classList.remove('error');
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
