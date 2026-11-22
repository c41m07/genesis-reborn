import { Controller } from '@hotwired/stimulus';
import { technologyGuideGoal } from '../technology_guide.js';

export default class extends Controller {
    static values = { storageKey: String, url: String };
    static targets = ['label', 'progress'];

    initialize() {
        this.fallbackLabel = this.labelTarget.textContent;
        this.fallbackHref = this.element.getAttribute('href');
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
            this.render(technologyGuideGoal(node));
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
        const recommendation = goal?.recommendation;
        const step = recommendation?.state === 'step' ? recommendation.requirement : null;
        const label = step?.label ?? ({
            acquired: 'Objectif atteint',
            prerequisites_met: 'Prérequis atteints',
            unavailable: 'Ouvrir le Guide',
        }[recommendation?.state] ?? this.fallbackLabel);
        const progress = step ? `${step.current}/${step.level}` : '';

        this.labelTarget.textContent = label;
        this.progressTarget.hidden = !progress;
        this.progressTarget.textContent = progress;
        // Même lien serveur qu’un prérequis de fiche, pas l’objectif final indiqué au survol.
        this.element.setAttribute('href', step ? this.itemHref(goal.itemUrl) : this.fallbackHref);

        // Le texte complet reste accessible si un long nom de recherche est abrégé dans la barre.
        const summary = [label, progress ? `niveau ${step.current} sur ${step.level}` : ''].filter(Boolean).join(' · ');
        const detail = goal ? ` · Objectif : ${goal.label} (${goal.tier})` : '';
        this.element.title = summary + detail;
        this.element.setAttribute('aria-label', `Guide techno · ${summary}${detail}`);
    }

    itemHref(path) {
        if (typeof path !== 'string' || !path.startsWith('/')) return this.fallbackHref;
        try {
            const item = new URL(path, window.location.origin);
            if (item.origin === window.location.origin && /^#item-[a-z0-9_]+-(basic|intermediate|superior)$/.test(item.hash)) return path;
        } catch {
            // Un état vide ou périmé ne doit pas laisser le lien de la fiche précédente.
        }
        return this.fallbackHref;
    }
}
