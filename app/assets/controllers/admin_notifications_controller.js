import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge'];
    static values = { url: String, initial: Object };

    connect() {
        this.render(this.initialValue);
        this.refresh = this.refresh.bind(this);
        this.timer = window.setInterval(this.refresh, 15000);
        window.addEventListener('genesis:refresh', this.refresh);
    }

    disconnect() {
        window.clearInterval(this.timer);
        window.removeEventListener('genesis:refresh', this.refresh);
        this.abort?.abort();
    }

    render(counts) {
        for (const badge of this.badgeTargets) {
            const count = Number(counts[badge.dataset.category]) || 0;
            badge.hidden = count === 0;
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.setAttribute('aria-label', `${count} ${badge.dataset.label}`);
        }
        window.dispatchEvent(new CustomEvent('genesis:admin-count', { detail: { count: Number(counts.total) || 0 } }));
    }

    async refresh() {
        if (document.hidden || this.loading) return;
        this.loading = true;
        this.abort = new AbortController();
        try {
            const response = await fetch(this.urlValue, { signal: this.abort.signal, headers: { Accept: 'application/json' } });
            if (response.ok && response.headers.get('content-type')?.includes('application/json')) this.render(await response.json());
        } catch { /* La prochaine vérification reprendra le compteur. */ }
        finally { this.loading = false; }
    }
}
