import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        this.element.dataset.tabsReady = 'true';
        const requestedPanel = window.location.hash.slice(1);
        const panelId = this.panelForAnchor(requestedPanel)?.id ?? this.panelTargets[0]?.id;

        if (panelId) this.activate(panelId, false);
    }

    select(event) {
        this.activate(event.currentTarget.dataset.panelId, true);
    }

    sync() {
        const panel = this.panelForAnchor(window.location.hash.slice(1));
        if (panel) {
            this.activate(panel.id, false);
        }
    }

    panelForAnchor(id) {
        const anchor = document.getElementById(id);
        return this.panelTargets.find((panel) => panel.id === id || (anchor && panel.contains(anchor)));
    }

    onKeydown(event) {
        const currentIndex = this.tabTargets.indexOf(event.target.closest('[role="tab"]'));
        if (currentIndex < 0 || event.altKey || event.ctrlKey || event.metaKey) return;
        let nextIndex = currentIndex;

        if (event.key === 'ArrowRight') nextIndex = (currentIndex + 1) % this.tabTargets.length;
        else if (event.key === 'ArrowLeft') nextIndex = (currentIndex - 1 + this.tabTargets.length) % this.tabTargets.length;
        else if (event.key === 'Home') nextIndex = 0;
        else if (event.key === 'End') nextIndex = this.tabTargets.length - 1;
        else return;

        event.preventDefault();
        const tab = this.tabTargets[nextIndex];
        tab.focus();
        this.activate(tab.dataset.panelId, true);
    }

    activate(panelId, updateHash) {
        for (const panel of this.panelTargets) panel.hidden = panel.id !== panelId;
        for (const tab of this.tabTargets) {
            const selected = tab.dataset.panelId === panelId;
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            tab.tabIndex = selected ? 0 : -1;
        }

        for (const link of document.querySelectorAll('[data-admin-panel-link]')) {
            const selected = link.dataset.adminPanelLink === panelId;
            link.classList.toggle('active', selected);
            if (selected) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        }

        if (updateHash && window.location.hash !== '#' + panelId) {
            window.history.replaceState(null, '', '#' + panelId);
        }
    }
}
