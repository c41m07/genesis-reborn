import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static values = { url: String };

    connect() {
        this.generation = (this.generation ?? 0) + 1;
        this.pendingRefresh = false;
        this.onRefresh = () => {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.refresh(), 200);
        };
        this.onFocusOut = () => {
            if (this.pendingRefresh) {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.refresh(), 200);
            }
        };
        window.addEventListener('genesis:refresh', this.onRefresh);
        document.addEventListener('focusout', this.onFocusOut);
    }

    disconnect() {
        ++this.generation;
        window.removeEventListener('genesis:refresh', this.onRefresh);
        document.removeEventListener('focusout', this.onFocusOut);
        clearTimeout(this.timer);
        this.abort?.abort();
    }

    async refresh() {
        this.abort?.abort();
        this.abort = new AbortController();
        const generation = ++this.generation;
        const contextUrl = location.href;
        try {
            const r = await fetch((location.pathname.startsWith('/planetes/') || location.pathname === '/flottes' || location.pathname.startsWith('/flottes/')) ? contextUrl : this.urlValue, {
                signal: this.abort.signal,
                headers: { 'X-Genesis-Live': '1', Accept: 'application/json' },
            });
            if (!r.ok || r.redirected) return;
            const html = r.headers.get('content-type')?.includes('application/json')
                ? Object.values((await r.json()).regions).join('\n') : await r.text();
            // AbortController ne suffit pas si une réponse a déjà été reçue ou décodée.
            if (generation !== this.generation || location.href !== contextUrl || !this.element.isConnected) return;
            const doc = new DOMParser().parseFromString(html, 'text/html');
            let deferred = false;
            // Préserver les formulaires modifiés et différer les zones portant le focus pour ne pas interrompre la saisie.
            for (const region of document.querySelectorAll('[data-live-region]')) {
                // Garder les événements affichés lisibles pendant cette visite, même après leur acquittement.
                if (region.dataset.liveRegion === 'journal' && region.dataset.journalConsultation) continue;
                if (region.querySelector('form[data-dirty="true"]')) continue;
                if (region.contains(document.activeElement)) {
                    deferred = true;
                    continue;
                }
                const next = doc.querySelector(`[data-live-region="${region.dataset.liveRegion}"]`);
                if (next) {
                    // Les panneaux du suivi restent ouverts après la mise à jour de leur contenu.
                    const panelScrolls = [];
                    for (const disclosure of region.querySelectorAll('details[data-catalog-disclosure][open]')) {
                        const key = disclosure.dataset.catalogDisclosure;
                        const replacement = [...next.querySelectorAll('details[data-catalog-disclosure]')]
                            .find(candidate => candidate.dataset.catalogDisclosure === key);
                        if (replacement) {
                            replacement.open = true;
                            const oldPanel = disclosure.querySelector('.catalog-follow-panel');
                            const newPanel = replacement.querySelector('.catalog-follow-panel');
                            if (oldPanel && newPanel) panelScrolls.push([newPanel, oldPanel.scrollTop]);
                        }
                    }
                    const left = region.scrollLeft;
                    region.replaceWith(next);
                    next.scrollLeft = left;
                    // Le défilement ne peut être restauré qu’une fois le panneau remis dans la page.
                    panelScrolls.forEach(([panel, top]) => { panel.scrollTop = top; });
                }
            }
            this.pendingRefresh = deferred;
        } catch (e) {
            if (generation !== this.generation || location.href !== contextUrl || !this.element.isConnected) return;
            if (e.name !== 'AbortError')
                this.element.dispatchEvent(new CustomEvent('genesis:offline', { bubbles: true }));
        }
    }
}
