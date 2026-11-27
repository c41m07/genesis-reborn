import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge', 'conversations', 'guildRecruitment', 'guildRecruitmentDescription', 'inventoryGift', 'inventoryGiftDescription', 'inventoryGiftIndicator'];
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

    renderCount(badges, count) {
        if (!Number.isInteger(count) || count < 0) return false;
        for (const badge of badges) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count === 0;
        }
        return true;
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
            // La notification pousse une actualisation ; le serveur recompte selon le grade actuel.
            if (data.membership === Number(this.element.dataset.menuNotificationsMembershipValue)) {
                this.renderCount(this.guildRecruitmentTargets, data.guildRecruitmentCount);
                for (const description of this.guildRecruitmentDescriptionTargets) {
                    const count = data.guildRecruitmentCount;
                    description.textContent = count > 0 ? count + ' ' + (count === 1 ? description.dataset.singular : description.dataset.plural) : '';
                }
                const gifts = data.inventoryGiftCount;
                if (this.renderCount(this.inventoryGiftTargets, gifts)) {
                    for (const badge of this.inventoryGiftIndicatorTargets) badge.hidden = gifts === 0;
                    for (const description of this.inventoryGiftDescriptionTargets) {
                        description.textContent = gifts === 0 ? '' : gifts === 1 ? '1 nouveau cadeau' : gifts + ' nouveaux cadeaux';
                    }
                }
            }
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
