import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        this.element.dataset.tabsReady = 'true';
        const requestedPanel = window.location.hash.slice(1);
        const panelId = this.panelTargets.some((panel) => panel.id === requestedPanel)
            ? requestedPanel
            : this.panelTargets[0]?.id;

        if (panelId) this.activate(panelId, false);
    }

    select(event) {
        this.activate(event.currentTarget.dataset.panelId, true);
    }

    sync() {
        const panelId = window.location.hash.slice(1);
        if (this.panelTargets.some((panel) => panel.id === panelId)) {
            this.activate(panelId, false);
        }
    }

    onKeydown(event) {
        const currentIndex = this.tabTargets.indexOf(event.currentTarget);
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

        if (updateHash && window.location.hash !== '#' + panelId) {
            window.history.replaceState(null, '', '#' + panelId);
        }
    }
}
