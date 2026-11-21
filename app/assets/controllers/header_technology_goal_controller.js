import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { storageKey: String, url: String };
    static targets = ['label'];

    initialize() {
        this.fallbackLabel = this.labelTarget.textContent;
    }

    connect() {
        this.refresh();
    }

    disconnect() {
        this.lookup?.abort();
        this.lookup = null;
    }

    storageChanged(event) {
        if (event.key === this.storageKeyValue || event.key === null) this.refresh();
    }

    restored(event) {
        if (event.persisted) this.refresh();
    }

    goalChanged({ detail }) {
        if (detail?.storageKey !== this.storageKeyValue) return;
        // Le Guide a déjà résolu le candidat et même sans stockage l’action reste visible sur sa page.
        this.lookup?.abort();
        this.lookup = null;
        this.render(detail.goal);
    }

    async refresh() {
        this.lookup?.abort();
        this.lookup = null;
        this.render(null);
        let key;
        try {
            key = localStorage.getItem(this.storageKeyValue);
        } catch {
            return; // Le lien vers le Guide reste utilisable si le navigateur refuse le stockage.
        }
        if (!key || key.length > 160) return;

        const guide = document.querySelector('[data-technology-graph-storage-key-value]');
        if (guide?.dataset.technologyGraphStorageKeyValue === this.storageKeyValue) {
            // Sur la page du Guide, les libellés sont déjà rendus : ne pas les demander à nouveau.
            const node = guide.querySelector(`[data-node-key="${CSS.escape(key)}"]`);
            this.render(node ? { label: node.dataset.label, tier: node.dataset.tierLabel } : null);
            return;
        }

        const lookup = new AbortController();
        this.lookup = lookup;
        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('goal', key);
            const response = await fetch(url, { signal: lookup.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const { goal } = await response.json();
            // Une réponse tardive ne doit pas écraser un objectif changé ou retiré.
            if (this.lookup === lookup && this.element.isConnected) this.render(goal);
        } catch {
            // Si la fiche ne peut pas être résolue, conserver le lien et le libellé par défaut.
        } finally {
            if (this.lookup === lookup) this.lookup = null;
        }
    }

    render(goal) {
        this.labelTarget.textContent = goal?.label && goal?.tier
            ? `${goal.label} · ${goal.tier}`
            : this.fallbackLabel;
    }
}
