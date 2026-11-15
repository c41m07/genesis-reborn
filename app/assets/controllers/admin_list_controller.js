import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'results', 'error'];

    connect() {
        this.generation = (this.generation ?? 0) + 1;
        // Une sélection peut précéder le chargement de Stimulus après la navigation.
        const url = new URL(window.location.href);
        if ([...new FormData(this.formTarget)].some(([key, value]) => value !== (url.searchParams.get(key) ?? ''))) {
            this.search();
        }
    }

    disconnect() {
        clearTimeout(this.timer);
        this.request?.abort();
        this.generation += 1;
    }

    schedule() {
        clearTimeout(this.timer);
        // Invalider dès la saisie : une réponse précédente ne doit plus être affichée.
        this.request?.abort();
        this.generation += 1;
        this.timer = setTimeout(() => this.search(), 250);
    }

    async search(event) {
        event?.preventDefault();
        clearTimeout(this.timer);
        this.request?.abort();
        const generation = ++this.generation;
        const request = new AbortController();
        this.request = request;
        const url = new URL(window.location.href);
        url.search = new URLSearchParams(new FormData(this.formTarget)).toString();
        this.resultsTarget.setAttribute('aria-busy', 'true');
        this.errorTarget.hidden = true;
        try {
            const response = await fetch(url, {
                headers: { 'X-Genesis-Admin-List': '1' },
                signal: request.signal,
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Recherche indisponible');
            const html = await response.text();
            if (generation !== this.generation || !this.element.isConnected) return;
            const fragment = new DOMParser().parseFromString(html, 'text/html')
                .querySelector('[data-admin-list-target="results"]');
            if (!fragment) throw new Error('Réponse de recherche invalide');
            this.resultsTarget.replaceWith(fragment);
            // Le formulaire reste en place : focus, texte et sélection sont conservés.
            window.history.replaceState(window.history.state, '', url);
        } catch (error) {
            if (error.name !== 'AbortError' && generation === this.generation && this.element.isConnected) {
                this.errorTarget.hidden = false;
            }
        } finally {
            if (generation === this.generation && this.element.isConnected) {
                this.resultsTarget.removeAttribute('aria-busy');
                this.request = null;
            }
        }
    }
}
