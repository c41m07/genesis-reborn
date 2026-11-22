import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge', 'conversations'];
    static values = { url: String };

    connect() {
        this.refresh = this.refresh.bind(this);
        window.addEventListener('genesis:refresh', this.refresh);
        window.addEventListener('genesis:journal-consulted', this.refresh);
        this.timer = window.setInterval(this.refresh, 15000);
        this.refresh();
    }

    disconnect() {
        window.removeEventListener('genesis:refresh', this.refresh);
        window.removeEventListener('genesis:journal-consulted', this.refresh);
        window.clearInterval(this.timer);
        this.abort?.abort();
    }

    async refresh(event) {
        if (event?.detail?.journal === true || event?.type === 'genesis:journal-consulted') {
            this.journalRefreshPending = true;
        }
        if (document.hidden || this.loading) return;
        this.journalRefreshPending = false;
        this.loading = true;
        const request = this.abort = new AbortController();
        try {
            const url = new URL(this.urlValue, window.location.origin);
            const current = new URLSearchParams(window.location.search);
            for (const key of ['with', 'channel']) if (current.has(key)) url.searchParams.set(key, current.get(key));
            const response = await fetch(url, { signal: request.signal, headers: { Accept: 'application/json' } });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return;
            const data = await response.json();
            if (request.signal.aborted) return;
            for (const badge of this.badgeTargets) badge.hidden = data.unread === 0;
            window.dispatchEvent(new CustomEvent('genesis:message-count', { detail: { count: data.unread } }));
            window.dispatchEvent(new CustomEvent('genesis:journal-state', {
                detail: { membership: data.membership, unread: data.journalUnread },
            }));
            if (this.hasConversationsTarget && !this.conversationsTarget.contains(document.activeElement)) {
                this.conversationsTarget.innerHTML = data.conversations;
            }
        } catch { /* La prochaine actualisation réessaiera. */ }
        finally {
            this.loading = false;
            // Coalescer les signaux reçus pendant une seule requête, sans multiplier les polls.
            if (this.journalRefreshPending && !request.signal.aborted) this.refresh();
        }
    }
}
