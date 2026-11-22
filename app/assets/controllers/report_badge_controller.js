import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['count', 'indicator', 'description'];
    static values = { url: String, membership: Number };

    connect() {
        this.wake = event => {
            if (event.type === 'genesis:refresh' && !event.detail?.notificationId) return;
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => this.refresh(), 250);
        };
        this.interval = setInterval(() => this.refresh(), 15000);
        window.addEventListener('genesis:refresh', this.wake);
        window.addEventListener('genesis:report-consulted', this.wake);
        document.addEventListener('visibilitychange', this.wake);
    }

    disconnect() {
        clearTimeout(this.debounce);
        clearInterval(this.interval);
        window.removeEventListener('genesis:refresh', this.wake);
        window.removeEventListener('genesis:report-consulted', this.wake);
        document.removeEventListener('visibilitychange', this.wake);
        this.request?.abort();
    }

    async refresh() {
        if (document.hidden) return;
        if (this.request) {
            this.pending = true;
            return;
        }
        this.pending = false;
        const request = this.request = new AbortController();
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, signal: request.signal });
            if (!response.ok || response.redirected) return;
            const { count, membership } = await response.json();
            if (request.signal.aborted || membership !== this.membershipValue || !Number.isInteger(count) || count < 0) return;
            for (const badge of this.countTargets) {
                badge.hidden = count === 0;
                badge.textContent = count > 99 ? '99+' : String(count);
            }
            for (const indicator of this.indicatorTargets) indicator.hidden = count === 0;
            for (const description of this.descriptionTargets) {
                description.textContent = count === 0 ? '' : count === 1 ? '1 rapport non lu' : count + ' rapports non lus';
            }
        } catch {
            // Garder l’état précédent jusqu’à la prochaine lecture autorisée.
        } finally {
            if (this.request === request) this.request = null;
            if (this.pending && !request.signal.aborted) this.refresh();
        }
    }
}
