import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge', 'conversations'];
    static values = { url: String };

    connect() {
        this.refresh = this.refresh.bind(this);
        window.addEventListener('genesis:refresh', this.refresh);
        this.timer = window.setInterval(this.refresh, 15000);
        this.refresh();
    }

    disconnect() {
        window.removeEventListener('genesis:refresh', this.refresh);
        window.clearInterval(this.timer);
        this.abort?.abort();
    }

    async refresh() {
        if (document.hidden || this.loading) return;
        this.loading = true;
        this.abort = new AbortController();
        try {
            const url = new URL(this.urlValue, window.location.origin);
            const current = new URLSearchParams(window.location.search);
            for (const key of ['with', 'channel']) if (current.has(key)) url.searchParams.set(key, current.get(key));
            const response = await fetch(url, { signal: this.abort.signal, headers: { Accept: 'application/json' } });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return;
            const data = await response.json();
            for (const badge of this.badgeTargets) badge.hidden = data.unread === 0;
            if (this.hasConversationsTarget && !this.conversationsTarget.contains(document.activeElement)) {
                this.conversationsTarget.innerHTML = data.conversations;
            }
        } catch { /* La prochaine actualisation réessaiera. */ }
        finally { this.loading = false; }
    }
}
