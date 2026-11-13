import { Controller } from '@hotwired/stimulus';

// Conserve les choix pendant le remplacement du catalogue par l’actualisation automatique.
const catalogStates = new Map();

export default class extends Controller {
    static targets = ['family', 'tab', 'panel', 'visibilitySwitch', 'visibilityInput', 'visibilityLabel', 'compactSwitch'];
    static values = { showLocked: Boolean };

    connect() {
        this.stateKey = `${location.pathname}:${this.element.id}`;
        const previous = catalogStates.get(this.stateKey);
        this.state = previous ?? { showLocked: this.showLockedValue, tiers: {}, category: 'all' };
        if (!this.element.querySelector(`[data-category="${this.state.category}"][aria-pressed]`)) this.state.category = 'all';
        this.showLockedValue = this.state.showLocked;
        try { this.state.compact = localStorage.getItem(`catalog-compact:${this.element.id}`) === 'true'; } catch { this.state.compact ??= false; }
        this.compactSwitchTarget.hidden = false;
        this.element.classList.add('building-catalog-ready');
        this.element.querySelectorAll('.building-rank-tabs').forEach(list => list.setAttribute('role', 'tablist'));
        this.tabTargets.forEach(tab => {
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', tab.hash.slice(1));
        });
        this.panelTargets.forEach(panel => {
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', `building-tab-${panel.closest('[data-slug]').dataset.slug}-${panel.dataset.tier}`);
        });
        this.element.querySelectorAll('[data-category][aria-pressed]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.category === this.state.category));
        });
        this.update();
        if (!previous || previous.followedHash !== location.hash) this.followHash();
        this.restoreHighlight();
    }

    toggleLocked(event) {
        event.preventDefault();
        this.showLockedValue = !this.showLockedValue;
        this.update();
    }

    toggleCompact() {
        this.state.compact = !this.state.compact;
        try { localStorage.setItem(`catalog-compact:${this.element.id}`, String(this.state.compact)); } catch { /* Le choix reste actif pour cette page. */ }
        this.update();
    }

    select(event) {
        event.preventDefault();
        this.activate(event.currentTarget, true);
    }

    activate(tab, remember = false) {
        if (remember) {
            if (location.hash !== tab.hash) history.pushState(null, '', tab.hash);
            this.state.followedHash = location.hash;
        }
        const family = tab.closest('[data-slug]');
        this.state.tiers[family.dataset.slug] = tab.dataset.tier;
        this.update();
    }

    navigate(event) {
        const tabs = [...event.currentTarget.closest('.building-rank-tabs').querySelectorAll('a:not([hidden])')];
        const index = tabs.indexOf(event.currentTarget);
        let next;
        if (event.key === 'ArrowRight') next = tabs[(index + 1) % tabs.length];
        else if (event.key === 'ArrowLeft') next = tabs[(index - 1 + tabs.length) % tabs.length];
        else if (event.key === 'Home') next = tabs[0];
        else if (event.key === 'End') next = tabs.at(-1);
        else if (event.key === ' ') next = event.currentTarget;
        if (!next) return;
        event.preventDefault();
        this.activate(next, true);
        next.focus();
    }

    rememberFilter(event) {
        const button = event.target.closest('[data-category][aria-pressed]');
        if (!button) return;
        this.state.category = button.dataset.category;
        this.update();
    }

    followHash() {
        const tab = this.tabTargets.find(candidate => candidate.hash === location.hash);
        if (!tab) return;
        this.showLockedValue = this.showLockedValue || tab.dataset.locked === 'true';
        if (this.state.category !== 'all' && tab.closest('[data-slug]').dataset.category !== this.state.category) this.state.category = 'all';
        this.element.querySelectorAll('[data-category][aria-pressed]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.category === this.state.category));
        });
        this.activate(tab);
        this.state.followedHash = location.hash;
        const panel = document.getElementById(tab.hash.slice(1));
        if (panel) {
            this.state.highlight = { slug: tab.closest('[data-slug]').dataset.slug, until: Date.now() + 5000 };
            this.restoreHighlight();
            requestAnimationFrame(() => {
                if (!panel.isConnected) return;
                const bars = [...document.querySelectorAll('.topbar, .resource-event-stack, .event-drawer-wrap')].filter(bar => ['fixed','sticky'].includes(getComputedStyle(bar).position));
                const top = Math.max(0, ...bars.map(bar => bar.getBoundingClientRect().bottom));
                const family = panel.closest('[data-slug]');
                window.scrollTo({ top: window.scrollY + family.getBoundingClientRect().top - top - 16, behavior: 'instant' });
            });
        }
        if (new URLSearchParams(location.search).get('focusItem') === '1') {
            requestAnimationFrame(() => {
                if (tab.isConnected) tab.focus({ preventScroll: true });
            });
        }
    }

    restoreHighlight() {
        clearTimeout(this.highlightTimer);
        const highlight = this.state.highlight;
        const remaining = (highlight?.until ?? 0) - Date.now();
        this.familyTargets.forEach(family => family.classList.toggle('catalog-card--targeted', remaining > 0 && family.dataset.slug === highlight.slug));
        if (remaining > 0) this.highlightTimer = setTimeout(() => {
            this.familyTargets.forEach(family => family.classList.remove('catalog-card--targeted'));
            this.state.highlight = null;
        }, remaining);
    }

    disconnect() { clearTimeout(this.highlightTimer); }

    update() {
        this.element.classList.toggle('catalog-condensed', !!this.state.compact);
        this.compactSwitchTarget.setAttribute('aria-checked', String(!!this.state.compact));
        this.visibilitySwitchTarget.setAttribute('aria-checked', String(this.showLockedValue));
        this.visibilityInputTarget.value = this.showLockedValue ? '0' : '1';
        this.visibilityLabelTarget.textContent = this.showLockedValue ? 'ON' : 'OFF';
        this.familyTargets.forEach(family => {
            const tabs = this.tabTargets.filter(tab => tab.closest('[data-slug]') === family);
            tabs.forEach(tab => { tab.hidden = !this.showLockedValue && tab.dataset.locked === 'true'; });
            const visibleTabs = tabs.filter(tab => !tab.hidden);
            const selected = visibleTabs.find(tab => tab.dataset.tier === this.state.tiers[family.dataset.slug])
                ?? visibleTabs.find(tab => tab.dataset.locked !== 'true') ?? visibleTabs[0];
            if (selected) this.state.tiers[family.dataset.slug] = selected.dataset.tier;
            family.dataset.catalogVisible = String(visibleTabs.length > 0);
            family.hidden = !visibleTabs.length || (this.state.category !== 'all' && family.dataset.category !== this.state.category);
            tabs.forEach(tab => {
                const active = tab === selected;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                document.getElementById(tab.hash.slice(1)).hidden = !active;
            });
        });
        const empty = this.element.querySelector('[data-catalog-empty]');
        if (empty) empty.hidden = this.familyTargets.some(family => !family.hidden);
        this.state.showLocked = this.showLockedValue;
        catalogStates.set(this.stateKey, this.state);
    }
}
