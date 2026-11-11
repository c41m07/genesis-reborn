import { Controller } from '@hotwired/stimulus';

// Conserve les choix pendant le remplacement du catalogue par l’actualisation automatique.
const catalogStates = new Map();

export default class extends Controller {
    static targets = ['family', 'tab', 'panel', 'visibilitySwitch', 'visibilityInput', 'visibilityLabel'];
    static values = { showLocked: Boolean };

    connect() {
        this.stateKey = `${location.pathname}:${this.element.id}`;
        const previous = catalogStates.get(this.stateKey);
        this.state = previous ?? { showLocked: this.showLockedValue, tiers: {}, category: 'all' };
        if (!this.element.querySelector(`[data-category="${this.state.category}"][aria-pressed]`)) this.state.category = 'all';
        this.showLockedValue = this.state.showLocked;
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
    }

    toggleLocked(event) {
        event.preventDefault();
        this.showLockedValue = !this.showLockedValue;
        this.update();
    }

    select(event) {
        event.preventDefault();
        this.activate(event.currentTarget);
    }

    activate(tab) {
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
        this.activate(next);
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
        this.state.category = 'all';
        this.element.querySelectorAll('[data-category][aria-pressed]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.category === 'all'));
        });
        this.activate(tab);
        this.state.followedHash = location.hash;
        const panel = document.getElementById(tab.hash.slice(1));
        panel?.scrollIntoView({ block: 'nearest' });
        if (new URLSearchParams(location.search).get('focusItem') === '1') {
            requestAnimationFrame(() => {
                if (tab.isConnected) tab.focus({ preventScroll: true });
            });
        }
    }

    update() {
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
