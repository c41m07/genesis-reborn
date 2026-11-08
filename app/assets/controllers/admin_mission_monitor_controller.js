import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['results', 'status'];

    connect() { this.timer = window.setInterval(() => this.refresh(), 20000); }
    disconnect() { window.clearInterval(this.timer); this.abort?.abort(); }

    async refresh(event) {
        const manual = event?.type === 'click';
        if (document.hidden || this.loading || (!manual && (this.element.contains(document.activeElement) || !window.getSelection()?.isCollapsed))) return;
        this.loading = true;
        this.abort = new AbortController();
        try {
            const response = await fetch(window.location.href, {signal:this.abort.signal, headers:{Accept:'text/html'}});
            if (!response.ok) throw new Error('refresh');
            const document = new DOMParser().parseFromString(await response.text(), 'text/html');
            const results = document.querySelector('[data-admin-mission-monitor-target="results"]');
            if (!results) throw new Error('results');
            this.resultsTarget.innerHTML = results.innerHTML;
            this.statusTarget.textContent = 'Suivi actualisé.';
        } catch (error) {
            if (error.name !== 'AbortError') this.statusTarget.textContent = 'Actualisation indisponible. Le dernier relevé reste affiché.';
        } finally { this.loading = false; }
    }
}
