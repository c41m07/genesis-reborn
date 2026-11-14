import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content', 'status'];
    static values = { url: String, membership: Number, now: String };

    connect() {
        this.active = true;
        this.offset = Date.parse(this.nowValue) - Date.now();
        this.lastHtml = null;
        this.signature = [...this.contentTarget.querySelectorAll('[data-incoming-attack]')].map(row => row.dataset.incomingAttack).join(',');
        this.refreshHandler = () => this.refresh();
        this.visibilityHandler = () => { if (!document.hidden) this.refresh(); };
        window.addEventListener('genesis:refresh', this.refreshHandler);
        document.addEventListener('visibilitychange', this.visibilityHandler);
        this.poll = setInterval(() => this.refresh(), 5000);
        this.timer = setInterval(() => this.tick(), 1000);
        this.tick();
    }

    disconnect() {
        this.active = false;
        this.abort?.abort();
        clearInterval(this.poll); clearInterval(this.timer);
        window.removeEventListener('genesis:refresh', this.refreshHandler);
        document.removeEventListener('visibilitychange', this.visibilityHandler);
    }

    async refresh() {
        if (!this.active || document.hidden || this.pending) return;
        this.pending = true; this.abort = new AbortController();
        const timeout = setTimeout(() => this.abort.abort(), 10000);
        try {
            const url = new URL(this.urlValue, location.origin);
            if (document.querySelector('[data-controller~="orbit-requests"]')) url.searchParams.set('orbits', '1');
            const colony = document.querySelector('.fleet-page-layout[data-fleet-colony]')?.dataset.fleetColony;
            if (colony && colony !== '0') url.searchParams.set('colony', colony);
            const galaxy = document.querySelector('[data-surveillance-galaxy]')?.dataset.surveillanceGalaxy;
            if (Number(galaxy) > 0) url.searchParams.set('galaxy', galaxy);
            const response = await fetch(url, { signal: this.abort.signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) { this.unavailable(); return; }
            const data = await response.json();
            if (!this.active || data.membership !== this.membershipValue) return;
            if (this.failed) { this.statusTarget.textContent = ''; this.statusTarget.classList.add('visually-hidden'); this.failed = false; }
            this.offset = Date.parse(data.serverNow) - Date.now();
            if (data.html !== this.lastHtml && !this.contentTarget.contains(document.activeElement)) {
                this.contentTarget.innerHTML = data.html;
                this.lastHtml = data.html;
                const count = this.contentTarget.querySelectorAll('[data-incoming-attack]').length;
                const signature = [...this.contentTarget.querySelectorAll('[data-incoming-attack]')].map(row => row.dataset.incomingAttack).join(',');
                if (this.signature !== undefined && signature !== this.signature)
                    this.statusTarget.textContent = count ? count + ' attaque(s) détectée(s). Consultez les colonies ciblées.' : 'Aucune attaque détectée en trajet.';
                this.signature = signature;
            }
            this.tick();
            if (data.orbitHtml !== undefined) window.dispatchEvent(new CustomEvent('genesis:orbit-requests', { detail: data }));
            if (data.flights) window.dispatchEvent(new CustomEvent('genesis:visible-fleets', { detail: data }));
        } catch { if (this.active) this.unavailable(); }
        finally { clearTimeout(timeout); this.pending = false; }
    }

    unavailable() {
        this.failed = true;
        this.statusTarget.classList.remove('visually-hidden');
        this.statusTarget.textContent = 'Surveillance temporairement indisponible. Actualisez la page pour vérifier les attaques.';
    }

    tick() {
        for (const time of this.contentTarget.querySelectorAll('[data-incoming-impact]')) {
            const seconds = Math.max(0, Math.ceil((Date.parse(time.dataset.incomingImpact) - Date.now() - this.offset) / 1000));
            time.textContent = seconds ? Math.floor(seconds / 3600) + ' h ' + Math.floor(seconds % 3600 / 60) + ' min ' + seconds % 60 + ' s' : 'Impact imminent · arrivée en cours';
        }
    }
}
