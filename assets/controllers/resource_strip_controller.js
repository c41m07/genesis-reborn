import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String };
    connect() { this.timer = setInterval(() => this.refresh(), 10000); }
    disconnect() { clearInterval(this.timer); this.abort?.abort(); }
    async refresh() {
        if (document.hidden || this.loading) return;
        this.loading = true;
        this.abort = new AbortController();
        try {
            const response = await fetch(this.urlValue, { signal: this.abort.signal });
            if (!response.ok) return;
            const resources = await response.json();
            for (const element of this.element.querySelectorAll('[data-resource-slug]')) {
                const resource = resources[element.dataset.resourceSlug];
                if (resource) element.dispatchEvent(new CustomEvent('resource:update', { detail: resource }));
            }
        } catch (error) {
            // Une interruption réseau conserve les dernières valeurs connues.
        } finally { this.loading = false; }
    }
}
