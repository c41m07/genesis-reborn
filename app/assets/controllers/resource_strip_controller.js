import { Controller } from '@hotwired/stimulus';
import { displayQuantity } from '../quantity.js';

export default class extends Controller {
    static values = { url: String };
    static targets = ['protection', 'protectionLabel', 'protectionTime', 'toggle', 'compactStock'];
    connect() { this.element.classList.add('resource-strip--collapsible'); this.tickProtection(); this.protectionTimer = setInterval(() => this.tickProtection(), 1000); this.timer = setInterval(() => this.refresh(), 10000); }
    disconnect() { this.element.classList.remove('resource-strip--collapsible'); clearInterval(this.protectionTimer); clearInterval(this.timer); this.abort?.abort(); }
    toggleDetails() {
        const expanded = this.element.classList.toggle('resource-strip--expanded');
        this.toggleTarget.setAttribute('aria-expanded', String(expanded));
    }
    updateSummary({ detail: { slug, stock, deficit } }) {
        const target = this.compactStockTargets.find(element => element.dataset.slug === slug);
        if (!target) return;
        displayQuantity(target, Math.floor(stock));
        target.closest('.resource-summary-item').classList.toggle('resource-summary-item--deficit', deficit);
    }
    syncEconomy({ target: panel }) {
        const expanded = panel.matches(':popover-open');
        for (const trigger of this.element.querySelectorAll('[aria-expanded][popovertarget]')) {
            if (trigger.getAttribute('popovertarget') === panel.id) trigger.setAttribute('aria-expanded', String(expanded));
        }
    }
    tickProtection() {
        if (!this.hasProtectionTarget) return;
        const end = Date.parse(this.protectionTarget.dataset.until);
        const seconds = Number.isFinite(end) ? Math.max(0, Math.ceil((end - Date.now()) / 1000)) : 0;
        this.protectionLabelTarget.textContent = seconds ? 'Protégé du pillage' : 'Aucune protection temporaire';
        this.protectionTimeTarget.textContent = seconds ? `(${Math.floor(seconds / 86400)}j ${Math.floor(seconds % 86400 / 3600)}h ${Math.floor(seconds % 3600 / 60)}m ${seconds % 60}s)` : '';
    }
    async refresh() {
        if (document.hidden || this.loading) return;
        this.loading = true;
        this.abort = new AbortController();
        try {
            const response = await fetch(this.urlValue, { signal: this.abort.signal });
            if (!response.ok) return;
            const resources = await response.json();
            if (this.hasProtectionTarget && resources._protection) {
                this.protectionTarget.dataset.until = resources._protection.until ?? '';
                this.protectionTarget.title = resources._protection.reason ?? 'Aucune protection temporaire';
                this.tickProtection();
            }
            for (const element of this.element.querySelectorAll('[data-resource-slug]')) {
                const resource = resources[element.dataset.resourceSlug];
                if (resource) element.dispatchEvent(new CustomEvent('resource:update', { detail: { ...resource, vacation: resources._vacation } }));
            }
        } catch (error) {
            // Une interruption réseau conserve les dernières valeurs connues.
        } finally { this.loading = false; }
    }
}
